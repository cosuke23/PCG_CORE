<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FileCrypto;
use App\Support\FileKeys;
use App\Support\SecureFiles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Uploaded files are encrypted at rest with AES-256-GCM, decrypted only by
 * the controllers after the access checks, audited, and the keys can be
 * rotated (docs/FILE_ENCRYPTION.md).
 */
class FileEncryptionTest extends TestCase
{
    private string $root;
    private string $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/enc-uploads');
        $this->keys = storage_path('framework/testing/enc-keys');
        File::deleteDirectory($this->root);
        File::deleteDirectory($this->keys);
        File::ensureDirectoryExists($this->root);
        // every folder the commands walk points into the test folder
        config(['pcg.upload_root' => $this->root, 'pcg.selfie_dir' => $this->root . '/selfies',
            'pcg.document_root' => $this->root . '/personnel-documents', 'pcg.legacy_photo_dir' => $this->root . '/public-disk/profile_photos']);
        FileKeys::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::deleteDirectory($this->keys);
        FileKeys::flush();
        parent::tearDown();
    }

    private function useKeyRing(): void
    {
        config(['pcg.file_key' => '', 'pcg.file_keys_path' => $this->keys . '/file-keys.json']);
        FileKeys::flush();
        $this->artisan('pcg:file-keys', ['action' => 'init'])->assertSuccessful();
    }

    public function test_round_trip_for_empty_small_and_multi_chunk_files()
    {
        foreach (['', '%PDF-1.4 small', random_bytes(2 * 1048576 + 12345)] as $i => $plain) {
            $path = $this->root . "/f{$i}.bin";
            FileCrypto::encryptBytes($plain, $path, "area/f{$i}.bin");

            $this->assertTrue(FileCrypto::isEncrypted($path));
            if ($plain !== '') {
                $this->assertStringNotContainsString(substr($plain, 0, 12), (string) file_get_contents($path));
            }
            $this->assertSame($plain, FileCrypto::decryptToString($path, "area/f{$i}.bin"));
            $this->assertSame(strlen($plain), FileCrypto::info($path)['size']);
        }
    }

    public function test_tampering_truncation_and_a_moved_file_are_detected()
    {
        $path = $this->root . '/doc.pdf';
        $plain = '%PDF-' . random_bytes(3000);
        FileCrypto::encryptBytes($plain, $path, 'documents_toupload/documents/doc.pdf');
        $good = file_get_contents($path);

        // the same bytes under another record's path do not decrypt
        $this->assertDecryptFails($path, 'documents_toupload/documents/other.pdf');

        // one flipped byte in the content
        $bad = $good;
        $bad[strlen($bad) - 40] = chr(ord($bad[strlen($bad) - 40]) ^ 1);
        file_put_contents($path, $bad);
        $this->assertDecryptFails($path, 'documents_toupload/documents/doc.pdf');

        // cut off the end
        file_put_contents($path, substr($good, 0, -10));
        $this->assertDecryptFails($path, 'documents_toupload/documents/doc.pdf');

        // a different master key cannot open it
        file_put_contents($path, $good);
        config(['pcg.file_key' => base64_encode(random_bytes(32))]);
        FileKeys::flush();
        $this->assertDecryptFails($path, 'documents_toupload/documents/doc.pdf');
    }

    public function test_key_rotation_rewraps_files_and_the_old_key_can_then_be_retired()
    {
        $this->useKeyRing();
        $path = $this->root . '/documents_toupload/documents/a.pdf';
        SecureFiles::storeBytes('%PDF-rotate', $path, 'documents_toupload/documents/a.pdf');
        $old = FileKeys::activeId();
        $this->assertSame($old, FileCrypto::info($path)['kid']);
        $this->assertOwnerOnly(FileKeys::path());

        $this->artisan('pcg:file-keys', ['action' => 'rotate'])->assertSuccessful();
        FileKeys::flush();
        $new = FileKeys::activeId();
        $this->assertNotSame($old, $new);

        // the old key is still in use, so it cannot be retired yet
        $this->artisan('pcg:file-keys', ['action' => 'retire', 'id' => $old])->assertFailed();
        $this->artisan('pcg:files', ['action' => 'rewrap'])->assertSuccessful(); // dry run
        $this->assertSame($old, FileCrypto::info($path)['kid']);
        $this->artisan('pcg:files', ['action' => 'rewrap', '--apply' => true])->assertSuccessful();
        $this->assertSame($new, FileCrypto::info($path)['kid']);

        $this->artisan('pcg:file-keys', ['action' => 'retire', 'id' => $old])->assertSuccessful();
        FileKeys::flush();
        $this->assertSame([$new], FileKeys::ids());
        $this->assertSame('%PDF-rotate', FileCrypto::decryptToString($path, 'documents_toupload/documents/a.pdf'));
        $this->artisan('pcg:files', ['action' => 'verify'])->assertSuccessful();
    }

    public function test_key_ring_is_refused_inside_upload_or_public_folders_and_backup_is_private()
    {
        config(['pcg.file_key' => '', 'pcg.file_keys_path' => $this->root . '/keys.json']);
        FileKeys::flush();
        try {
            FileKeys::ring();
            $this->fail('A key ring inside the upload folder must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('must not be inside', $e->getMessage());
        }

        $this->useKeyRing();
        $this->artisan('pcg:file-keys', ['action' => 'backup', '--to' => public_path('keys.json')])->assertFailed();
        $this->assertFileDoesNotExist(public_path('keys.json'));
        $this->artisan('pcg:file-keys', ['action' => 'backup', '--to' => $this->keys . '/backup.json'])->assertSuccessful();
        $this->assertOwnerOnly($this->keys . '/backup.json');
        $this->artisan('pcg:file-keys', ['action' => 'init'])->assertFailed(); // never replaced
    }

    public function test_existing_plain_files_are_encrypted_in_place_and_old_public_photos_are_moved()
    {
        File::ensureDirectoryExists($this->root . '/201_files/soi');
        File::put($this->root . '/201_files/soi/old.pdf', '%PDF-legacy');
        $publicPhotos = config('pcg.legacy_photo_dir');
        File::ensureDirectoryExists($publicPhotos);
        File::put($publicPhotos . '/enc-test-photo.jpg', 'legacy-photo');

        $this->artisan('pcg:files', ['action' => 'encrypt'])->assertSuccessful(); // dry run changes nothing
        $this->assertFalse(FileCrypto::isEncrypted($this->root . '/201_files/soi/old.pdf'));

        $this->artisan('pcg:files', ['action' => 'encrypt', '--apply' => true])->assertSuccessful();
        $this->assertSame('%PDF-legacy', FileCrypto::decryptToString($this->root . '/201_files/soi/old.pdf', '201_files/soi/old.pdf'));
        $this->assertFileDoesNotExist($publicPhotos . '/enc-test-photo.jpg');
        $this->assertSame('legacy-photo', FileCrypto::decryptToString($this->root . '/profile_photos/enc-test-photo.jpg', 'profile_photos/enc-test-photo.jpg'));
        $this->artisan('pcg:files', ['action' => 'status'])->expectsOutputToContain('Every stored file is encrypted.')->assertSuccessful();
    }

    public function test_files_are_served_decrypted_only_after_the_access_check_and_every_view_is_audited()
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('serial_number')->nullable(); $t->string('selfie_image')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('profiles', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('photo')->nullable();
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('admin_user_id')->nullable(); $t->string('admin_email')->nullable(); $t->string('action');
            $t->string('method')->nullable(); $t->string('subject')->nullable(); $t->integer('status')->nullable(); $t->string('ip')->nullable();
            $t->string('user_agent')->nullable(); $t->text('details')->nullable(); $t->timestamp('created_at')->nullable();
        });

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        SecureFiles::storeBytes($png, $this->root . '/selfies/me.png', 'selfies/me.png');
        SecureFiles::storeBytes($png, $this->root . '/profile_photos/me.png', 'profile_photos/me.png');
        $owner = User::forceCreate(['name' => 'Owner', 'email' => 'o@example.test', 'password' => Hash::make('x'), 'selfie_image' => 'selfies/me.png']);
        $other = User::forceCreate(['name' => 'Other', 'email' => 'x@example.test', 'password' => Hash::make('x')]);
        DB::table('profiles')->insert(['user_id' => $owner->id, 'photo' => 'profile_photos/me.png']);

        $this->get('/files/selfies/me.png')->assertRedirect(route('login'));
        $this->actingAs($other)->get('/files/selfies/me.png')->assertNotFound();
        $this->actingAs($other)->get('/files/profile_photos/me.png')->assertNotFound();

        $view = $this->actingAs($owner)->get('/files/selfies/me.png')->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($png, $view->streamedContent());
        $this->assertStringContainsString('no-store', $view->headers->get('Cache-Control'));
        $this->assertStringStartsWith('inline', $view->headers->get('Content-Disposition'));
        $this->assertSame($png, $this->actingAs($owner)->get('/files/profile_photos/me.png')->assertOk()->streamedContent());

        $download = $this->actingAs($owner)->get('/files/selfies/me.png?download=1')->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));

        // admins: shown, and written to the Audit log page
        auth()->logout();
        Schema::create('admin_users', function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('role'); $t->unsignedBigInteger('org_unit_id')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        DB::table('admin_users')->insert(['id' => 7, 'email' => 'a@example.test', 'role' => 'super_admin', 'is_active' => true]);
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => 7, 'admin_email' => 'a@example.test']);
        $this->get('/files/selfies/me.png?download=1')->assertOk();
        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'file.download')->where('subject', 'selfies/me.png')->where('admin_user_id', 7)->count());

        // a deactivated admin loses file access at once, even on /files (outside the admin pages)
        DB::table('admin_users')->where('id', 7)->update(['is_active' => false]);
        $this->get('/files/selfies/me.png')->assertNotFound();
        DB::table('admin_users')->where('id', 7)->update(['is_active' => true]);
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => 7, 'admin_email' => 'a@example.test']);

        // demoted to a role without documents.view: no files, whatever the old session said
        DB::table('admin_users')->where('id', 7)->update(['role' => 'lms_admin']);
        $this->get('/files/selfies/me.png')->assertNotFound();
        DB::table('admin_users')->where('id', 7)->update(['role' => 'super_admin']);

        // a damaged file is refused, not served
        $bytes = file_get_contents($this->root . '/selfies/me.png');
        $bytes[strlen($bytes) - 5] = chr(ord($bytes[strlen($bytes) - 5]) ^ 1);
        file_put_contents($this->root . '/selfies/me.png', $bytes);
        $this->get('/files/selfies/me.png')->assertStatus(500);
    }

    public function test_no_upload_path_writes_a_readable_file()
    {
        // Every place that stores an upload goes through SecureFiles.
        foreach (['ApplicantDocumentController', 'UnitUserController', 'PersonnelDocumentController', 'Auth/RegisterController', 'DashboardController'] as $c) {
            $code = file_get_contents(app_path("Http/Controllers/{$c}.php"));
            $this->assertDoesNotMatchRegularExpression('/->move\(|->store\(|file_put_contents\(|File::put\(/', $code, "{$c} writes an upload without encryption");
        }
    }

    /** Mode 0600 on Linux/macOS (Hermes). Windows has no Unix permissions: PHP always reports 0666 there. */
    private function assertOwnerOnly(string $path): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->assertFileExists($path);

            return;
        }
        $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    private function assertDecryptFails(string $path, string $logical): void
    {
        try {
            FileCrypto::decryptToString($path, $logical);
            $this->fail('Decryption should have failed.');
        } catch (RuntimeException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }
}

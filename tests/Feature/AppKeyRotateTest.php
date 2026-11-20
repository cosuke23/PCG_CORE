<?php

namespace Tests\Feature;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** pcg:app-key-rotate: new APP_KEY, admin two-factor data re-encrypted, old key not kept, .env backed up. */
class AppKeyRotateTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/app-key-' . bin2hex(random_bytes(3)));
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir . '/.env', "APP_NAME=Test\nAPP_KEY=" . config('app.key') . "\nAPP_PREVIOUS_KEYS=base64:" . base64_encode(random_bytes(32)) . "\nDB_CONNECTION=sqlite\n");
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_the_key_is_replaced_and_two_factor_data_still_opens()
    {
        $old = new Encrypter(base64_decode(substr(config('app.key'), 7)), config('app.cipher'));
        $id = DB::table('admin_users')->insertGetId(['name' => 'A', 'email' => 'a@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true,
            'two_factor_secret' => $old->encryptString('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => $old->encryptString('["AAAAA-BBBBB"]'),
            'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('pcg:app-key-rotate')->assertSuccessful();               // dry run
        $this->assertStringContainsString(config('app.key'), File::get($this->dir . '/.env'));

        $this->artisan('pcg:app-key-rotate', ['--apply' => true])->assertSuccessful();
        $env = File::get($this->dir . '/.env');
        preg_match('/^APP_KEY=(\S+)$/m', $env, $m);
        $this->assertNotSame(config('app.key'), $m[1]);
        $this->assertStringNotContainsString('APP_PREVIOUS_KEYS', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertCount(1, File::glob($this->dir . '/.env.bak-*'));

        $new = new Encrypter(base64_decode(substr($m[1], 7)), config('app.cipher'));
        $row = DB::table('admin_users')->find($id);
        $this->assertSame('JBSWY3DPEHPK3PXP', $new->decryptString($row->two_factor_secret));
        $this->assertSame('["AAAAA-BBBBB"]', $new->decryptString($row->two_factor_recovery_codes));
        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);
        $old->decryptString($row->two_factor_secret);
    }

    public function test_nothing_changes_when_stored_data_does_not_open()
    {
        DB::table('admin_users')->insert(['name' => 'B', 'email' => 'b@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true,
            'two_factor_secret' => 'not-encrypted', 'created_at' => now(), 'updated_at' => now()]);
        $before = File::get($this->dir . '/.env');
        $this->artisan('pcg:app-key-rotate', ['--apply' => true])->assertFailed();
        $this->assertSame($before, File::get($this->dir . '/.env'));
    }
}

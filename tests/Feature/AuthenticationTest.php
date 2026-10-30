<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Login behaviour for the three account types. Runs on in-memory SQLite
 * (phpunit.xml) with only the columns these flows touch; it never connects
 * to the real pcg_core database.
 */
class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('rank')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('unit_users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->string('unit_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        config([
            'pcg.admin.email' => 'admin@example.test',
            'pcg.admin.password_hash' => Hash::make('correct-horse-battery'),
        ]);
    }

    private function makeUser(string $status, string $emailPrefix = null): User
    {
        return User::forceCreate([
            'name' => 'Test Member',
            'email' => ($emailPrefix ?? strtolower($status)) . '@example.test',
            'password' => Hash::make('secret-password'),
            'serial_number' => 'SN-' . $status,
            'status' => $status,
        ]);
    }

    // ---------------- Admin ----------------

    public function test_admin_can_login_with_configured_credentials()
    {
        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(session('admin_logged_in'));
    }

    public function test_admin_login_rejects_wrong_password()
    {
        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertNull(session('admin_logged_in'));
    }

    public function test_only_the_configured_admin_email_is_accepted()
    {
        // The two formerly hardcoded admin accounts must not work unless
        // they are the account configured in .env.
        foreach (['emsorix19@gmail.com', 'colminasjulius@gmail.com'] as $email) {
            $this->post('/admin/login', [
                'email' => $email,
                'password' => 'correct-horse-battery',
            ])->assertSessionHasErrors('email');
        }

        $this->assertNull(session('admin_logged_in'));
    }

    public function test_admin_login_fails_closed_when_not_configured()
    {
        config(['pcg.admin.email' => null, 'pcg.admin.password_hash' => null]);

        $this->post('/admin/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-horse-battery',
        ])->assertSessionHasErrors('email');

        $this->assertNull(session('admin_logged_in'));
    }

    public function test_legacy_get_logout_ends_admin_session()
    {
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin'])
            ->get('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertNull(session('admin_logged_in'));
    }

    // ---------------- Applicant ----------------

    public function test_approved_applicant_can_login()
    {
        $this->makeUser('Approved');

        $this->post('/login', [
            'email' => 'approved@example.test',
            'password' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    /**
     * "Rejected" (capital R, as written by AdminController::reject) used to
     * fall through the status check and log the user in.
     */
    public function test_rejected_and_pending_applicants_cannot_login()
    {
        foreach (['Rejected', 'rejected', 'Pending', 'Suspended', ''] as $i => $status) {
            $user = $this->makeUser($status, 'member' . $i);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    // ---------------- Unit checker ----------------

    private function makeUnitUser(?string $password): void
    {
        DB::table('unit_users')->insert([
            'full_name' => 'Unit Checker',
            'email' => 'unit@example.test',
            'password' => $password ? Hash::make($password) : null,
            'role' => 'unit_checker',
            'unit_name' => 'MEPCOM',
            'is_active' => 1,
        ]);
    }

    public function test_unit_login_requires_correct_password()
    {
        $this->makeUnitUser('unit-password');

        $this->post('/unit/login', ['email' => 'unit@example.test', 'password' => 'wrong'])
            ->assertSessionHas('error');
        $this->assertFalse(session()->has('unit_user_id'));

        $this->post('/unit/login', ['email' => 'unit@example.test', 'password' => 'unit-password'])
            ->assertRedirect('/unit/dashboard');
        $this->assertTrue(session()->has('unit_user_id'));
    }

    public function test_unit_login_without_password_is_rejected()
    {
        $this->makeUnitUser(null);

        $this->post('/unit/loginM1', ['email' => 'unit@example.test'])
            ->assertSessionHasErrors('password');

        $this->post('/unit/loginM1', ['email' => 'unit@example.test', 'password' => 'anything'])
            ->assertSessionHas('error');

        $this->assertFalse(session()->has('unit_user_id'));
    }

    // ---------------- Uploads ----------------

    public function test_document_upload_rejects_executable_files()
    {
        $user = $this->makeUser('Approved');

        $this->actingAs($user)
            ->post('/documents/upload', [
                'soi' => \Illuminate\Http\UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
            ])
            ->assertSessionHasErrors('soi');
    }

    // ---------------- Sign-in pages ----------------

    public function test_sign_in_pages_render_without_cdn_assets()
    {
        foreach (['/login', '/admin/login', '/unit/login', '/unit/loginM1'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('css/pcg-ui.css', false)
                ->assertDontSee('cdn.tailwindcss.com', false);
        }
    }

    public function test_sign_in_errors_are_shown_and_email_is_kept()
    {
        $this->from('/admin/login')
            ->followingRedirects()
            ->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'wrong'])
            ->assertSee('Invalid admin credentials')
            ->assertSee('value="admin@example.test"', false);

        $this->makeUnitUser('unit-password');

        $this->from('/unit/login')
            ->followingRedirects()
            ->post('/unit/login', ['email' => 'unit@example.test', 'password' => 'wrong'])
            ->assertSee('Invalid email, password, or inactive account.')
            ->assertSee('value="unit@example.test"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PCG HRMIS Android app (owner request 2026-11-20): only members whose SOI is
 * submitted see and download the APK; the file is served from outside public/.
 */
class MobileAppTest extends TestCase
{
    use RefreshDatabase;

    private string $apk;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->string('unit_assignment')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_09_000002_add_soi_submitted_at_to_users.php'))->up();

        $this->apk = tempnam(sys_get_temp_dir(), 'apk');
        file_put_contents($this->apk, 'PK fake apk bytes');
        config(['pcg.android_app.apk_path' => $this->apk, 'pcg.android_app.version' => '1.0.0']);
    }

    protected function tearDown(): void
    {
        @unlink($this->apk);
        parent::tearDown();
    }

    private function member(bool $submitted): User
    {
        return User::forceCreate([
            'name' => 'Ana Member', 'email' => 'ana' . ($submitted ? '1' : '0') . '@x.test', 'password' => bcrypt('x'),
            'serial_number' => $submitted ? 'SN-1' : 'SN-0', 'status' => 'Approved',
            'soi_submitted_at' => $submitted ? now() : null,
        ]);
    }

    public function test_a_member_without_a_submitted_soi_sees_no_download()
    {
        $this->actingAs($this->member(false));

        $this->get('/mobile-app')->assertOk()
            ->assertSee('Submit your SOI to get the Android app')
            ->assertDontSee(route('mobile.app.download'));

        $this->get('/mobile-app/download')->assertRedirect(route('mobile.app'))
            ->assertSessionHas('error');
    }

    public function test_a_member_with_a_submitted_soi_downloads_the_apk()
    {
        $this->actingAs($this->member(true));

        $this->get('/mobile-app')->assertOk()
            ->assertSee('The PCG HRMIS Android app is ready for you')
            ->assertSee(route('mobile.app.download'))
            ->assertSee('1.0.0');

        $response = $this->get('/mobile-app/download');
        $response->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('PCG-HRMIS-1.0.0.apk', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_nothing_is_offered_until_an_apk_is_published()
    {
        config(['pcg.android_app.apk_path' => $this->apk . '-missing']);
        $this->actingAs($this->member(true));

        $this->get('/mobile-app')->assertOk()->assertSee('The Android app is being prepared')
            ->assertDontSee(route('mobile.app.download'));
        $this->get('/mobile-app/download')->assertRedirect(route('mobile.app'))->assertSessionHas('error');
    }

    public function test_the_sidebar_links_the_app_page_for_every_member()
    {
        $this->actingAs($this->member(false))->get('/mobile-app')
            ->assertSee(route('mobile.app'))->assertSee('Android app');
    }

    public function test_guests_are_sent_to_sign_in()
    {
        $this->get('/mobile-app')->assertRedirect(route('login'));
        $this->get('/mobile-app/download')->assertRedirect(route('login'));
    }
}

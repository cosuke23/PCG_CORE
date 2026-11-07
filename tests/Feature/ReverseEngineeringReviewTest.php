<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Findings of the 2026-11-07 reverse-engineering review (SECURITY_AUDIT section 0i).
 */
class ReverseEngineeringReviewTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('rank')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        $this->user = User::forceCreate([
            'name' => 'Member', 'email' => 'member@example.test', 'password' => Hash::make('secret-password'),
            'serial_number' => 'SN-1', 'status' => 'Approved', 'rank' => 'PO1',
        ]);
    }

    public function test_member_write_and_upload_routes_are_rate_limited()
    {
        $expect = [
            'temp.save.step1' => 'throttle:member-writes', 'temp.save.step2' => 'throttle:member-writes',
            'temp.save.step3' => 'throttle:member-writes', 'temp.save.step4' => 'throttle:member-writes',
            'temp.save.step5' => 'throttle:member-writes', 'temp.save.step6' => 'throttle:member-writes',
            'profile.update' => 'throttle:member-uploads', 'upload.201.store' => 'throttle:member-uploads',
        ];
        foreach ($expect as $name => $limiter) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains($limiter, $route->gatherMiddleware(), $name);
        }

        // the member document upload (not the admin one) is limited too
        $docs = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('POST', $r->methods()) && in_array('auth', $r->gatherMiddleware()) && str_contains($r->uri(), 'documents'))
            ->filter(fn ($r) => !str_starts_with($r->uri(), 'admin') && !str_starts_with($r->uri(), 'unit'));
        $this->assertNotEmpty($docs);
        foreach ($docs as $r) {
            $this->assertNotEmpty(array_filter($r->gatherMiddleware(), fn ($m) => str_starts_with($m, 'throttle:member-')), $r->uri());
        }
    }

    public function test_step5_refuses_oversized_or_nested_lists()
    {
        $this->actingAs($this->user);

        $this->postJson('/temp-save/step5', ['duties' => array_fill(0, 11, [])])->assertStatus(422);
        $this->postJson('/temp-save/step5', ['duties' => [[['x' => ['deep' => 'array']]]]])->assertStatus(422);
        $this->postJson('/temp-save/step5', ['appointments' => [['description' => str_repeat('a', 501)]]])->assertStatus(422);
    }

    public function test_step6_refuses_oversized_or_nested_lists_with_422()
    {
        $this->actingAs($this->user);

        $this->postJson('/temp-save/step6', ['careers' => array_fill(0, 201, ['x' => 'y'])])->assertStatus(422);
        $this->postJson('/temp-save/step6', ['careers' => [['x' => ['nested']]]])->assertStatus(422);
        $this->postJson('/temp-save/step6', ['careers' => [['x' => str_repeat('a', 1001)]]])->assertStatus(422);
    }

    public function test_member_writes_stop_after_the_limit()
    {
        $this->actingAs($this->user);
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/temp-save/step5', ['duties' => array_fill(0, 11, [])])->assertStatus(422);
        }
        $this->postJson('/temp-save/step5', [])->assertStatus(429);
    }

    public function test_sign_in_code_is_not_in_the_email_subject()
    {
        $mail = new \App\Mail\AdminLoginCode(new \App\Models\AdminUser(), '123456', 10);
        $this->assertStringNotContainsString('123456', $mail->envelope()->subject);
    }
}

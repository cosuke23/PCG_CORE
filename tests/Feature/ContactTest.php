<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_contact_form_stores_message()
    {
        $this->get('/contact')->assertOk()->assertSee('action="' . route('contact.store') . '"', false);

        $this->post('/contact', ['name' => 'Juan', 'email' => 'juan@example.test', 'subject' => 'Help', 'message' => 'I cannot upload my NBI clearance.'])
            ->assertRedirect('/contact')->assertSessionHas('success');

        $this->assertSame('new', DB::table('contact_messages')->value('status'));
    }

    public function test_validation_and_honeypot()
    {
        $this->post('/contact', ['name' => '', 'email' => 'bad', 'message' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->post('/contact', ['name' => 'Bot', 'email' => 'b@example.test', 'message' => 'Buy things now please', 'website' => 'http://spam'])
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('contact_messages')->count());
    }

    public function test_inbox_is_for_super_and_hr_admins()
    {
        DB::table('contact_messages')->insert(['name' => 'A', 'email' => 'a@example.test', 'message' => 'Hello there team', 'status' => 'new', 'created_at' => now()]);
        $id = DB::table('contact_messages')->value('id');

        $this->get('/admin/messages')->assertRedirect(route('admin.login'));
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'lms_admin'])->get('/admin/messages')->assertForbidden();

        $this->flushSession();
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'hr_admin']);
        $this->get('/admin/messages')->assertOk()->assertSee('Hello there team');
        $this->post("/admin/messages/$id/status", ['status' => 'archived'])->assertSessionHas('success');
        $this->assertSame('archived', DB::table('contact_messages')->value('status'));
    }

    public function test_face_verification_routes_are_gone()
    {
        $this->get('/face-verify')->assertNotFound();
    }
}

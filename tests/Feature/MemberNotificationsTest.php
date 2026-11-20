<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MemberNotifications;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** In-app notifications and the Android poll (owner request 2026-11-20). */
class MemberNotificationsTest extends TestCase
{
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_20_000005_create_member_notifications_table.php'))->up();
        $this->ana = User::forceCreate(['name' => 'Ana Reyes', 'email' => 'ana@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-A', 'status' => 'Approved']);
    }

    public function test_the_page_lists_and_marks_read()
    {
        MemberNotifications::send($this->ana->id, 'leave.approved', 'Your RNR is approved', 'Nov 24–27', '/leave');
        MemberNotifications::send($this->ana->id, 'documents.rejected', '1 document was returned to you', null, 'https://evil.test/x');
        $this->assertSame(2, MemberNotifications::unread($this->ana->id));
        $this->assertNull(DB::table('member_notifications')->where('kind', 'documents.rejected')->value('url'));   // only portal paths

        $this->actingAs($this->ana)->get('/notifications')->assertOk()
            ->assertSee('You have 2 new notifications')->assertSee('Your RNR is approved')->assertSee('Nov 24–27');
        $this->assertSame(0, MemberNotifications::unread($this->ana->id));
        $this->get('/notifications')->assertSee('You have no new notifications');
    }

    public function test_the_app_polls_with_its_token_only()
    {
        MemberNotifications::send($this->ana->id, 'leave.approved', 'Your RNR is approved', null, '/leave');
        $t = MemberNotifications::appToken($this->ana);
        $this->getJson('/api/app/notifications?u=' . $this->ana->id . '&t=' . $t . '&after=0')->assertOk()
            ->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', 'Your RNR is approved')->assertCookieMissing(config('session.cookie'));
        $id = DB::table('member_notifications')->value('id');
        $this->getJson('/api/app/notifications?u=' . $this->ana->id . '&t=' . $t . '&after=' . $id)->assertJsonCount(0, 'items');

        $this->getJson('/api/app/notifications?u=' . $this->ana->id . '&t=' . str_repeat('a', 64))->assertStatus(401);
        $this->ana->forceFill(['password' => bcrypt('new-password')])->save();                 // a new password revokes the token
        $this->getJson('/api/app/notifications?u=' . $this->ana->id . '&t=' . $t)->assertStatus(401);
    }

    public function test_the_layout_gives_the_token_only_to_the_android_app()
    {
        $this->actingAs($this->ana)->get('/notifications')->assertDontSee('pcg-app-notify', false);
        $this->withHeader('User-Agent', 'Mozilla/5.0 PCGHRMIS-Android/1.0.3')->get('/notifications')
            ->assertSee('name="pcg-app-notify" content="' . $this->ana->id . ':' . MemberNotifications::appToken($this->ana) . '"', false);
    }
}

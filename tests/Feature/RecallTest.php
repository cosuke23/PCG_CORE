<?php

namespace Tests\Feature;

use App\Mail\RecallAlert;
use App\Models\UnitUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Emergency recall: send to a unit, members answer, checker sees who answered (owner request 2026-11-20). */
class RecallTest extends TestCase
{
    private array $unit = ['unit_user_id' => 1, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF', 'unit_full_name' => 'Unit Checker'];
    private array $other = ['unit_user_id' => 3, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'NOCOP'];
    private array $m1 = ['unit_user_id' => 2, 'unit_role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM', 'unit_full_name' => 'M1'];
    private User $ana;
    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureUnitSession::class);
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_21_000004_create_recall_tables.php'))->up();
        (require base_path('database/migrations/2026_11_20_000005_create_member_notifications_table.php'))->up();
        $this->ana = User::forceCreate(['name' => 'Ana', 'email' => 'ana@x.test', 'password' => bcrypt('x'), 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        User::forceCreate(['name' => 'Cara', 'email' => 'cara@x.test', 'password' => bcrypt('x'), 'status' => 'Approved', 'mother_unit' => 'meplef ']);
        User::forceCreate(['name' => 'Pending', 'email' => 'p@x.test', 'password' => bcrypt('x'), 'status' => 'Pending', 'mother_unit' => 'MEPLEF']);
        $this->ben = User::forceCreate(['name' => 'Ben', 'email' => 'ben@x.test', 'password' => bcrypt('x'), 'status' => 'Approved', 'mother_unit' => 'NOCOP']);
    }

    public function test_a_unit_recall_reaches_its_approved_members_and_collects_answers()
    {
        Mail::fake();
        $this->withSession($this->unit)->post('/unit/recall', ['title' => 'Oil spill', 'message' => 'Report now'])->assertSessionHasErrors('confirm');
        $this->post('/unit/recall', ['title' => 'Oil spill', 'message' => 'Report now', 'report_to' => 'HQ by 1800H', 'confirm' => '1'])->assertRedirect();
        $id = DB::table('recalls')->value('id');
        $this->assertSame(2, DB::table('recall_responses')->where('recall_id', $id)->count());          // Ana and Cara only
        Mail::assertSent(RecallAlert::class, 2);
        $this->assertSame(2, DB::table('member_notifications')->where('kind', 'recall')->count());

        $this->actingAs($this->ana)->get("/recall/$id")->assertOk()->assertSee('Oil spill')->assertSee('HQ by 1800H');
        $this->post("/recall/$id", ['answer' => 'cannot'])->assertSessionHasErrors('note');
        $this->post("/recall/$id", ['answer' => 'coming'])->assertSessionHas('success');
        $this->actingAs($this->ben)->get("/recall/$id")->assertNotFound();                               // not recalled

        $this->withSession($this->unit)->get("/unit/recall/$id")->assertOk()->assertSee('1 member has not answered · 1 on the way, 0 cannot come');
        $this->withSession($this->other)->get("/unit/recall/$id")->assertForbidden();
        $this->withSession($this->unit)->post("/unit/recall/$id/close");
        $this->actingAs($this->ana)->post("/recall/$id", ['answer' => 'cannot', 'note' => 'x'])->assertSessionHas('error');
    }

    public function test_m1_can_recall_every_unit()
    {
        Mail::fake();
        $this->withSession($this->m1)->post('/unit/recall', ['unit' => '__all__', 'title' => 'Typhoon', 'message' => 'All report', 'confirm' => '1']);
        $this->assertSame(3, DB::table('recall_responses')->count());
        $this->assertNull(DB::table('recalls')->value('unit_name'));
    }
}

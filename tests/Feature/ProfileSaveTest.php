<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SOI save steps delete a member's rows and re-insert them. A failure in
 * the insert must not leave the member with their records deleted.
 */
class ProfileSaveTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('serial_number')->nullable();
            $table->string('status')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('reinlist_personal_info', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_id');
            $table->string('serial_number');
        });

        $this->user = User::forceCreate([
            'name' => 'Member',
            'email' => 'member@example.test',
            'password' => Hash::make('secret-password'),
            'serial_number' => 'SN-1',
            'status' => 'Approved',
        ]);

        DB::table('reinlist_personal_info')->insert(['personnel_id' => 'MEPCOM-SN-1', 'serial_number' => 'SN-1']);
    }

    private function createDependentsTable(bool $withTimestamps): void
    {
        Schema::create('reinlist_dependents', function (Blueprint $table) use ($withTimestamps) {
            $table->id();
            $table->string('personnel_id')->nullable();
            $table->string('serial_number');
            $table->string('name');
            $table->string('relationship');
            $table->date('birthdate');
            if ($withTimestamps) {
                $table->timestamps();
            }
        });

        DB::table('reinlist_dependents')->insert([
            'serial_number' => 'SN-1', 'name' => 'Existing Child', 'relationship' => 'Son', 'birthdate' => '2015-01-01',
        ]);
    }

    private function saveDependents()
    {
        return $this->actingAs($this->user)->postJson('/temp-save/step3', [
            'dependents' => [['name' => 'New Child', 'relationship' => 'Daughter', 'bday' => '2018-05-05']],
        ]);
    }

    public function test_step3_replaces_dependents()
    {
        $this->createDependentsTable(true);

        $this->saveDependents()->assertOk();

        $this->assertSame(['New Child'], DB::table('reinlist_dependents')->pluck('name')->all());
    }

    public function test_step3_keeps_existing_dependents_when_insert_fails()
    {
        // No timestamp columns -> the insert fails after the delete has run.
        $this->createDependentsTable(false);

        $this->saveDependents()->assertStatus(500);

        $this->assertSame(['Existing Child'], DB::table('reinlist_dependents')->pluck('name')->all());
    }
}

<?php

namespace Tests\Feature;

use App\Models\OsaertTrainee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OsaertVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('osaert_trainees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('date_attended')->nullable();
            $table->string('date_ended')->nullable();
            $table->timestamps();
        });
    }

    public function test_year_is_read_from_both_date_formats()
    {
        $this->assertSame('2025', OsaertTrainee::yearOf('07/02/2025'));
        $this->assertSame('2025', OsaertTrainee::yearOf('2025-06-14'));

        $a = OsaertTrainee::create(['name' => 'A', 'date_attended' => '03/02/2024', 'date_ended' => '07/02/2025']);
        $b = OsaertTrainee::create(['name' => 'B', 'date_attended' => '2024-06-10', 'date_ended' => '2025-06-14']);
        OsaertTrainee::create(['name' => 'C', 'date_attended' => '2026-01-15', 'date_ended' => '']);

        $this->assertSame('OSAERT-2025-' . str_pad($b->id, 6, '0', STR_PAD_LEFT), $b->certificate_number);

        // The SQL filter matches both formats (it used RIGHT(col, 4) before).
        $this->assertSame(['A', 'B'], OsaertTrainee::whereYearOf('date_ended', '2025')->orderBy('name')->pluck('name')->all());
        $this->assertSame(['A', 'B'], OsaertTrainee::whereYearOf('date_attended', '2024')->orderBy('name')->pluck('name')->all());
    }

    public function test_lookup_by_certificate_number_redirects_to_the_certificate()
    {
        $t = OsaertTrainee::create(['name' => 'Sample Trainee', 'company' => 'Sample Co.', 'date_attended' => '2025-06-10', 'date_ended' => '2025-06-14']);

        $this->get('/osaert/verify?certificate=' . strtolower($t->certificate_number))
            ->assertRedirect(route('osaert.verify.certificate', $t));

        // Numbers printed before the date fix still verify.
        $this->get('/osaert/verify?certificate=' . urlencode($t->legacy_certificate_number))
            ->assertRedirect(route('osaert.verify.certificate', $t));

        $this->get(route('osaert.verify.certificate', $t))
            ->assertOk()
            ->assertSee('Certificate verified')
            ->assertSee('Sample Trainee')
            ->assertDontSee('Logout');
    }

    public function test_lookup_page_does_not_list_trainees_and_rejects_bad_numbers()
    {
        $t = OsaertTrainee::create(['name' => 'Hidden Person', 'date_ended' => '07/02/2025']);

        $this->get('/osaert/verify')->assertOk()->assertDontSee('Hidden Person');

        $this->get('/osaert/verify?certificate=OSAERT-1999-' . str_pad($t->id, 6, '0', STR_PAD_LEFT))
            ->assertOk()->assertSee('No issued certificate matches')->assertDontSee('Hidden Person');
    }

    public function test_incomplete_training_has_no_certificate()
    {
        $t = OsaertTrainee::create(['name' => 'In Progress', 'date_attended' => '2026-01-15', 'date_ended' => '']);

        $this->get(route('osaert.verify.certificate', $t))->assertNotFound();
    }
}

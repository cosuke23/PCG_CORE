<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service certificates on request (owner request 2026-11-20, App\Support\ServiceCertificates):
 * a member asks for a certificate of service or of assignment, HR issues or
 * declines it, the issued certificate prints with a QR code that opens the
 * public check /verify/certificate/{code}. The details printed are frozen in
 * `snapshot` when issued.
 *
 * up():   creates service_certificates when it does not exist. No existing table changes.
 * down(): drops service_certificates (only this migration's table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_certificates')) {
            return;
        }
        Schema::create('service_certificates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('serial_number', 100)->index();
            $t->string('kind', 20);                          // service | assignment
            $t->string('purpose', 200);
            $t->string('status', 20)->default('pending');    // pending | issued | declined | revoked
            $t->string('code', 16)->nullable()->unique();    // verification code on the printout
            $t->text('snapshot')->nullable();                // JSON of what is printed, frozen at issue
            $t->text('decision_note')->nullable();
            $t->string('decided_by_name', 150)->nullable();
            $t->unsignedBigInteger('decided_by_id')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_certificates');
    }
};

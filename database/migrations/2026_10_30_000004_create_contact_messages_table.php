<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages sent from the public Contact page (read by admins under
 * Admin -> Messages). New table only; rollback drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contact_messages')) Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject', 150)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('new')->index(); // new | read | archived
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

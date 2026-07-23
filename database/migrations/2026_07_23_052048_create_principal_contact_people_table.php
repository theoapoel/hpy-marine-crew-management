<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The people at a principal we actually deal with: crewing, DPA, marine super. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('principal_contact_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_id')->constrained('principals')->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('principal_contact_persons');
    }
};

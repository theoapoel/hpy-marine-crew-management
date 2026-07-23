<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rank'); // Master, Officers, Engineers, Ratings, Catering
            $table->string('nationality')->default('Indonesian');
            $table->string('seaman_book_no')->nullable();
            $table->string('status')->default('Standby'); // Onboard, Standby, Sign Off
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sign_on_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crews');
    }
};

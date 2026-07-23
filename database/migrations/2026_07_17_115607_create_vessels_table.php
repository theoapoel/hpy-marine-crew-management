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
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('Container'); // Container, Bulk Carrier, Tanker
            $table->string('status')->default('In Port'); // At Sea, In Port, Dry Dock
            $table->string('principal')->nullable();
            $table->unsignedSmallInteger('crew_capacity')->default(0);
            $table->string('eta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};

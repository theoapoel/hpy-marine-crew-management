<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gender and date of birth are mandatory on the ERP HPY "Employee" Doctype,
 * so the crew form has to collect them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('nationality');
            $table->date('date_of_birth')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->dropColumn(['gender', 'date_of_birth']);
        });
    }
};

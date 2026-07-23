<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fleet itself lives in ERP HPY (Doctype Vessel), where the link is a custom
 * "principal" field. This column keeps the local placeholder table in step for the
 * dashboard and any code still reading it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->foreignId('principal_id')->nullable()->after('id')->constrained('principals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('principal_id');
        });
    }
};

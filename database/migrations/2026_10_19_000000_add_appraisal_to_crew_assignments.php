<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a crew member did on each contract: the Appraisal column of the sea service
 * record on Crew Master, rated once the berth is over (or while they are still aboard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_assignments', function (Blueprint $table) {
            $table->string('appraisal')->nullable()->after('sign_off_reason');
            $table->text('appraisal_notes')->nullable()->after('appraisal');
        });
    }

    public function down(): void
    {
        Schema::table('crew_assignments', function (Blueprint $table) {
            $table->dropColumn(['appraisal', 'appraisal_notes']);
        });
    }
};

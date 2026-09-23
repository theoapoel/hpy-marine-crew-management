<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COC types are master data in ERP HPY now ("COC Type"), so the column can no longer
 * be an enum of the list the app shipped with — a type added in the master would be
 * refused by the database. Existing values are kept as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_candidates', function (Blueprint $table) {
            $table->string('coc_type', 140)->nullable()->default('None')->change();
        });
    }

    public function down(): void
    {
        Schema::table('crew_candidates', function (Blueprint $table) {
            $table->enum('coc_type', [
                'ANT-I', 'ANT-II', 'ANT-III', 'ANT-IV', 'ANT-V', 'ANT-D',
                'ATT-I', 'ATT-II', 'ATT-III', 'ATT-IV', 'ATT-V', 'ATT-D',
                'Rating', 'None',
            ])->default('None')->change();
        });
    }
};

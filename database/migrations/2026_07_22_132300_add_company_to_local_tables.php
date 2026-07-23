<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the app shows belongs to the company picked in the header. Candidates
 * were the one local table without that column, so they could leak across companies.
 *
 * Existing rows are handed to the default company from config, since they were all
 * entered before the split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_candidates', function (Blueprint $table) {
            $table->string('company')->nullable()->after('candidate_code')->index();
        });

        if ($company = config('services.erpnext.company')) {
            DB::table('crew_candidates')->whereNull('company')->update(['company' => $company]);
            DB::table('crew_applications')->whereNull('company')->update(['company' => $company]);
            DB::table('crew_assignments')->whereNull('company')->update(['company' => $company]);
        }
    }

    public function down(): void
    {
        Schema::table('crew_candidates', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }
};

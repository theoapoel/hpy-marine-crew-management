<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Pipeline and Crew Assignment.
 *
 * An application is one candidate going after one seat: it walks the stages until it
 * is hired or rejected. A hired application produces an assignment — the actual berth
 * on a vessel, with its sign on / sign off dates.
 *
 * Vessels, ranks and employees live in ERP HPY, so those columns hold ERP HPY document
 * names rather than foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('application_code')->unique(); // APP-YYYY-XXXXX

            $table->foreignId('crew_candidate_id')->constrained('crew_candidates')->cascadeOnDelete();

            // What is being applied for
            $table->string('applied_rank')->nullable();
            $table->string('vessel')->nullable();   // ERP HPY Vessel
            $table->string('company')->nullable();  // ERP HPY Company
            $table->string('principal')->nullable();

            $table->enum('stage', [
                'applied', 'screening', 'interview', 'mcu', 'offer', 'hired', 'rejected', 'withdrawn',
            ])->default('applied');

            // Stage bookkeeping
            $table->date('applied_date');
            $table->date('screening_date')->nullable();
            $table->date('interview_date')->nullable();
            $table->unsignedTinyInteger('interview_score')->nullable(); // 1..10
            $table->text('interview_notes')->nullable();
            $table->date('mcu_date')->nullable();
            $table->enum('mcu_result', ['pending', 'fit', 'unfit'])->nullable();
            $table->date('offer_date')->nullable();
            $table->decimal('offered_salary', 15, 2)->nullable();
            $table->string('offered_salary_currency')->default('IDR');
            $table->date('decision_date')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stage', 'applied_rank']);
        });

        Schema::create('crew_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('assignment_code')->unique(); // ASG-YYYY-XXXXX

            $table->foreignId('crew_candidate_id')->nullable()->constrained('crew_candidates')->nullOnDelete();
            $table->foreignId('crew_application_id')->nullable()->constrained('crew_applications')->nullOnDelete();
            $table->string('employee_id')->nullable();  // ERP HPY Employee
            $table->string('crew_name');                // snapshot, so history survives deletions

            $table->string('vessel')->nullable();       // ERP HPY Vessel
            $table->string('rank')->nullable();         // ERP HPY Rank
            $table->string('company')->nullable();

            $table->enum('status', ['planned', 'onboard', 'signed_off', 'cancelled'])->default('planned');

            $table->date('planned_sign_on_date')->nullable();
            $table->date('sign_on_date')->nullable();
            $table->string('sign_on_port')->nullable();
            $table->unsignedSmallInteger('contract_months')->nullable();
            $table->date('planned_sign_off_date')->nullable();
            $table->date('sign_off_date')->nullable();
            $table->string('sign_off_port')->nullable();
            $table->string('sign_off_reason')->nullable();

            $table->decimal('wage', 15, 2)->nullable();
            $table->string('wage_currency')->default('IDR');

            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'vessel']);
            $table->index('planned_sign_on_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_assignments');
        Schema::dropIfExists('crew_applications');
    }
};

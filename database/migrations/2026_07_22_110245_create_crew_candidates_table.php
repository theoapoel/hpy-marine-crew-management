<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate Pool: the lifetime database of seafarers who ever applied to HPY.
 * A candidate is not an employee yet — promotion creates the Employee in ERP HPY
 * and stores its name in linked_employee_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('candidate_code')->unique(); // CND-YYYY-XXXXX

            // Personal
            $table->string('full_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->default('Indonesia');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('religion')->nullable();
            $table->string('blood_type')->nullable();

            // Identity
            $table->string('nik', 16)->nullable()->unique();
            $table->string('passport_no')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('passport_issue_place')->nullable();
            $table->string('seaman_book_no')->nullable()->unique();
            $table->date('seaman_book_expiry')->nullable();
            $table->string('seaman_book_issue_place')->nullable();

            // Contact
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Professional
            $table->string('applied_rank')->nullable();
            $table->string('preferred_vessel_type')->nullable();
            $table->unsignedInteger('years_of_experience')->default(0);
            $table->string('last_vessel_name')->nullable();
            $table->string('last_rank')->nullable();
            $table->date('last_sign_off_date')->nullable();

            // Certification
            $table->enum('coc_type', [
                'ANT-I', 'ANT-II', 'ANT-III', 'ANT-IV', 'ANT-V', 'ANT-D',
                'ATT-I', 'ATT-II', 'ATT-III', 'ATT-IV', 'ATT-V', 'ATT-D',
                'Rating', 'None',
            ])->default('None');
            $table->string('coc_number')->nullable();
            $table->date('coc_expiry')->nullable();
            $table->json('cop_certificates')->nullable(); // [{name, number, expiry}]
            $table->date('mcu_expiry')->nullable();
            $table->date('endc_expiry')->nullable();

            // Source tracking
            $table->enum('source', [
                'walk_in', 'website', 'referral', 'agency', 'job_board',
                'school', 'alumni', 'union', 'head_hunter', 'other',
            ])->default('walk_in');
            $table->string('source_detail')->nullable();
            $table->string('referred_by_employee_id')->nullable(); // ERP HPY Employee name
            $table->string('source_agency_id')->nullable();        // ERP HPY Supplier name
            $table->string('source_school')->nullable();
            $table->decimal('source_cost', 15, 2)->nullable();

            // Status
            $table->enum('status', [
                'applicant', 'in_process', 'employed', 'on_leave', 'blacklist', 'retired',
            ])->default('applicant');
            $table->date('availability_date')->nullable();
            $table->decimal('expected_salary', 15, 2)->nullable();
            $table->string('expected_salary_currency')->default('IDR');

            // Linkage — set once the candidate becomes an ERP HPY Employee
            $table->string('linked_employee_id')->nullable();

            // Attachments
            $table->string('photo_path')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('id_scan_path')->nullable();
            $table->string('seaman_book_scan_path')->nullable();
            $table->string('coc_scan_path')->nullable();
            $table->json('other_documents')->nullable();

            // Meta
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable(); // ERP HPY user id (email)
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'applied_rank']);
            $table->index('source');
            $table->index('availability_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_candidates');
    }
};

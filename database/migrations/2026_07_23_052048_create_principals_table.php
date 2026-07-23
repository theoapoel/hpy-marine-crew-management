<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Principals: the shipowners, managers and charterers whose vessels we man.
 * One principal owns many vessels, and carries the commercial terms the manning
 * agreement runs on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('principals', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('principal_code')->unique(); // PRN-0001
            $table->string('company')->nullable()->index(); // our own company, per the header

            // Basic info
            $table->string('principal_name');
            $table->string('legal_entity_name')->nullable();
            $table->enum('principal_type', ['shipowner', 'ship_manager', 'charterer', 'operator'])->default('shipowner');
            $table->string('country')->default('Indonesia');

            // Address & contact
            $table->text('head_office_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Business terms
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('contract_type', ['exclusive', 'non_exclusive'])->nullable();
            $table->enum('manning_fee_type', ['per_crew', 'percentage', 'flat_monthly'])->nullable();
            $table->decimal('manning_fee_amount', 15, 2)->nullable();
            $table->string('currency')->default('IDR');
            $table->string('payment_terms')->nullable();

            // Compliance
            $table->string('p_and_i_club')->nullable();
            $table->string('wage_scale_reference')->nullable();

            // Financial
            $table->text('billing_address')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('bank_details')->nullable();

            $table->enum('status', ['prospect', 'active', 'on_hold', 'terminated'])->default('active');
            $table->text('notes')->nullable();

            // ERP HPY mirror
            $table->string('erpnext_name')->nullable();
            $table->timestamp('erpnext_synced_at')->nullable();
            $table->string('erpnext_sync_status')->default('pending'); // pending|synced|failed
            $table->text('erpnext_sync_error')->nullable();
            $table->string('erpnext_hash')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'principal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('principals');
    }
};

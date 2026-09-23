<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A principal can have several offices and several fee arrangements (per crew for
 * officers, flat monthly for a charter, in different currencies), so both become
 * rows. The single columns stay, mirrored from the first row, for search and ERP HPY.
 * WeChat joins WhatsApp for principals in China and Hong Kong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('principals', function (Blueprint $table) {
            $table->json('addresses')->nullable()->after('website');
            $table->json('manning_fees')->nullable()->after('currency');
            $table->string('wechat')->nullable()->after('fax');
        });

        Schema::table('principal_contact_persons', function (Blueprint $table) {
            $table->string('wechat')->nullable()->after('whatsapp');
        });

        // Carry what each principal already has into the first row.
        DB::table('principals')->orderBy('id')->each(function ($p) {
            $address = array_filter([
                'address_type' => 'Head Office',
                'address' => $p->head_office_address, 'city' => $p->city, 'province' => $p->province,
                'postal_code' => $p->postal_code, 'phone' => $p->phone, 'fax' => $p->fax, 'email' => $p->email,
            ], fn ($v) => $v !== null && $v !== '');
            $fee = array_filter([
                'fee_type' => $p->manning_fee_type, 'amount' => $p->manning_fee_amount, 'currency' => $p->currency,
            ], fn ($v) => $v !== null && $v !== '');

            DB::table('principals')->where('id', $p->id)->update([
                'addresses' => count($address) > 1 ? json_encode([$address]) : null,
                'manning_fees' => isset($fee['fee_type']) || isset($fee['amount']) ? json_encode([$fee]) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('principal_contact_persons', fn (Blueprint $table) => $table->dropColumn('wechat'));
        Schema::table('principals', fn (Blueprint $table) => $table->dropColumn(['addresses', 'manning_fees', 'wechat']));
    }
};

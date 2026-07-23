<?php

namespace App\Http\Requests;

use App\Models\Principal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PrincipalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->principal ? 'principals.update' : 'principals.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Basic info
            'principal_name' => ['required', 'string', 'max:255'],
            'legal_entity_name' => ['nullable', 'string', 'max:255'],
            'principal_type' => ['required', Rule::in(Principal::TYPES)],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(Principal::STATUSES)],

            // Address & contact
            'head_office_address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:150'],

            // Business terms
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after:contract_start_date'],
            'contract_type' => ['nullable', Rule::in(Principal::CONTRACT_TYPES)],
            'manning_fee_type' => ['nullable', Rule::in(Principal::FEE_TYPES)],
            'manning_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:5'],
            'payment_terms' => ['nullable', 'string', 'max:100'],

            // Compliance
            'p_and_i_club' => ['nullable', 'string', 'max:150'],
            'wage_scale_reference' => ['nullable', 'string', 'max:150'],

            // Financial
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'bank_details' => ['nullable', 'string', 'max:1000'],

            'notes' => ['nullable', 'string', 'max:5000'],

            // Contact persons
            'contact_persons' => ['required', 'array', 'min:1'],
            'contact_persons.*.name' => ['nullable', 'string', 'max:150'],
            'contact_persons.*.position' => ['nullable', 'string', 'max:100'],
            'contact_persons.*.email' => ['nullable', 'email', 'max:150'],
            'contact_persons.*.phone' => ['nullable', 'string', 'max:50'],
            'contact_persons.*.whatsapp' => ['nullable', 'string', 'max:50'],
            'contact_persons.*.is_primary' => ['nullable', 'boolean'],
            'contact_persons.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A principal without a named primary contact is a phone number nobody answers,
     * so require at least one filled row and exactly one primary.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rows = collect($this->input('contact_persons', []))
                    ->filter(fn ($row) => filled($row['name'] ?? null));

                if ($rows->isEmpty()) {
                    $validator->errors()->add('contact_persons', 'Isi minimal satu contact person.');

                    return;
                }

                if ($rows->where('is_primary', '1')->isEmpty() && $rows->where('is_primary', true)->isEmpty()) {
                    $validator->errors()->add('contact_persons', 'Tandai satu contact person sebagai primary.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contract_end_date.after' => 'Kontrak berakhir harus setelah tanggal mulai.',
        ];
    }
}

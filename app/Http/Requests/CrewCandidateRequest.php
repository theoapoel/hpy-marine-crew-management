<?php

namespace App\Http\Requests;

use App\Models\CrewCandidate;
use App\Support\CocTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validation for the Candidate Pool form.
 *
 * Expiry dates are deliberately NOT rejected when they are in the past: an expired
 * document is a fact about the candidate, not a typo. The form flags them instead
 * (see the warnings shown on the detail page).
 */
class CrewCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->candidate ? 'candidates.update' : 'candidates.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->candidate?->id;

        return [
            // Personal
            'full_name' => ['required', 'string', 'max:150'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(CrewCandidate::GENDERS)],
            'date_of_birth' => ['required', 'date', 'before:today', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::in(CrewCandidate::MARITAL_STATUSES)],
            'religion' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:5'],

            // Identity
            'nik' => ['nullable', 'digits:16', Rule::unique('crew_candidates', 'nik')->ignore($id)->whereNull('deleted_at')],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'passport_expiry' => ['nullable', 'date'],
            'passport_issue_place' => ['nullable', 'string', 'max:100'],
            'seaman_book_no' => ['nullable', 'string', 'max:50', Rule::unique('crew_candidates', 'seaman_book_no')->ignore($id)->whereNull('deleted_at')],
            'seaman_book_expiry' => ['nullable', 'date'],
            'seaman_book_issue_place' => ['nullable', 'string', 'max:100'],

            // Contact
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('crew_candidates', 'email')->ignore($id)->whereNull('deleted_at')],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            // Professional
            'applied_rank' => ['nullable', 'string', 'max:100'],
            'preferred_vessel_type' => ['nullable', 'string', 'max:100'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'last_vessel_name' => ['nullable', 'string', 'max:150'],
            'last_rank' => ['nullable', 'string', 'max:100'],
            'last_sign_off_date' => ['nullable', 'date'],

            // Certification
            // Types come from the ERP HPY "COC Type" master; a value stored before a type
            // was retired stays valid on its own record.
            'coc_type' => ['nullable', 'string', 'max:140', Rule::in(array_filter([
                ...app(CocTypes::class)->all(),
                $this->route('candidate')?->coc_type,
            ]))],
            'coc_number' => ['nullable', 'string', 'max:100'],
            'coc_expiry' => ['nullable', 'date'],
            'cop_certificates' => ['nullable', 'array'],
            'cop_certificates.*.name' => ['nullable', 'string', 'max:150'],
            'cop_certificates.*.number' => ['nullable', 'string', 'max:100'],
            'cop_certificates.*.expiry' => ['nullable', 'date'],
            'mcu_expiry' => ['nullable', 'date'],
            'endc_expiry' => ['nullable', 'date'],

            // Source
            'source' => ['required', Rule::in(CrewCandidate::SOURCES)],
            'source_detail' => ['nullable', 'string', 'max:150'],
            'referred_by_employee_id' => ['nullable', 'string', 'max:150', Rule::requiredIf($this->input('source') === 'referral')],
            'source_agency_id' => ['nullable', 'string', 'max:150', Rule::requiredIf($this->input('source') === 'agency')],
            'source_school' => ['nullable', 'string', 'max:150', Rule::requiredIf($this->input('source') === 'school')],
            'source_cost' => ['nullable', 'numeric', 'min:0'],

            // Status
            'status' => ['required', Rule::in(CrewCandidate::STATUSES)],
            'availability_date' => ['nullable', 'date'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'expected_salary_currency' => ['nullable', 'string', 'max:5'],

            // Attachments
            'photo' => ['nullable', 'image', 'max:5120'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'id_scan' => ['nullable', 'file', 'max:10240'],
            'seaman_book_scan' => ['nullable', 'file', 'max:10240'],
            'coc_scan' => ['nullable', 'file', 'max:10240'],
            'other_documents.*' => ['nullable', 'file', 'max:10240'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon harus format Indonesia, mis. 081234567890.',
            'whatsapp.regex' => 'Nomor WhatsApp harus format Indonesia, mis. 081234567890.',
            'nik.digits' => 'NIK harus 16 digit.',
            'date_of_birth.before_or_equal' => 'Umur kandidat minimal 18 tahun.',
            'referred_by_employee_id.required' => 'Isi siapa yang mereferensikan kandidat ini.',
            'source_agency_id.required' => 'Pilih agency asal kandidat.',
            'source_school.required' => 'Isi nama sekolah/akademi asal kandidat.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // The form collects one name; split it so ERP HPY gets first/last on promotion.
        if ($this->filled('full_name') && ! $this->filled('first_name')) {
            $parts = preg_split('/\s+/', trim((string) $this->input('full_name')), 2);

            $this->merge(['first_name' => $parts[0] ?? null, 'last_name' => $parts[1] ?? null]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\CrewDocumentTypes;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Document Types: the list behind the Type dropdown of crew certificates (passport,
 * seaman book, COC, medical, …). The records live in the ERP HPY "Crew Certificate
 * Type" master; this page adds them and switches them on or off without desk access.
 */
class CrewDocumentTypeController extends Controller
{
    public function __construct(private readonly CrewDocumentTypes $types) {}

    public function index()
    {
        return view('crew.document-types', [
            'editable' => $this->types->editable(),
            'rows' => rescue(fn () => $this->types->rows(), []),
            'shipped' => $this->types->names(),
            'categories' => CrewDocumentTypes::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'certificate_name' => ['required', 'string', 'max:140'],
            'category' => ['nullable', Rule::in(CrewDocumentTypes::CATEGORIES)],
            'validity_months' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        if (! $this->types->editable()) {
            return back()->withErrors(['certificate_name' => 'Master belum ada di ERP HPY. Jalankan php artisan erp:sync-crew-fields dulu.']);
        }

        $name = trim($data['certificate_name']);

        try {
            $this->types->create($name, ['category' => $data['category'] ?? null, 'validity_months' => $data['validity_months'] ?? null]);
        } catch (RequestException $e) {
            report($e);

            return back()->withInput()->withErrors(['certificate_name' => 'ERP HPY menolak (status '.$e->response->status().').']);
        }

        return back()->with('success', "Document type \"{$name}\" ditambahkan.");
    }

    public function update(Request $request, string $type)
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(CrewDocumentTypes::CATEGORIES)],
            'validity_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_active' => ['required', 'boolean'],
        ]);

        try {
            $this->types->update($type, [
                'category' => $data['category'] ?? '',
                'validity_months' => $data['validity_months'] ?? 0,
                'is_active' => (int) $data['is_active'],
            ]);
        } catch (RequestException $e) {
            report($e);

            return back()->withErrors(['certificate_name' => "ERP HPY menolak perubahan {$type} (status {$e->response->status()})."]);
        }

        return back()->with('success', "{$type} diperbarui.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncCrewFields;
use App\Repositories\CrewDocumentRepository;
use App\Services\Erpnext\ErpnextOptions;
use App\Support\CrewDocumentTypes;
use Illuminate\Http\Request;

/**
 * Documents menu: one screen over every crew certificate in the company, with the
 * sidebar entries (Certificates / Passport / Seaman Book / Medical / Expiring) as
 * preset views of the same list.
 */
class DocumentController extends Controller
{
    /** Sidebar entry => certificate type it pins the list to. */
    public const VIEWS = [
        'certificates' => null,
        'passport' => 'Passport',
        'seaman-book' => 'Seaman Book',
        'medical' => 'Medical Certificate',
        'expiring' => null,
    ];

    public function __construct(
        private readonly CrewDocumentRepository $documents,
        private readonly ErpnextOptions $options,
    ) {}

    public function index(Request $request, string $view = 'certificates')
    {
        abort_unless(array_key_exists($view, self::VIEWS), 404);

        $filters = array_filter([
            'type' => self::VIEWS[$view] ?? $request->query('type'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'expiring_in' => $view === 'expiring' ? (int) $request->query('days', 60) : null,
        ], fn ($value) => $value !== null && $value !== '');

        return view('documents.index', [
            'view' => $view,
            'documents' => $this->documents->all($filters),
            'filters' => $filters,
            'types' => $this->types(),
            'statuses' => SyncCrewFields::CERTIFICATE_STATUSES,
            'title' => $this->title($view),
        ]);
    }

    /** Certificate types from the ERP HPY master; the seed list if it cannot be read. */
    private function types(): array
    {
        return app(CrewDocumentTypes::class)->names();
    }

    private function title(string $view): string
    {
        return match ($view) {
            'passport' => 'Passport',
            'seaman-book' => 'Seaman Book',
            'medical' => 'Medical',
            'expiring' => 'Expiring Documents',
            default => 'Certificates',
        };
    }
}

<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CrewApplicationController;
use App\Http\Controllers\CrewAssignmentController;
use App\Http\Controllers\CrewCandidateController;
use App\Http\Controllers\CrewController;
use App\Http\Controllers\CrewDocumentTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ErpFileController;
use App\Http\Controllers\PrincipalController;
use App\Http\Controllers\VesselController;
use App\Http\Controllers\VesselProfitabilityController;
use Illuminate\Support\Facades\Route;

/**
 * Brand images, served from public/images through a url that has no file of its own.
 *
 * The built-in dev server treats a request whose path matches a real file as the
 * application's base path, which breaks every generated url on the page — so the
 * images are addressed as /brand/... instead. Outside the auth middleware, since the
 * login screen needs them.
 */
Route::get('brand/{file}', function (string $file) {
    abort_unless(preg_match('/^[a-z0-9._-]+\.(png|jpg|jpeg|svg|webp)$/i', $file), 404);

    $path = public_path('images/' . $file);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
})->name('brand.image');

Route::get('login', [LoginController::class, 'show'])->name('login');
Route::post('login', [LoginController::class, 'store']);
Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

// Logged in, but not yet working inside a company.
Route::middleware('erpnext')->group(function () {
    Route::get('company', [CompanyController::class, 'show'])->name('company.select');
    Route::post('company', [CompanyController::class, 'store'])->name('company.store');

    // Files kept in ERP HPY (crew photos, certificate scans, company logos) are served
    // through here; the company chooser needs them before a company is picked.
    Route::get('erp-file', [ErpFileController::class, 'show'])->name('erp.file');
});

Route::middleware(['erpnext', 'erpnext.company'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // New certificate types, added from the crew form (Crew Certificate Type master).
    // Document Types: the Crew Certificate Type master in ERP HPY.
    Route::get('crew/document-types', [CrewDocumentTypeController::class, 'index'])->name('crew.document-types.index');
    Route::post('crew/document-types', [CrewDocumentTypeController::class, 'store'])->name('crew.document-types.store');
    Route::patch('crew/document-types/{type}', [CrewDocumentTypeController::class, 'update'])->name('crew.document-types.update')->where('type', '.*');
    Route::post('crew/certificate-types', [CrewController::class, 'storeCertificateType'])->name('crew.certificate-types.store');
    Route::resource('crew', CrewController::class);

    // Candidate Pool — lifetime seafarer database, kept locally until promotion.
    Route::get('candidates/export', [CrewCandidateController::class, 'export'])->name('candidates.export');
    Route::get('candidates/duplicates', [CrewCandidateController::class, 'duplicates'])->name('candidates.duplicates');
    Route::post('candidates/bulk-status', [CrewCandidateController::class, 'bulkStatus'])->name('candidates.bulk-status');
    Route::post('candidates/coc-types', [CrewCandidateController::class, 'storeCocType'])->name('candidates.coc-types.store');
    Route::post('candidates/{candidate}/promote', [CrewCandidateController::class, 'promote'])->name('candidates.promote');
    Route::resource('candidates', CrewCandidateController::class)->parameters(['candidates' => 'candidate']);

    // Recruitment Pipeline
    Route::post('applications/{application}/advance', [CrewApplicationController::class, 'advance'])->name('applications.advance');
    Route::post('applications/{application}/close', [CrewApplicationController::class, 'close'])->name('applications.close');
    Route::resource('applications', CrewApplicationController::class);

    // Crew Assignment, with the sign on / sign off desks over the same records.
    Route::get('sign-on', [CrewAssignmentController::class, 'signOnDesk'])->name('assignments.sign-on');
    Route::get('sign-off', [CrewAssignmentController::class, 'signOffDesk'])->name('assignments.sign-off');
    Route::post('assignments/{assignment}/sign-on', [CrewAssignmentController::class, 'signOn'])->name('assignments.do-sign-on');
    Route::post('assignments/{assignment}/sign-off', [CrewAssignmentController::class, 'signOff'])->name('assignments.do-sign-off');
    Route::post('assignments/{assignment}/link', [CrewAssignmentController::class, 'link'])->name('assignments.link');
    Route::resource('assignments', CrewAssignmentController::class);

    // Principals — the owners whose vessels we crew.
    Route::post('principals/{principal}/sync', [PrincipalController::class, 'sync'])->name('principals.sync');
    Route::resource('principals', PrincipalController::class);

    // Fleet, kept in the ERP HPY "Vessel" doctype.
    Route::post('vessels/types', [VesselController::class, 'storeType'])->name('vessels.types.store');
    Route::post('vessels/certificate-types', [VesselController::class, 'storeCertificateType'])->name('vessels.certificate-types.store');
    Route::resource('vessels', VesselController::class)->except('show');
    Route::get('vessels/{vessel}', [VesselController::class, 'show'])->name('vessels.show');

    // Vessel Profitability — read through the Project doctype, one project per
    // contract/charter, linked to the ship by Project.vessel.
    Route::get('profitability', [VesselProfitabilityController::class, 'index'])->name('profitability.index');
    Route::get('profitability/vessel/{vessel}', [VesselProfitabilityController::class, 'vessel'])->name('profitability.vessel');
    Route::get('profitability/project/{project}', [VesselProfitabilityController::class, 'project'])->name('profitability.project');

    // Accounting — ERP HPY's own financial reports, rendered here.
    Route::get('accounting/{report}', [AccountingController::class, 'show'])
        ->whereIn('report', array_keys(App\Services\Erpnext\FinancialReports::REPORTS))
        ->name('accounting');

    Route::get('documents/{view}', [DocumentController::class, 'index'])
        ->whereIn('view', array_keys(DocumentController::VIEWS))
        ->name('documents');
});

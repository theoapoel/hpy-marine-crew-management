<?php

namespace App\Http\Controllers;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a file stored in ERP HPY (crew photos, certificate scans) through the app.
 *
 * Uploads land in ERP HPY's private folder, which only answers to a logged-in ERP HPY
 * session. The browser has no such cookie — it is logged into this app — so linking
 * straight at the file returns 403. Here the request is made with the session's own
 * ERP HPY credentials and handed back to the browser.
 */
class ErpFileController extends Controller
{
    public function __construct(private readonly ErpnextClient $erpnext)
    {
    }

    public function show(Request $request): Response
    {
        $path = (string) $request->query('path');

        // Only ever serve ERP HPY's own file folders, never an arbitrary url, and never
        // a path that climbs back out of them.
        abort_unless(preg_match('#^/(private/)?files/[^?\#]+$#', $path) === 1, 404);
        abort_if(str_contains($path, '..'), 404);

        $file = $this->erpnext->download($path);

        return response($file['contents'], 200, [
            'Content-Type' => $file['content_type'],
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

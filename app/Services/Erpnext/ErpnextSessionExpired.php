<?php

namespace App\Services\Erpnext;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thrown when the logged-in user's ERP HPY session (sid) is no longer valid.
 * Rendered as a redirect back to the login form.
 */
class ErpnextSessionExpired extends \RuntimeException
{
    public function render(Request $request): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'usr' => 'Your ERP HPY session has expired. Please sign in again.',
        ]);
    }
}

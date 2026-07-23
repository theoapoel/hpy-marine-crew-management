<?php

namespace App\Services\Erpnext;

/**
 * Thrown when the logged-in user's ERP HPY session (sid) is no longer valid.
 * Rendered as a redirect back to the login form.
 */
class ErpnextSessionExpired extends \RuntimeException
{
    public function render(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'usr' => 'Sesi ERP HPY Anda sudah berakhir. Silakan login kembali.',
        ]);
    }
}

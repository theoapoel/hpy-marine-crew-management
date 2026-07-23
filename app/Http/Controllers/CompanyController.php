<?php

namespace App\Http\Controllers;

use App\Services\Erpnext\ErpnextClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The instance holds more than one company, so every session works inside one of
 * them: picked right after login, switchable from the navbar.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly ErpnextClient $erpnext)
    {
    }

    /** Session key holding the company list, so the navbar need not re-fetch it. */
    public const LIST_KEY = 'erpnext_companies';

    public function show(Request $request)
    {
        $companies = $this->companies($request);

        // Nothing to choose between — take the only one and move on.
        if (count($companies) === 1) {
            $request->session()->put(ErpnextClient::COMPANY_KEY, $companies[0]);

            return redirect()->intended(route('dashboard'));
        }

        return view('auth.company', [
            'companies' => $companies,
            'profiles' => $this->erpnext->companyProfiles(),
            'current' => $request->session()->get(ErpnextClient::COMPANY_KEY),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = (string) $request->input('company');

        if (! in_array($company, $this->companies($request), true)) {
            throw ValidationException::withMessages([
                'company' => 'Company tidak dikenal di ERP HPY.',
            ]);
        }

        $request->session()->put(ErpnextClient::COMPANY_KEY, $company);

        // Switching company changes what every page shows; go back where we came from.
        return $request->filled('redirect_to')
            ? redirect()->to($request->input('redirect_to'))
            : redirect()->intended(route('dashboard'));
    }

    /**
     * Companies from ERP HPY, remembered for the session so the navbar switcher
     * does not hit the API on every page.
     *
     * @return array<int, string>
     */
    private function companies(Request $request): array
    {
        $companies = $this->erpnext->companies();

        $request->session()->put(self::LIST_KEY, $companies);

        return $companies;
    }
}

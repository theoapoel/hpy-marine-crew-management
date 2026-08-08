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

            return redirect()->to($this->intendedTarget($request));
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
            : redirect()->to($this->intendedTarget($request));
    }

    /**
     * Where to land after a company is picked: the page the visitor was originally
     * after, or the dashboard.
     *
     * A session that ran out while the chooser itself was on screen leaves /company
     * as the remembered page — following that would only show the chooser again, so
     * the auth pages are skipped over.
     */
    private function intendedTarget(Request $request): string
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = rtrim((string) parse_url($intended, PHP_URL_PATH), '/');

        $skip = [
            rtrim(parse_url(route('company.select'), PHP_URL_PATH), '/'),
            rtrim(parse_url(route('login'), PHP_URL_PATH), '/'),
        ];

        if ($intended === '' || in_array($path, $skip, true)) {
            return route('dashboard');
        }

        return $intended;
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

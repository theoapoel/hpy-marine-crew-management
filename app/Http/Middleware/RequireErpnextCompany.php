<?php

namespace App\Http\Middleware;

use App\Services\Erpnext\ErpnextClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every page shows data for one company, so one must be picked first. */
class RequireErpnextCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(ErpnextClient::COMPANY_KEY)) {
            return redirect()->guest(route('company.select'));
        }

        return $next($request);
    }
}

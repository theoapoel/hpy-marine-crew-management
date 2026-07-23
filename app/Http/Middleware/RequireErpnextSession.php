<?php

namespace App\Http\Middleware;

use App\Services\Erpnext\ErpnextClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks the app until the visitor has an ERP HPY session. */
class RequireErpnextSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(ErpnextClient::SESSION_KEY . '.sid')) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}

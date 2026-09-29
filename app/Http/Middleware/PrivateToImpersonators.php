<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps private conversations out of reach while an admin is logged in as someone else.
 */
class PrivateToImpersonators
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('impersonate')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Conversațiile sunt private.');
        }

        return redirect('/friends')->with('error', 'Conversațiile sunt private și nu pot fi deschise în modul impersonare.');
    }
}

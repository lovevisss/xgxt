<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCasAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cas.enabled')) {
            return $next($request);
        }

        if ($request->session()->has(config('cas.session_key'))) {
            return $next($request);
        }

        $returnUrl = '/'.ltrim($request->getRequestUri(), '/');

        return redirect()->route((string) config('cas.routes.names.login'), ['returnUrl' => $returnUrl]);
    }
}

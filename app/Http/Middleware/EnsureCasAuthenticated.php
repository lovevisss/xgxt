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

        if (session()->has(config('cas.session_key'))) {
            return $next($request);
        }

        $path = '/'.ltrim($request->getRequestUri(), '/');

        return redirect()->route('cas.login', [
            'returnUrl' => $path,
        ]);
    }
}


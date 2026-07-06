<?php

namespace Zufedfc\LaravelCas\Http\Middleware;

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

        return redirect()->route($this->routeName('login'), ['returnUrl' => $returnUrl]);
    }

    private function routeName(string $route): string
    {
        return (string) config("cas.routes.names.{$route}");
    }
}

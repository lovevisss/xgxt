<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCasAuthenticated
{
    public function __construct(private readonly AuthFactory $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cas.enabled')) {
            return $next($request);
        }

        if ($request->session()->has(config('cas.session_key'))) {
            return $next($request);
        }

        $guard = $this->auth->guard((string) config('cas.guard', 'web'));
        if ($guard->check()) {
            $username = data_get($guard->user(), 'cas_username');
            if (filled($username)) {
                $request->session()->put(config('cas.session_key'), [
                    'user' => (string) $username,
                    'recovered_at' => now()->toIso8601String(),
                ]);

                return $next($request);
            }
        }

        $returnUrl = '/'.ltrim($request->getRequestUri(), '/');

        return redirect()->route((string) config('cas.routes.names.login'), ['returnUrl' => $returnUrl]);
    }
}

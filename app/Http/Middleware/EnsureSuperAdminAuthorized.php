<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cas.enabled')) {
            return $next($request);
        }

        $user = CurrentUser::get();

        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('*/data*')) {
            return response()->json([
                'message' => '仅超级管理员可访问该资源。',
            ], 403);
        }

        return response()->view('forbidden', [
            'message' => '仅超级管理员可访问该页面。',
        ], 403);
    }
}


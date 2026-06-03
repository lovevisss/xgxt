<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cas.enabled')) {
            return $next($request);
        }

        $user = CurrentUser::get();

        if ($user && ($user->isAdmin() || $user->isCounselor())) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('*/data*')) {
            return response()->json([
                'message' => '当前账号不是管理员，无法访问该资源。',
            ], 403);
        }

        return response()->view('forbidden', [
            'message' => '当前账号不是管理员，无法访问该页面。',
        ], 403);
    }
}

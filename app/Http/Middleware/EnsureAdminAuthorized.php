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

        if ($user?->isInactiveStaffMember()) {
            return $request->expectsJson()
                ? response()->json(['message' => '当前账号已不在在职教职工目录中。'], 403)
                : response()->view('forbidden', ['message' => '当前账号已不在在职教职工目录中。'], 403);
        }
        $capabilities = app(\App\Services\StudentAccess::class)->capabilities();
        $studentRoute = $request->is('students', 'students/*', 'graduates', 'graduates/*');
        $familyRoute = $request->is('student-families', 'student-families/*');
        $counselorRoute = $request->is('counselors', 'counselors/*');
        $allowed = match (true) {
            $studentRoute => $request->isMethod('GET') ? $capabilities['students'] : $capabilities['editStudents'],
            $familyRoute => $request->isMethod('GET') ? $capabilities['students'] : $capabilities['editFamilies'],
            $counselorRoute => $capabilities['counselors'],
            $request->is('admin', 'admin/*') => $capabilities['systemAdmin'],
            default => $capabilities['businessAdmin'],
        };
        if ($user && $allowed) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('*/data*')) {
            return response()->json([
                'message' => '当前账号没有此资源的访问权限。',
            ], 403);
        }

        return response()->view('forbidden', [
            'message' => '当前账号没有此页面的访问权限。',
        ], 403);
    }
}

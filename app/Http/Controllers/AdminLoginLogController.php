<?php

namespace App\Http\Controllers;

use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLoginLogController extends Controller
{
    public function page()
    {
        return view('admin-login-logs');
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = UserLoginLog::query()
            ->select(['id', 'user_id', 'cas_username', 'name', 'logged_in_at', 'ip_address', 'user_agent'])
            ->latest('logged_in_at')
            ->latest('id');

        $keyword = trim((string) ($validated['q'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->where('cas_username', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        return response()->json($query->paginate(20));
    }
}

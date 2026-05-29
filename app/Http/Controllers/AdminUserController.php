<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function page()
    {
        return view('admin-users');
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'cas_username', 'email', 'role', 'dwbm', 'dwmc', 'created_at'])
            ->orderByRaw("CASE role WHEN 'super_admin' THEN 0 WHEN 'admin' THEN 1 WHEN 'counselor' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $users,
            'roles' => [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_ADMIN,
                User::ROLE_COUNSELOR,
                User::ROLE_STAFF,
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:super_admin,admin,counselor,staff'],
        ]);

        $targetRole = (string) $validated['role'];

        if ($user->isSuperAdmin() && $targetRole !== User::ROLE_SUPER_ADMIN) {
            $superAdminCount = User::query()->where('role', User::ROLE_SUPER_ADMIN)->count();
            abort_if($superAdminCount <= 1, 422, '系统至少需要保留一位超级管理员。');
        }

        $user->forceFill([
            'role' => $targetRole,
        ])->save();

        return response()->json([
            'message' => '角色更新成功。',
            'data' => $user->only(['id', 'role']),
        ]);
    }
}


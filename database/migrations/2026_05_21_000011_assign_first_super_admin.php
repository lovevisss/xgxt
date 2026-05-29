<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (User::query()->where('role', User::ROLE_SUPER_ADMIN)->exists()) {
            return;
        }

        $firstUser = User::query()->orderBy('id')->first();
        if (! $firstUser) {
            return;
        }

        $firstUser->forceFill([
            'role' => User::ROLE_SUPER_ADMIN,
        ])->save();
    }

    public function down(): void
    {
        // No-op: keep role assignments stable once promoted.
    }
};


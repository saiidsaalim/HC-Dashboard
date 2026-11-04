<?php

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->lockAndProtectLastSuperAdmin($lockedUser, null);
            $lockedUser->delete();
        }, attempts: 3);
    }

    public function changeRole(User $user, UserRole $newRole): void
    {
        DB::transaction(function () use ($user, $newRole): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->lockAndProtectLastSuperAdmin($lockedUser, $newRole);
            $lockedUser->update(['role' => $newRole->value]);
        }, attempts: 3);
    }

    private function lockAndProtectLastSuperAdmin(User $user, ?UserRole $newRole): void
    {
        if ($user->roleEnum() !== UserRole::SUPER_ADMIN
            || $newRole === UserRole::SUPER_ADMIN) {
            return;
        }

        $superAdminCount = User::query()
            ->where('role', UserRole::SUPER_ADMIN->value)
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        if ($superAdminCount <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Super Admin aktif terakhir tidak dapat dihapus atau diturunkan rolenya.',
            ]);
        }
    }
}

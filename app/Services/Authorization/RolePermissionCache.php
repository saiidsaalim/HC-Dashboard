<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionCache
{
    public const CacheKey = 'authorization.role-permissions.v1';

    public function hasStoredConfiguration(): bool
    {
        return $this->snapshot()['configured'];
    }

    public function allows(UserRole $role, string $permissionKey): bool
    {
        return in_array($permissionKey, $this->snapshot()['roles'][$role->value] ?? [], true);
    }

    public function clear(): void
    {
        Cache::forget(self::CacheKey);
    }

    /** @return array{configured: bool, roles: array<string, array<int, string>>} */
    private function snapshot(): array
    {
        return Cache::rememberForever(self::CacheKey, function (): array {
            if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
                return ['configured' => false, 'roles' => []];
            }

            $rows = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->orderBy('permissions.sort_order')
                ->get(['permission_role.role', 'permissions.key']);

            return [
                'configured' => $rows->isNotEmpty(),
                'roles' => $rows
                    ->groupBy('role')
                    ->map(fn ($permissions): array => $permissions->pluck('key')->all())
                    ->all(),
            ];
        });
    }
}

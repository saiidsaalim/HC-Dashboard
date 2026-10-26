<?php

namespace App\Services\Authorization;

class WorkspaceRegistry
{
    /** @return array<string, array{key: string, label: string, permission: string, active: bool, assignable: bool, default_access: bool}> */
    public function all(): array
    {
        $workspaces = [];

        foreach (config('portal.units', []) as $unit) {
            foreach ($unit['workspaces'] ?? [] as $workspace) {
                $key = trim((string) ($workspace['slug'] ?? ''));
                $permission = trim((string) ($workspace['permission'] ?? ''));

                if ($key === '' || $permission === '') {
                    continue;
                }

                $workspaces[$key] = [
                    'key' => $key,
                    'label' => (string) ($workspace['name'] ?? $key),
                    'permission' => $permission,
                    'active' => (bool) ($workspace['active'] ?? true),
                    'assignable' => (bool) ($workspace['assignable'] ?? false),
                    'default_access' => (bool) ($workspace['default_access'] ?? false),
                ];
            }
        }

        return $workspaces;
    }

    /** @return array<string, array{key: string, label: string, permission: string, active: bool, assignable: bool, default_access: bool}> */
    public function assignable(): array
    {
        return array_filter(
            $this->all(),
            static fn (array $workspace): bool => $workspace['active']
                && $workspace['assignable']
                && PermissionRegistry::has($workspace['permission']),
        );
    }

    /** @return array<int, string> */
    public function permissionKeys(): array
    {
        return array_values(array_unique(array_column($this->all(), 'permission')));
    }

    /** @return array<int, string> */
    public function assignablePermissionKeys(): array
    {
        return array_values(array_unique(array_column($this->assignable(), 'permission')));
    }

    public function isDefaultPermission(string $permissionKey): bool
    {
        return collect($this->assignable())->contains(
            static fn (array $workspace): bool => $workspace['permission'] === $permissionKey
                && $workspace['default_access'],
        );
    }
}

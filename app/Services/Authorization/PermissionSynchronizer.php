<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use LogicException;

class PermissionSynchronizer
{
    public function __construct(private RolePermissionCache $rolePermissionCache) {}

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     relations_added: int,
     *     relations_removed: int,
     *     stale: int,
     *     created_keys: array<int, string>,
     *     updated_keys: array<int, string>,
     *     stale_keys: array<int, string>,
     *     relation_additions: array<int, array{role: string, key: string}>,
     *     relation_removals: array<int, array{role: string, key: string}>
     * }
     */
    public function synchronize(bool $dryRun = false, bool $applyDefaults = false): array
    {
        $definitions = $this->validatedDefinitions();
        $this->validateWorkspacePermissions($definitions);

        if ($dryRun) {
            return $this->buildPlan($definitions, $applyDefaults);
        }

        $result = DB::transaction(function () use ($definitions, $applyDefaults): array {
            $plan = $this->buildPlan($definitions, $applyDefaults, lockForUpdate: true);

            foreach ($plan['created_keys'] as $key) {
                Permission::query()->create($this->metadata($definitions[$key]));
            }

            foreach ($plan['updated_keys'] as $key) {
                Permission::query()->where('key', $key)->update($this->metadata($definitions[$key]));
            }

            $permissionIds = Permission::query()
                ->whereIn('key', array_keys($definitions))
                ->pluck('id', 'key');
            $now = now();

            foreach ($plan['relation_removals'] as $relation) {
                DB::table('permission_role')
                    ->where('role', $relation['role'])
                    ->where('permission_id', $permissionIds->get($relation['key']))
                    ->delete();
            }

            DB::table('permission_role')->insertOrIgnore(array_map(
                fn (array $relation): array => [
                    'permission_id' => $permissionIds->get($relation['key']),
                    'role' => $relation['role'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $plan['relation_additions'],
            ));

            return $plan;
        }, 3);

        $this->rolePermissionCache->clear();

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    protected function permissionDefinitions(): array
    {
        return array_values(PermissionRegistry::permissions());
    }

    /** @return array<int, array<string, mixed>> */
    protected function workspaceDefinitions(): array
    {
        $workspaces = [];

        foreach (config('portal.units', []) as $unit) {
            foreach ($unit['workspaces'] ?? [] as $workspace) {
                $workspaces[] = $workspace;
            }
        }

        return $workspaces;
    }

    /**
     * @return array<string, array{key: string, label: string, group: string, group_label: string, default_roles: array<int, UserRole>, sort_order: int}>
     */
    private function validatedDefinitions(): array
    {
        $definitions = [];

        foreach ($this->permissionDefinitions() as $index => $definition) {
            if (! is_array($definition)) {
                throw new LogicException("Definisi permission pada indeks {$index} harus berupa array.");
            }

            $key = trim((string) ($definition['key'] ?? ''));
            $label = trim((string) ($definition['label'] ?? ''));
            $group = trim((string) ($definition['group'] ?? ''));
            $groupLabel = trim((string) ($definition['group_label'] ?? ''));
            $defaultRoles = $definition['default_roles'] ?? null;
            $sortOrder = $definition['sort_order'] ?? null;

            if ($key === '' || preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', $key) !== 1) {
                throw new LogicException("Permission key tidak valid pada indeks {$index}: {$key}.");
            }

            if (array_key_exists($key, $definitions)) {
                throw new LogicException("Permission key ganda terdeteksi: {$key}.");
            }

            if ($label === '' || $group === '' || $groupLabel === '') {
                throw new LogicException("Permission {$key} harus memiliki label, group, dan group_label.");
            }

            if (! is_array($defaultRoles) || $defaultRoles === []) {
                throw new LogicException("Permission {$key} harus memiliki default_roles.");
            }

            foreach ($defaultRoles as $role) {
                if (! $role instanceof UserRole) {
                    throw new LogicException("Permission {$key} memiliki default role yang tidak valid.");
                }
            }

            if (! is_int($sortOrder) || $sortOrder < 0) {
                throw new LogicException("Permission {$key} memiliki sort_order yang tidak valid.");
            }

            $definitions[$key] = [
                'key' => $key,
                'label' => $label,
                'group' => $group,
                'group_label' => $groupLabel,
                'default_roles' => array_values(array_unique($defaultRoles, SORT_REGULAR)),
                'sort_order' => $sortOrder,
            ];
        }

        return $definitions;
    }

    /** @param array<string, array<string, mixed>> $definitions */
    private function validateWorkspacePermissions(array $definitions): void
    {
        foreach ($this->workspaceDefinitions() as $workspace) {
            $slug = trim((string) ($workspace['slug'] ?? 'workspace tanpa slug'));
            $permissionKey = trim((string) ($workspace['permission'] ?? ''));

            if ($permissionKey === '' || ! array_key_exists($permissionKey, $definitions)) {
                throw new LogicException(
                    "Workspace {$slug} menggunakan permission yang tidak terdaftar: {$permissionKey}.",
                );
            }
        }
    }

    /**
     * @param  array<string, array{key: string, label: string, group: string, group_label: string, default_roles: array<int, UserRole>, sort_order: int}>  $definitions
     * @return array{
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     relations_added: int,
     *     relations_removed: int,
     *     stale: int,
     *     created_keys: array<int, string>,
     *     updated_keys: array<int, string>,
     *     stale_keys: array<int, string>,
     *     relation_additions: array<int, array{role: string, key: string}>,
     *     relation_removals: array<int, array{role: string, key: string}>
     * }
     */
    private function buildPlan(array $definitions, bool $applyDefaults, bool $lockForUpdate = false): array
    {
        $permissionQuery = Permission::query();

        if ($lockForUpdate) {
            $permissionQuery->lockForUpdate();
        }

        $existingPermissions = $permissionQuery->get()->keyBy('key');
        $createdKeys = [];
        $updatedKeys = [];
        $unchanged = 0;

        foreach ($definitions as $key => $definition) {
            $permission = $existingPermissions->get($key);

            if ($permission === null) {
                $createdKeys[] = $key;

                continue;
            }

            if ($this->metadataChanged($permission, $definition)) {
                $updatedKeys[] = $key;
            } else {
                $unchanged++;
            }
        }

        $staleKeys = $existingPermissions->keys()
            ->diff(array_keys($definitions))
            ->sort()
            ->values()
            ->all();
        $pivotQuery = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->select(['permission_role.role', 'permissions.key']);

        if ($lockForUpdate) {
            $pivotQuery->lockForUpdate();
        }

        $currentRelations = $pivotQuery->get()
            ->mapWithKeys(fn (object $relation): array => [
                $this->relationIdentifier($relation->role, $relation->key) => [
                    'role' => $relation->role,
                    'key' => $relation->key,
                ],
            ]);
        $targetRelations = collect();

        foreach ($definitions as $key => $definition) {
            $roles = $applyDefaults || in_array($key, $createdKeys, true)
                ? $definition['default_roles']
                : [];
            $roles[] = UserRole::SUPER_ADMIN;

            foreach (array_unique($roles, SORT_REGULAR) as $role) {
                $targetRelations->put($this->relationIdentifier($role->value, $key), [
                    'role' => $role->value,
                    'key' => $key,
                ]);
            }
        }

        $relationAdditions = $targetRelations->diffKeys($currentRelations)->values()->all();
        $relationRemovals = [];

        if ($applyDefaults) {
            $knownRoles = array_column(UserRole::cases(), 'value');
            $relationRemovals = $currentRelations
                ->filter(fn (array $relation, string $identifier): bool => isset($definitions[$relation['key']])
                    && in_array($relation['role'], $knownRoles, true)
                    && ! $targetRelations->has($identifier))
                ->values()
                ->all();
        }

        return [
            'created' => count($createdKeys),
            'updated' => count($updatedKeys),
            'unchanged' => $unchanged,
            'relations_added' => count($relationAdditions),
            'relations_removed' => count($relationRemovals),
            'stale' => count($staleKeys),
            'created_keys' => $createdKeys,
            'updated_keys' => $updatedKeys,
            'stale_keys' => $staleKeys,
            'relation_additions' => $relationAdditions,
            'relation_removals' => $relationRemovals,
        ];
    }

    /**
     * @param  array{key: string, label: string, group: string, group_label: string, default_roles: array<int, UserRole>, sort_order: int}  $definition
     * @return array{key: string, label: string, group: string, group_label: string, sort_order: int}
     */
    private function metadata(array $definition): array
    {
        return [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'group' => $definition['group'],
            'group_label' => $definition['group_label'],
            'sort_order' => $definition['sort_order'],
        ];
    }

    /** @param array{label: string, group: string, group_label: string, sort_order: int} $definition */
    private function metadataChanged(Permission $permission, array $definition): bool
    {
        return $permission->label !== $definition['label']
            || $permission->group !== $definition['group']
            || $permission->group_label !== $definition['group_label']
            || (int) $permission->sort_order !== $definition['sort_order'];
    }

    private function relationIdentifier(string $role, string $permissionKey): string
    {
        return $role."\0".$permissionKey;
    }
}

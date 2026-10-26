<?php

namespace App\Actions\Users;

use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\WorkspaceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncUserWorkspaceAccess
{
    public function __construct(private WorkspaceRegistry $workspaceRegistry) {}

    /** @param array<int, string> $selectedWorkspaceKeys */
    public function execute(User $user, array $selectedWorkspaceKeys): void
    {
        $workspaces = $this->workspaceRegistry->assignable();
        $workspacePermissionKeys = $this->workspaceRegistry->permissionKeys();
        $permissions = Permission::query()
            ->whereIn('key', $workspacePermissionKeys)
            ->lockForUpdate()
            ->get()
            ->keyBy('key');
        $assignablePermissionKeys = array_unique(array_column($workspaces, 'permission'));

        if ($permissions->keys()->intersect($assignablePermissionKeys)->count() !== count($assignablePermissionKeys)) {
            throw ValidationException::withMessages([
                'workspace_access' => 'Terdapat workspace yang belum memiliki permission aktif.',
            ]);
        }

        DB::table('permission_user')
            ->where('user_id', $user->getKey())
            ->whereIn('permission_id', $permissions->pluck('id'))
            ->delete();

        if (! $user->isSuperAdmin()) {
            $selectedWorkspaceKeys = array_flip($selectedWorkspaceKeys);
            $now = now();

            DB::table('permission_user')->insert(collect($workspaces)
                ->map(fn (array $workspace): array => [
                    'permission_id' => $permissions->get($workspace['permission'])->id,
                    'user_id' => $user->getKey(),
                    'granted' => array_key_exists($workspace['key'], $selectedWorkspaceKeys),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->unique('permission_id')
                ->values()
                ->all());
        }

        $user->unsetRelation('permissionOverrides');
    }
}

<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Services\Authorization\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (PermissionRegistry::permissions() as $permission) {
                Permission::query()->updateOrCreate(
                    ['key' => $permission['key']],
                    [
                        'label' => $permission['label'],
                        'group' => $permission['group'],
                        'group_label' => $permission['group_label'],
                        'sort_order' => $permission['sort_order'],
                    ],
                );
            }

            $permissions = Permission::query()->get()->keyBy('key');
            $hasConfiguredMatrix = DB::table('permission_role')->exists();
            $now = now();
            $rows = [];

            foreach (PermissionRegistry::permissions() as $key => $definition) {
                $roles = $hasConfiguredMatrix
                    ? [UserRole::SUPER_ADMIN]
                    : collect($definition['roles'])
                        ->push(UserRole::SUPER_ADMIN)
                        ->unique(fn (UserRole $role): string => $role->value);

                foreach ($roles as $role) {
                    $rows[] = [
                        'permission_id' => $permissions->get($key)->id,
                        'role' => $role->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('permission_role')->insertOrIgnore($rows);
        });
    }
}

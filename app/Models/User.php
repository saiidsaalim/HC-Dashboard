<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\Authorization\PermissionRegistry;
use App\Services\Authorization\RolePermissionCache;
use App\Services\Authorization\WorkspaceRegistry;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['employee_id', 'name', 'email', 'role', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function roleEnum(): UserRole
    {
        return UserRole::tryFrom($this->role) ?? UserRole::STAFF;
    }

    public function canManageRbac(): bool
    {
        return $this->roleEnum()->canManageRbac();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleEnum() === UserRole::SUPER_ADMIN;
    }

    public function hasPermission(string $key): bool
    {
        if (! PermissionRegistry::has($key)) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($key === 'users.manage-access') {
            return false;
        }

        if ($key === 'workspace.view') {
            return collect(app(WorkspaceRegistry::class)->assignablePermissionKeys())
                ->contains(fn (string $permissionKey): bool => $this->workspacePermissionDecision($permissionKey) === true);
        }

        $workspacePermission = $this->workspacePermissionDecision($key);

        if ($workspacePermission !== null) {
            return $workspacePermission;
        }

        $rolePermissionCache = app(RolePermissionCache::class);

        if (! $rolePermissionCache->hasStoredConfiguration()) {
            return PermissionRegistry::roleHasPermission($this->roleEnum(), $key);
        }

        return $rolePermissionCache->allows($this->roleEnum(), $key);
    }

    /** @param array<int, string> $keys */
    public function hasAnyPermission(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->hasPermission($key)) {
                return true;
            }
        }

        return false;
    }

    /** @return BelongsToMany<Permission, $this> */
    public function rolePermissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'role',
            'permission_id',
            'role',
        )->withTimestamps();
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot('granted')
            ->withTimestamps();
    }

    /** @return array<int, string> */
    public function workspaceAccessKeys(): array
    {
        $workspaces = app(WorkspaceRegistry::class)->assignable();

        if ($this->isSuperAdmin()) {
            return array_keys($workspaces);
        }

        return collect($workspaces)
            ->filter(fn (array $workspace): bool => $this->hasPermission($workspace['permission']))
            ->keys()
            ->all();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    private function workspacePermissionDecision(string $key): ?bool
    {
        $workspacePermissionKeys = app(WorkspaceRegistry::class)->assignablePermissionKeys();

        if (! in_array($key, $workspacePermissionKeys, true)) {
            return null;
        }

        $this->loadMissing('permissionOverrides');
        $workspaceOverrides = $this->permissionOverrides
            ->whereIn('key', $workspacePermissionKeys);

        if ($workspaceOverrides->isEmpty()) {
            return app(WorkspaceRegistry::class)->isDefaultPermission($key);
        }

        $override = $workspaceOverrides->firstWhere('key', $key);

        return $override === null ? false : (bool) $override->pivot->granted;
    }
}

<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\Authorization\PermissionRegistry;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Fillable(['employee_id', 'name', 'email', 'role', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private ?bool $storedRolePermissionsExist = null;

    public function roleEnum(): UserRole
    {
        return UserRole::tryFrom($this->role) ?? UserRole::STAFF;
    }

    public function canManageRbac(): bool
    {
        return $this->roleEnum()->canManageRbac();
    }

    public function hasPermission(string $key): bool
    {
        if (! PermissionRegistry::has($key)) {
            return false;
        }

        if ($this->roleEnum() === UserRole::SUPER_ADMIN) {
            return true;
        }

        if ($key === 'users.manage-access') {
            return false;
        }

        $this->storedRolePermissionsExist ??= Schema::hasTable('permission_role')
            && DB::table('permission_role')->exists();

        if (! $this->storedRolePermissionsExist) {
            return PermissionRegistry::roleHasPermission($this->roleEnum(), $key);
        }

        $this->loadMissing('rolePermissions');

        return $this->rolePermissions->contains('key', $key);
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
}

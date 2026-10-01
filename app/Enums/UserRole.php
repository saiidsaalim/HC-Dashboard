<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'Super Admin';
    case ADMIN = 'Admin';
    case MANAGER = 'Manager';
    case STAFF = 'Staff';
    case MEMBER = 'Member';

    public function description(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Akses penuh ke seluruh sistem, pengguna, log, dan konfigurasi.',
            self::ADMIN, self::MANAGER => 'Mengelola data operasional, laporan, serta akun Staff dan Member.',
            self::STAFF, self::MEMBER => 'Membaca dan membuat data pribadi serta tugas yang ditugaskan.',
        };
    }

    public function canManageRbac(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN, self::MANAGER], true);
    }

    public function canManageAllRoles(): bool
    {
        return $this === self::SUPER_ADMIN;
    }
}
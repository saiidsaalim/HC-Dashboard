<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;

class PermissionRegistry
{
    /** @return array<string, array{label: string, permissions: array<string, array{label: string, roles: array<int, UserRole>}>}> */
    public static function groups(): array
    {
        static $groups;

        if (isset($groups)) {
            return $groups;
        }

        $all = UserRole::cases();
        $management = [UserRole::SUPER_ADMIN, UserRole::ADMIN, UserRole::MANAGER];
        $superAdmin = [UserRole::SUPER_ADMIN];
        $approvers = [UserRole::ADMIN, UserRole::MANAGER];
        $staff = [UserRole::STAFF];

        return $groups = [
            'dashboard' => ['label' => 'Dashboard', 'permissions' => [
                'dashboard.view' => ['label' => 'Lihat dashboard', 'roles' => $all],
                'workspace.view' => ['label' => 'Buka workspace portal', 'roles' => $all],
                'workspace.hc-od-career-management.view' => ['label' => 'Buka workspace HC OD & Career Management', 'roles' => $all],
                'workspace.performance-talent-management.view' => ['label' => 'Buka workspace Performance & Talent Management', 'roles' => $superAdmin],
                'workspace.learning-development.view' => ['label' => 'Buka workspace Learning & Development', 'roles' => $superAdmin],
            ]],
            'performance-talent' => ['label' => 'Performance & Talent Management', 'permissions' => [
                'critical-positions.view' => ['label' => 'Lihat Critical Position', 'roles' => $superAdmin],
                'critical-positions.create' => ['label' => 'Tambah Critical Position', 'roles' => $superAdmin],
                'critical-positions.update' => ['label' => 'Edit Critical Position', 'roles' => $superAdmin],
                'critical-positions.delete' => ['label' => 'Hapus Critical Position', 'roles' => $superAdmin],
            ]],
            'employees' => ['label' => 'Data Pegawai', 'permissions' => [
                'employees.view' => ['label' => 'Lihat data pegawai', 'roles' => $all],
                'employees.create' => ['label' => 'Tambah data pegawai', 'roles' => $superAdmin],
                'employees.update' => ['label' => 'Edit data pegawai', 'roles' => $superAdmin],
                'employees.delete' => ['label' => 'Hapus data pegawai', 'roles' => $superAdmin],
                'employees.import' => ['label' => 'Impor dan proses data pegawai', 'roles' => $superAdmin],
                'employees.export' => ['label' => 'Ekspor data pegawai', 'roles' => $all],
                'employees.print' => ['label' => 'Cetak data pegawai', 'roles' => $all],
                'employees.sync-organization' => ['label' => 'Sinkronisasi organisasi pegawai', 'roles' => $superAdmin],
            ]],
            'mutation' => ['label' => 'Mutasi', 'permissions' => [
                'mutations.view' => ['label' => 'Lihat mutasi', 'roles' => $all],
                'mutations.create' => ['label' => 'Tambah mutasi', 'roles' => $all],
                'mutations.update' => ['label' => 'Edit mutasi', 'roles' => $superAdmin],
                'mutations.delete' => ['label' => 'Hapus mutasi', 'roles' => $superAdmin],
                'mutations.import' => ['label' => 'Impor mutasi', 'roles' => $all],
                'mutations.approve' => ['label' => 'Setujui mutasi', 'roles' => $approvers],
                'mutations.print' => ['label' => 'Cetak mutasi Final', 'roles' => $superAdmin],
            ]],
            'promotion' => ['label' => 'Promosi', 'permissions' => [
                'promotions.view' => ['label' => 'Lihat promosi', 'roles' => $management],
                'promotions.create' => ['label' => 'Tambah promosi', 'roles' => $superAdmin],
                'promotions.update' => ['label' => 'Edit promosi', 'roles' => $superAdmin],
                'promotions.delete' => ['label' => 'Hapus promosi', 'roles' => $superAdmin],
                'promotions.import' => ['label' => 'Impor promosi', 'roles' => $superAdmin],
                'promotions.submit' => ['label' => 'Ajukan promosi', 'roles' => $superAdmin],
                'promotions.approve' => ['label' => 'Setujui atau kembalikan promosi', 'roles' => $approvers],
                'promotions.print' => ['label' => 'Cetak promosi Final', 'roles' => $superAdmin],
                'promotions.manage-approval-settings' => ['label' => 'Atur alur persetujuan', 'roles' => $superAdmin],
            ]],
            'demotion' => ['label' => 'Demosi', 'permissions' => [
                'demotions.view' => ['label' => 'Lihat demosi', 'roles' => $all],
                'demotions.create' => ['label' => 'Tambah demosi', 'roles' => $all],
                'demotions.update' => ['label' => 'Edit demosi', 'roles' => $superAdmin],
                'demotions.delete' => ['label' => 'Hapus demosi', 'roles' => $superAdmin],
                'demotions.import' => ['label' => 'Impor demosi', 'roles' => $all],
                'demotions.approve' => ['label' => 'Setujui demosi', 'roles' => $approvers],
                'demotions.print' => ['label' => 'Cetak demosi Final', 'roles' => $superAdmin],
            ]],
            'wla' => ['label' => 'WLA / FTE', 'permissions' => [
                'wla.view' => ['label' => 'Lihat assessment WLA', 'roles' => $management],
                'wla.create' => ['label' => 'Tambah assessment WLA', 'roles' => $management],
                'wla.update' => ['label' => 'Edit assessment dan aktivitas WLA', 'roles' => $management],
                'wla.delete' => ['label' => 'Hapus assessment WLA', 'roles' => $management],
                'wla.finalize' => ['label' => 'Finalisasi WLA', 'roles' => $management],
                'wla.export' => ['label' => 'Cetak dan ekspor WLA', 'roles' => $management],
                'wla.participants.manage' => ['label' => 'Kelola peserta WLA', 'roles' => $management],
                'wla.participants.review' => ['label' => 'Review pengajuan peserta', 'roles' => $management],
                'wla.participants.import-approved' => ['label' => 'Impor aktivitas peserta yang disetujui', 'roles' => $management],
                'wla.staff.view' => ['label' => 'Lihat WLA sendiri', 'roles' => $staff],
                'wla.staff.update' => ['label' => 'Isi aktivitas WLA sendiri', 'roles' => $staff],
                'wla.staff.submit' => ['label' => 'Kirim WLA sendiri', 'roles' => $staff],
            ]],
            'organization' => ['label' => 'Master Organisasi', 'permissions' => [
                'organization.view' => ['label' => 'Lihat struktur organisasi', 'roles' => $superAdmin],
                'organization.create' => ['label' => 'Tambah struktur organisasi', 'roles' => $superAdmin],
                'organization.update' => ['label' => 'Edit struktur organisasi', 'roles' => $superAdmin],
                'organization.delete' => ['label' => 'Hapus struktur organisasi', 'roles' => $superAdmin],
            ]],
            'workforce-master' => ['label' => 'Master WLA', 'permissions' => [
                'work-schedules.view' => ['label' => 'Lihat jadwal kerja', 'roles' => $superAdmin],
                'work-schedules.create' => ['label' => 'Tambah jadwal kerja', 'roles' => $superAdmin],
                'work-schedules.update' => ['label' => 'Edit jadwal kerja', 'roles' => $superAdmin],
                'work-schedules.delete' => ['label' => 'Hapus jadwal kerja', 'roles' => $superAdmin],
                'work-calendars.view' => ['label' => 'Lihat kalender kerja', 'roles' => $superAdmin],
                'work-calendars.create' => ['label' => 'Tambah kalender kerja', 'roles' => $superAdmin],
                'work-calendars.update' => ['label' => 'Edit kalender kerja', 'roles' => $superAdmin],
                'work-calendars.delete' => ['label' => 'Hapus kalender kerja', 'roles' => $superAdmin],
            ]],
            'users' => ['label' => 'Konfigurasi User', 'permissions' => [
                'users.view' => ['label' => 'Lihat Konfigurasi User', 'roles' => $all],
                'users.create' => ['label' => 'Tambah pengguna', 'roles' => $management],
                'users.update' => ['label' => 'Edit pengguna', 'roles' => $superAdmin],
                'users.delete' => ['label' => 'Hapus pengguna', 'roles' => $superAdmin],
                'users.manage-roles' => ['label' => 'Ubah role pengguna', 'roles' => $superAdmin],
                'users.approve-candidates' => ['label' => 'Setujui calon akun Staff', 'roles' => $superAdmin],
                'users.manage-access' => ['label' => 'Atur hak akses role', 'roles' => $superAdmin],
            ]],
            'other-pages' => ['label' => 'Halaman Lain', 'permissions' => [
                'fit-proper.view' => ['label' => 'Lihat Fit & Proper', 'roles' => $all],
                'formation.view' => ['label' => 'Lihat Formasi', 'roles' => $all],
                'definitive.view' => ['label' => 'Lihat Definitif', 'roles' => $all],
                'reports.view' => ['label' => 'Lihat Laporan', 'roles' => $all],
            ]],
        ];
    }

    /** @return array<string, array{key: string, label: string, group: string, group_label: string, default_roles: array<int, UserRole>, sort_order: int}> */
    public static function permissions(): array
    {
        static $permissions;

        if (isset($permissions)) {
            return $permissions;
        }

        $permissions = [];
        $sortOrder = 0;

        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['permissions'] as $key => $permission) {
                $permissions[$key] = [
                    'key' => $key,
                    'label' => $permission['label'],
                    'group' => $groupKey,
                    'group_label' => $group['label'],
                    'default_roles' => $permission['roles'],
                    'sort_order' => $sortOrder++,
                ];
            }
        }

        return $permissions;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::permissions());
    }

    public static function roleHasPermission(UserRole $role, string $key): bool
    {
        $permission = self::permissions()[$key] ?? null;

        return $permission !== null && in_array($role, $permission['default_roles'], true);
    }
}

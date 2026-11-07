<?php

namespace App\Services;

use App\Models\DashboardLayout;
use App\Models\User;

class DashboardLayoutService
{
    public const Workspace = 'hc-od-career-management';

    public const SlotTypes = [
        'compact-1' => 'compact',
        'compact-2' => 'compact',
        'main-1' => 'main',
        'main-2' => 'main',
        'right-1' => 'main',
        'right-2' => 'main',
    ];

    private const MovementPermissions = ['mutations.view', 'promotions.view', 'demotions.view'];

    private const Widgets = [
        'total-employees' => ['type' => 'compact', 'title' => 'Total Pegawai', 'description' => 'Jumlah pegawai yang tercatat.', 'icon' => 'users', 'permissions' => ['employees.view']],
        'wla-draft' => ['type' => 'compact', 'title' => 'WLA Draft', 'description' => 'Assessment WLA berstatus Draft.', 'icon' => 'chart', 'permissions' => ['wla.view']],
        'fit-proper-participants' => ['type' => 'compact', 'title' => 'Peserta Fit & Proper', 'description' => 'Peserta kegiatan Berjalan sesuai akses Anda.', 'icon' => 'clipboard', 'permissions' => ['fit-proper.view']],
        'pending-requests' => ['type' => 'compact', 'title' => 'Pengajuan Menunggu', 'description' => 'Pengajuan pergerakan pegawai yang menunggu persetujuan.', 'icon' => 'arrows', 'permissions' => self::MovementPermissions],
        'career-movements' => ['type' => 'main', 'title' => 'Pergerakan Karier', 'description' => 'Pergerakan Final efektif selama enam bulan terakhir.', 'icon' => 'arrows', 'permissions' => self::MovementPermissions],
        'fit-proper-status' => ['type' => 'main', 'title' => 'Status Fit & Proper', 'description' => 'Status kegiatan Fit & Proper sesuai akses Anda.', 'icon' => 'clipboard', 'permissions' => ['fit-proper.view']],
        'wla-summary' => ['type' => 'main', 'title' => 'Ringkasan WLA', 'description' => 'Jumlah assessment WLA menurut status.', 'icon' => 'chart', 'permissions' => ['wla.view']],
        'follow-up' => ['type' => 'main', 'title' => 'Perlu Ditindaklanjuti', 'description' => 'Pengajuan mutasi, promosi, dan demosi sesuai akses Anda.', 'icon' => 'document', 'permissions' => self::MovementPermissions],
    ];

    /** @return list<array{id: string, type: string, title: string, description: string, icon: string}> */
    public function widgetsFor(User $user): array
    {
        $widgets = [];
        foreach (self::Widgets as $id => $widget) {
            if (! $user->hasAnyPermission($widget['permissions'])) {
                continue;
            }

            unset($widget['permissions']);
            $widgets[] = ['id' => $id, ...$widget];
        }

        return $widgets;
    }

    /** @return array{compact-1: ?string, compact-2: ?string, main-1: ?string, main-2: ?string, right-1: ?string, right-2: ?string} */
    public function layoutFor(User $user): array
    {
        $preference = DashboardLayout::query()->where('user_id', $user->id)->where('workspace', self::Workspace)->first();

        return $this->sanitize($user, $preference === null ? [
            'compact-1' => 'total-employees', 'compact-2' => 'fit-proper-participants',
            'main-1' => 'career-movements', 'main-2' => 'follow-up',
            'right-1' => 'fit-proper-status', 'right-2' => 'wla-summary',
        ] : $preference->slots);
    }

    /**
     * @param  array<string, ?string>  $slots
     * @return array{compact-1: ?string, compact-2: ?string, main-1: ?string, main-2: ?string, right-1: ?string, right-2: ?string}
     */
    public function saveFor(User $user, array $slots): array
    {
        $slots = $this->sanitize($user, $slots);
        DashboardLayout::query()->updateOrCreate(
            ['user_id' => $user->id, 'workspace' => self::Workspace],
            ['slots' => $slots],
        );

        return $slots;
    }

    /** @return array{compact-1: ?string, compact-2: ?string, main-1: ?string, main-2: ?string, right-1: ?string, right-2: ?string} */
    private function sanitize(User $user, mixed $slots): array
    {
        $available = array_column($this->widgetsFor($user), null, 'id');
        $result = [];
        $used = [];

        foreach (self::SlotTypes as $slot => $type) {
            $id = is_array($slots) ? ($slots[$slot] ?? null) : null;
            if (! is_string($id) || ! isset($available[$id]) || $available[$id]['type'] !== $type || in_array($id, $used, true)) {
                $result[$slot] = null;

                continue;
            }

            $result[$slot] = $id;
            $used[] = $id;
        }

        return $result;
    }
}

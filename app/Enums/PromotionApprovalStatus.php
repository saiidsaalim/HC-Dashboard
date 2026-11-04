<?php

namespace App\Enums;

enum PromotionApprovalStatus: string
{
    case Pending = 'Pending';
    case Approved = 'Approved';
    case Returned = 'Returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Returned => 'Dikembalikan',
        };
    }
}

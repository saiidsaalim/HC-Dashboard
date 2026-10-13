<?php

namespace App\Enums;

enum PromotionApprovalStatus: string
{
    case Approved = 'Approved';
    case Returned = 'Returned';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::Returned => 'Dikembalikan',
        };
    }
}

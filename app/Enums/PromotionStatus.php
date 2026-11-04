<?php

namespace App\Enums;

enum PromotionStatus: string
{
    case Draft = 'Draft';
    case WaitingApproval = 'Waiting Approval';
    case Returned = 'Returned';
    case Approved = 'Approved';
    case Final = 'Final';
    case LegacyPending = 'Pending';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::WaitingApproval => 'Menunggu Persetujuan',
            self::Returned => 'Dikembalikan',
            self::Approved => 'Disetujui, menunggu TMT',
            self::Final => 'Final',
            self::LegacyPending => 'Pending (data lama)',
        };
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited();
    }

    public function canBeDeleted(): bool
    {
        return $this === self::Draft;
    }
}

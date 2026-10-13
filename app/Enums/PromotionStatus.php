<?php

namespace App\Enums;

enum PromotionStatus: string
{
    case Draft = 'Draft';
    case WaitingApproval = 'Waiting Approval';
    case Returned = 'Returned';
    case Final = 'Final';
    case LegacyPending = 'Pending';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::WaitingApproval => 'Menunggu Persetujuan',
            self::Returned => 'Dikembalikan',
            self::Final => 'Final',
            self::LegacyPending => 'Pending (data lama)',
        };
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Returned, self::LegacyPending], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited();
    }
}

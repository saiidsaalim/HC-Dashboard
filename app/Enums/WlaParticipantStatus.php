<?php

namespace App\Enums;

enum WlaParticipantStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Dikirim',
            self::Returned => 'Dikembalikan',
            self::Approved => 'Disetujui',
        };
    }

    public function isEditableByStaff(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }
}

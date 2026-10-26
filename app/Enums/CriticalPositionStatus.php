<?php

namespace App\Enums;

enum CriticalPositionStatus: string
{
    case Covered = 'Covered';
    case Vacant = 'Vacant';

    public function tone(): string
    {
        return match ($this) {
            self::Covered => 'success',
            self::Vacant => 'danger',
        };
    }
}

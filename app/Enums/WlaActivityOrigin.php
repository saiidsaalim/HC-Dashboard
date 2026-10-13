<?php

namespace App\Enums;

enum WlaActivityOrigin: string
{
    case Legacy = 'legacy';
    case Management = 'management';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Legacy => 'Data lama',
            self::Management => 'Input management',
            self::Staff => 'Staff',
        };
    }
}

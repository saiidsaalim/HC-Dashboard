<?php

namespace App\Enums;

enum CriticalPositionCriticality: string
{
    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';

    public function tone(): string
    {
        return match ($this) {
            self::High => 'danger',
            self::Medium => 'warning',
            self::Low => 'info',
        };
    }
}

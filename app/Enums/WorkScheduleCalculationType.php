<?php

namespace App\Enums;

enum WorkScheduleCalculationType: string
{
    case Dayshift = 'dayshift';
    case Shift123 = 'shift_123';
    case Shift77 = 'shift_77';

    public function label(): string
    {
        return match ($this) {
            self::Dayshift => 'Dayshift',
            self::Shift123 => 'Shift 1/2/3',
            self::Shift77 => 'Shift 7/7, 7/19, dan 19/7',
        };
    }

    public function workingHoursPerDay(): string
    {
        return match ($this) {
            self::Dayshift => '7.00',
            self::Shift123 => '7.50',
            self::Shift77 => '10.00',
        };
    }

    public static function fromLegacyCode(string $code): ?self
    {
        return self::legacyCodeMap()[$code] ?? null;
    }

    /** @return array<string, self> */
    public static function legacyCodeMap(): array
    {
        return [
            'DAYSHIFT' => self::Dayshift,
            'SHIFT_1' => self::Shift123,
            'SHIFT_2' => self::Shift123,
            'SHIFT_3' => self::Shift123,
            'SHIFT_7_7' => self::Shift77,
            'SHIFT_7_19' => self::Shift77,
            'SHIFT_19_7' => self::Shift77,
        ];
    }
}

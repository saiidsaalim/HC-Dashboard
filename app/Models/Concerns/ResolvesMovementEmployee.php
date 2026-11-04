<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

trait ResolvesMovementEmployee
{
    public static function bootResolvesMovementEmployee(): void
    {
        static::creating(function (Model $movement): void {
            if ($movement->getAttribute('employee_id') === null
                && filled($movement->getAttribute('sap'))) {
                $movement->setAttribute(
                    'employee_id',
                    Employee::query()->where('sap', $movement->getAttribute('sap'))->value('id'),
                );
            }

            if (! filled($movement->getAttribute('sap'))
                && $movement->getAttribute('employee_id') !== null) {
                $movement->setAttribute(
                    'sap',
                    Employee::query()->whereKey($movement->getAttribute('employee_id'))->value('sap'),
                );
            }
        });
    }
}

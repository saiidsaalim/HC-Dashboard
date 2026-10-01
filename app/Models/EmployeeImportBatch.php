<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class EmployeeImportBatch extends Model
{
    protected $fillable = [
        'source_file',
        'source_type',
        'imported_by',
        'imported_at',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'approved_rows',
        'processed_rows',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'approved_rows' => 'integer',
            'processed_rows' => 'integer',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(EmployeeImportRow::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}

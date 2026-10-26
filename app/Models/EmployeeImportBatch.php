<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeImportBatch extends Model
{
    protected $fillable = [
        'source_file',
        'original_file_name',
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

    public function displayFileName(): string
    {
        return $this->original_file_name ?: basename($this->source_file);
    }

    protected static function booted(): void
    {
        static::deleting(function (EmployeeImportBatch $batch): void {
            $batch->rows()->delete();
        });
    }
}

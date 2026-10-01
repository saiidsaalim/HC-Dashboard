<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EmployeeImportRow extends Model
{
    protected $fillable = [
        'employee_import_batch_id',
        'source_row',
        'source_reference',
        'source_payload',
        'normalized_payload',
        'validation_status',
        'mapping_status',
        'validation_errors',
        'mapping_errors',
        'processing_result',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
            'source_payload' => 'array',
            'normalized_payload' => 'array',
            'validation_errors' => 'array',
            'mapping_errors' => 'array',
            'processing_result' => 'array',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EmployeeImportBatch::class, 'employee_import_batch_id');
    }
}

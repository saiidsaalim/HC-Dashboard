<?php

namespace App\Models;

use Database\Factories\EmployeeMutationBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['batch_number', 'name', 'description', 'default_tmt', 'created_by'])]
class EmployeeMutationBatch extends Model
{
    /** @use HasFactory<EmployeeMutationBatchFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(EmployeeMutation::class);
    }

    protected function casts(): array
    {
        return [
            'default_tmt' => 'date',
        ];
    }
}

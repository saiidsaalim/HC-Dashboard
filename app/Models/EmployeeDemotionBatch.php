<?php

namespace App\Models;

use Database\Factories\EmployeeDemotionBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['batch_number', 'name', 'description', 'default_tmt', 'created_by'])]
class EmployeeDemotionBatch extends Model
{
    /** @use HasFactory<EmployeeDemotionBatchFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function demotions(): HasMany
    {
        return $this->hasMany(EmployeeDemotion::class);
    }

    protected function casts(): array
    {
        return ['default_tmt' => 'date'];
    }
}

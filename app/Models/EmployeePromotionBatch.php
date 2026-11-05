<?php

namespace App\Models;

use Database\Factories\EmployeePromotionBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['batch_number', 'name', 'description', 'default_tmt', 'created_by'])]
class EmployeePromotionBatch extends Model
{
    /** @use HasFactory<EmployeePromotionBatchFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(EmployeePromotion::class);
    }

    protected function casts(): array
    {
        return ['default_tmt' => 'date'];
    }
}

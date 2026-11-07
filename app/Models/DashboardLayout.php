<?php

namespace App\Models;

use Database\Factories\DashboardLayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'workspace', 'slots'])]
class DashboardLayout extends Model
{
    /** @use HasFactory<DashboardLayoutFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['slots' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

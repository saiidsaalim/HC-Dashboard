<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FitProperExaminer extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(FitProperActivity::class, 'activity_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(FitProperScore::class, 'examiner_id');
    }
}

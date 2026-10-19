<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FitProperParticipant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['profile' => 'array', 'birth_date' => 'date'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(FitProperActivity::class, 'fit_proper_activity_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(FitProperScore::class, 'participant_id');
    }

    public function kpi(): ?float
    {
        return $this->kpi_year_1 === null || $this->kpi_year_2 === null ? null : ((float) $this->kpi_year_1 + (float) $this->kpi_year_2) / 2;
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }
}

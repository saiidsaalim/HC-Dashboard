<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FitProperActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['standard' => 'array', 'final_snapshot' => 'array', 'is_simulation' => 'boolean', 'scheduled_at' => 'date', 'finalized_at' => 'datetime'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FitProperParticipant::class, 'fit_proper_activity_id');
    }

    public function examiners(): HasMany
    {
        return $this->hasMany(FitProperExaminer::class, 'activity_id');
    }

    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fit_proper_pics', 'activity_id');
    }

    public function histories(): Builder
    {
        return DB::table('fit_proper_histories')->where('activity_id', $this->id);
    }
}

<?php

namespace App\Models;

use App\Enums\CriticalPositionCriticality;
use Carbon\CarbonInterface;
use Database\Factories\CriticalPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'position_id',
    'criticality',
    'assessment_reason',
    'assessed_at',
    'review_due_at',
    'assessed_by',
    'notes',
])]
class CriticalPosition extends Model
{
    /** @use HasFactory<CriticalPositionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'criticality' => CriticalPositionCriticality::class,
            'assessed_at' => 'date',
            'review_due_at' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /** @return array{date: string, label: string, tone: string} */
    public function reviewSchedule(?CarbonInterface $today = null): array
    {
        if ($this->review_due_at === null) {
            return [
                'date' => 'Belum dijadwalkan',
                'label' => 'Belum dijadwalkan',
                'tone' => 'neutral',
            ];
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $reviewDate = $this->review_due_at->copy()->startOfDay();
        $daysUntilReview = (int) $today->diffInDays($reviewDate, false);

        if ($daysUntilReview === 0) {
            $label = 'Hari ini';
            $tone = 'warning';
        } elseif ($daysUntilReview > 0) {
            $label = $daysUntilReview.' hari lagi';
            $tone = 'warning';
        } else {
            $label = 'Terlambat '.abs($daysUntilReview).' hari';
            $tone = 'danger';
        }

        return [
            'date' => $reviewDate->format('d M Y'),
            'label' => $label,
            'tone' => $tone,
        ];
    }
}

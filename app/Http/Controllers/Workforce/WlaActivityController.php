<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWlaActivityRequest;
use App\Http\Requests\UpdateWlaActivityRequest;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use App\Services\Workforce\WlaCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WlaActivityController extends Controller
{
    public function __construct(private WlaCalculationService $wlaCalculationService) {}

    public function store(StoreWlaActivityRequest $request, WlaAssessment $wla): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $wla): void {
            $lockedAssessment = $this->lockAuthorizedAssessment($wla);
            $maximumSortOrder = $lockedAssessment->activities()->max('sort_order');

            if (! array_key_exists('sort_order', $data)) {
                if ($maximumSortOrder !== null && (int) $maximumSortOrder >= WlaActivity::MAX_SORT_ORDER) {
                    throw ValidationException::withMessages([
                        'sort_order' => 'Urutan aktivitas telah mencapai batas maksimum.',
                    ]);
                }

                $data['sort_order'] = ((int) ($maximumSortOrder ?? -1)) + 1;
            }

            $lockedAssessment->activities()->create([
                ...$data,
                'frequency_unit' => $data['period_unit'],
                'volume' => '1.00',
                'volume_unit' => $data['period_unit'],
                'time_allocated' => $data['time_allocated_hours'],
                'time_unit' => 'Hour',
            ]);

            $this->wlaCalculationService->recalculate($lockedAssessment);
        });

        return to_route('wla.show', $wla)->with('status', 'Activity berhasil ditambahkan.');
    }

    public function update(UpdateWlaActivityRequest $request, WlaAssessment $wla, WlaActivity $activity): RedirectResponse
    {
        DB::transaction(function () use ($request, $wla, $activity): void {
            $lockedAssessment = $this->lockAuthorizedAssessment($wla);
            $lockedActivity = $lockedAssessment->activities()
                ->whereKey($activity->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActivity->update($request->validated());
            $this->wlaCalculationService->recalculate($lockedAssessment);
        });

        return to_route('wla.show', $wla)->with('status', 'Activity berhasil diperbarui.');
    }

    public function destroy(Request $request, WlaAssessment $wla, WlaActivity $activity): RedirectResponse
    {
        DB::transaction(function () use ($wla, $activity): void {
            $lockedAssessment = $this->lockAuthorizedAssessment($wla);
            $lockedActivity = $lockedAssessment->activities()
                ->whereKey($activity->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActivity->delete();
            $this->wlaCalculationService->recalculate($lockedAssessment);
        });

        return to_route('wla.show', $wla)->with('status', 'Activity berhasil dihapus.');
    }

    private function lockAuthorizedAssessment(WlaAssessment $assessment): WlaAssessment
    {
        $lockedAssessment = WlaAssessment::query()
            ->lockForUpdate()
            ->findOrFail($assessment->getKey());
        Gate::authorize('update', $lockedAssessment);

        return $lockedAssessment;
    }
}

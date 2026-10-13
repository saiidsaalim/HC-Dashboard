<?php

namespace App\Http\Controllers\Workforce;

use App\Enums\WlaActivityOrigin;
use App\Enums\WlaParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewWlaParticipantRequest;
use App\Http\Requests\StoreWlaParticipantRequest;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use App\Models\WlaParticipant;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WlaParticipantCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WlaParticipantController extends Controller
{
    public function __construct(
        private WlaCalculationService $wlaCalculationService,
        private WlaParticipantCalculationService $participantCalculationService,
    ) {}

    public function store(StoreWlaParticipantRequest $request, WlaAssessment $wla): RedirectResponse
    {
        DB::transaction(function () use ($request, $wla): void {
            $lockedAssessment = WlaAssessment::query()->lockForUpdate()->findOrFail($wla->getKey());
            Gate::authorize('manageParticipants', [WlaParticipant::class, $lockedAssessment]);
            $lockedAssessment->participants()->create([
                'employee_id' => $request->validated('employee_id'),
                'assigned_by' => $request->user()->getKey(),
                'status' => WlaParticipantStatus::Draft,
            ]);
        });

        return to_route('wla.show', $wla)->with('status', 'Staff berhasil ditambahkan sebagai peserta.');
    }

    public function destroy(
        WlaAssessment $wla,
        WlaParticipant $participant,
    ): RedirectResponse {
        DB::transaction(function () use ($wla, $participant): void {
            $lockedAssessment = WlaAssessment::query()->lockForUpdate()->findOrFail($wla->getKey());
            Gate::authorize('manageParticipants', [WlaParticipant::class, $lockedAssessment]);
            $lockedParticipant = $lockedAssessment->participants()
                ->whereKey($participant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedParticipant->activities()->lockForUpdate()->get(['id']);
            $lockedParticipant->delete();
        });

        return to_route('wla.show', $wla)->with('status', 'Peserta WLA berhasil dihapus.');
    }

    public function show(WlaAssessment $wla, WlaParticipant $participant): View
    {
        Gate::authorize('view', $wla);
        $participant = $wla->participants()->whereKey($participant->getKey())->firstOrFail();
        Gate::authorize('view', $participant);
        $participant->load(['employee.user', 'assigner', 'reviewer', 'appliedBy', 'activities']);

        return view('wla.participants.show', [
            'wla' => $wla->load(['department', 'unit', 'position']),
            'participant' => $participant,
            'subtotal' => $this->participantCalculationService->subtotal(
                $participant->activities->pluck('annual_workload_hours'),
            ),
        ]);
    }

    public function returnSubmission(
        ReviewWlaParticipantRequest $request,
        WlaAssessment $wla,
        WlaParticipant $participant,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $wla, $participant): void {
            $lockedParticipant = $this->lockedNestedParticipant($wla, $participant);
            Gate::authorize('review', $lockedParticipant);
            $lockedParticipant->update([
                'status' => WlaParticipantStatus::Returned,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getKey(),
                'review_note' => $request->validated('review_note'),
            ]);
        });

        return to_route('wla.participants.show', [$wla, $participant])
            ->with('status', 'Pengisian dikembalikan kepada Staff.');
    }

    public function approve(
        Request $request,
        WlaAssessment $wla,
        WlaParticipant $participant,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $wla, $participant): void {
            $lockedParticipant = $this->lockedNestedParticipant($wla, $participant);
            Gate::authorize('review', $lockedParticipant);
            $activities = $lockedParticipant->activities()->lockForUpdate()->get();

            if ($activities->isEmpty()) {
                throw ValidationException::withMessages([
                    'review' => 'Pengisian kosong tidak dapat disetujui.',
                ]);
            }

            $lockedParticipant->update([
                'status' => WlaParticipantStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getKey(),
                'review_note' => null,
            ]);
        });

        return to_route('wla.participants.show', [$wla, $participant])
            ->with('status', 'Pengisian WLA Staff berhasil disetujui.');
    }

    public function importApproved(
        Request $request,
        WlaAssessment $wla,
        WlaParticipant $participant,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $wla, $participant): void {
            $lockedParticipant = $this->lockedNestedParticipant($wla, $participant);
            Gate::authorize('importApproved', $lockedParticipant);
            $activities = $lockedParticipant->activities()->lockForUpdate()->get();

            if ($activities->isEmpty()) {
                throw ValidationException::withMessages([
                    'participant' => 'Pengisian kosong tidak dapat digunakan.',
                ]);
            }

            $maximumSortOrder = $lockedParticipant->assessment->activities()->max('sort_order');
            $nextSortOrder = ((int) ($maximumSortOrder ?? -1)) + 1;
            $lockedParticipant->loadMissing('employee.user');
            $staffUserId = $lockedParticipant->employee?->user?->getKey();

            foreach ($activities as $activity) {
                if ($nextSortOrder > WlaActivity::MAX_SORT_ORDER) {
                    throw ValidationException::withMessages([
                        'sort_order' => 'Urutan aktivitas telah mencapai batas maksimum.',
                    ]);
                }

                $lockedParticipant->assessment->activities()->create([
                    'origin' => WlaActivityOrigin::Staff,
                    'created_by' => $staffUserId,
                    'updated_by' => null,
                    'activity_name' => $activity->activity_name,
                    'frequency' => $activity->frequency,
                    'period_unit' => $activity->period_unit,
                    'frequency_unit' => $activity->period_unit->value,
                    'volume' => '1.00',
                    'volume_unit' => $activity->period_unit->value,
                    'time_allocated' => $activity->time_allocated_hours,
                    'time_unit' => 'Hour',
                    'time_allocated_hours' => $activity->time_allocated_hours,
                    'sort_order' => $nextSortOrder++,
                    'notes' => $activity->notes,
                ]);
            }

            $lockedParticipant->update([
                'applied_at' => now(),
                'applied_by' => $request->user()->getKey(),
            ]);
            $this->wlaCalculationService->recalculate($lockedParticipant->assessment);
        });

        return to_route('wla.show', $wla)
            ->with('status', 'Aktivitas yang disetujui berhasil ditambahkan ke daftar Aktivitas.');
    }

    private function lockedNestedParticipant(
        WlaAssessment $assessment,
        WlaParticipant $participant,
    ): WlaParticipant {
        $lockedAssessment = WlaAssessment::query()->lockForUpdate()->findOrFail($assessment->getKey());
        $lockedParticipant = $lockedAssessment->participants()
            ->whereKey($participant->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $lockedParticipant->setRelation('assessment', $lockedAssessment);

        return $lockedParticipant;
    }
}

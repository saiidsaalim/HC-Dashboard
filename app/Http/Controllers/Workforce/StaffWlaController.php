<?php

namespace App\Http\Controllers\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaParticipantStatus;
use App\Enums\WlaPeriodUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWlaParticipantActivityRequest;
use App\Http\Requests\UpdateWlaParticipantActivityRequest;
use App\Models\User;
use App\Models\WlaActivity;
use App\Models\WlaParticipant;
use App\Models\WlaParticipantActivity;
use App\Services\Workforce\WlaParticipantCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffWlaController extends Controller
{
    public function __construct(private WlaParticipantCalculationService $calculationService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WlaParticipant::class);

        /** @var User $user */
        $user = $request->user();
        $employee = $user->employee()
            ->with(['department:id,name', 'unit:id,name', 'position:id,name'])
            ->first();
        $participants = WlaParticipant::query()
            ->where('employee_id', $user->employee_id)
            ->with([
                'assessment' => fn (BelongsTo $query) => $query->select([
                    'id', 'assessment_code', 'period', 'department_id', 'unit_id', 'position_id', 'status',
                ]),
                'assessment.position:id,name',
            ])
            ->with('assigner:id,name,role')
            ->withCount('activities')
            ->orderByDesc('id')
            ->paginate(15);

        return view('wla.staff.index', compact('employee', 'participants'));
    }

    public function show(WlaParticipant $participant): View
    {
        Gate::authorize('viewAny', WlaParticipant::class);
        Gate::authorize('view', $participant);

        $participant->load([
            'assessment' => fn (BelongsTo $query) => $query->select([
                'id', 'assessment_code', 'period', 'department_id', 'unit_id', 'position_id',
                'work_schedule_id', 'work_calendar_id', 'status',
            ]),
            'assessment.department:id,name',
            'assessment.unit:id,name',
            'assessment.position:id,name',
            'assigner:id,name,role',
            'reviewer:id,name,role',
            'activities',
        ]);

        return view('wla.staff.show', [
            'participant' => $participant,
            'subtotal' => $this->calculationService->subtotal(
                $participant->activities->pluck('annual_workload_hours'),
            ),
            'isEditable' => Gate::allows('update', $participant),
            'isComplete' => $participant->activities->isNotEmpty(),
        ]);
    }

    public function storeActivity(
        StoreWlaParticipantActivityRequest $request,
        WlaParticipant $participant,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $participant): void {
            $lockedParticipant = $this->lockedEditableParticipant($participant);
            $data = $request->validated();
            $maximumSortOrder = $lockedParticipant->activities()->max('sort_order');

            if ($maximumSortOrder !== null && (int) $maximumSortOrder >= WlaActivity::MAX_SORT_ORDER) {
                throw ValidationException::withMessages([
                    'sort_order' => 'Urutan aktivitas telah mencapai batas maksimum.',
                ]);
            }

            $lockedParticipant->activities()->create([
                ...$data,
                'period_unit' => WlaPeriodUnit::from($data['period_unit']),
                'annual_workload_hours' => $this->calculationService->annualWorkload(
                    $lockedParticipant->assessment,
                    $data['frequency'],
                    $data['time_allocated_hours'],
                    WlaPeriodUnit::from($data['period_unit']),
                ),
                'sort_order' => ((int) ($maximumSortOrder ?? -1)) + 1,
            ]);
        });

        return to_route('wla.staff.show', $participant)->with('status', 'Aktivitas berhasil ditambahkan.');
    }

    public function updateActivity(
        UpdateWlaParticipantActivityRequest $request,
        WlaParticipant $participant,
        WlaParticipantActivity $activity,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $participant, $activity): void {
            $lockedParticipant = $this->lockedEditableParticipant($participant);
            $lockedActivity = $lockedParticipant->activities()
                ->whereKey($activity->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $data = $request->validated();
            $periodUnit = WlaPeriodUnit::from($data['period_unit']);
            $lockedActivity->update([
                ...$data,
                'period_unit' => $periodUnit,
                'annual_workload_hours' => $this->calculationService->annualWorkload(
                    $lockedParticipant->assessment,
                    $data['frequency'],
                    $data['time_allocated_hours'],
                    $periodUnit,
                ),
            ]);
        });

        return to_route('wla.staff.show', $participant)->with('status', 'Aktivitas berhasil diperbarui.');
    }

    public function destroyActivity(
        WlaParticipant $participant,
        WlaParticipantActivity $activity,
    ): RedirectResponse {
        DB::transaction(function () use ($participant, $activity): void {
            $lockedParticipant = $this->lockedEditableParticipant($participant);
            $lockedActivity = $lockedParticipant->activities()
                ->whereKey($activity->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedActivity->delete();
        });

        return to_route('wla.staff.show', $participant)->with('status', 'Aktivitas berhasil dihapus.');
    }

    public function submit(WlaParticipant $participant): RedirectResponse
    {
        DB::transaction(function () use ($participant): void {
            $lockedParticipant = WlaParticipant::query()->lockForUpdate()->findOrFail($participant->getKey());
            $lockedAssessment = $lockedParticipant->assessment()->lockForUpdate()->firstOrFail();
            $lockedParticipant->setRelation('assessment', $lockedAssessment);
            Gate::authorize('submit', $lockedParticipant);
            $activities = $lockedParticipant->activities()->lockForUpdate()->get();

            if ($activities->isEmpty()) {
                throw ValidationException::withMessages([
                    'submission' => 'Tambahkan minimal satu aktivitas sebelum dikirim ke Manager.',
                ]);
            }

            $lockedParticipant->update([
                'status' => WlaParticipantStatus::Submitted,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);
        });

        return to_route('wla.staff.show', $participant)->with('status', 'Pengisian WLA berhasil dikirim ke Manager.');
    }

    private function lockedEditableParticipant(WlaParticipant $participant): WlaParticipant
    {
        $lockedParticipant = WlaParticipant::query()->lockForUpdate()->findOrFail($participant->getKey());
        $lockedAssessment = $lockedParticipant->assessment()->lockForUpdate()->firstOrFail();
        $lockedParticipant->setRelation('assessment', $lockedAssessment);
        Gate::authorize('update', $lockedParticipant);

        if ($lockedAssessment->status !== WlaAssessmentStatus::Draft) {
            abort(403);
        }

        return $lockedParticipant;
    }
}

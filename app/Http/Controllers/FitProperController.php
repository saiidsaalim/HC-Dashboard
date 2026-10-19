<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FitProperActivity;
use App\Models\FitProperExaminer;
use App\Models\FitProperParticipant;
use App\Models\FitProperSetting;
use App\Models\User;
use App\Services\FitProperCalculationService;
use App\Services\FitProperService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FitProperController extends Controller
{
    public function __construct(public FitProperService $service) {}

    public function index(Request $request): View
    {
        $query = FitProperActivity::query();
        if (! $request->user()->canManageRbac()) {
            $query->where(fn ($builder) => $builder->whereHas('pics', fn ($builder) => $builder->where('users.id', $request->user()->id))
                ->orWhereHas('examiners', fn ($builder) => $builder->where('user_id', $request->user()->id)));
        }
        $real = (clone $query)->where('is_simulation', false)->count();
        $demo = (clone $query)->where('is_simulation', true)->count();
        $query->when($request->filled('q'), fn ($builder) => $builder->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('simulation'), fn ($builder) => $builder->where('is_simulation', $request->boolean('simulation')));

        return view('fit-proper.index', ['activities' => $query->withCount(['participants', 'examiners', 'examiners as submitted_count' => fn ($builder) => $builder->whereNotNull('submitted_at')])->latest()->paginate(12)->withQueryString(),
            'realCount' => $real, 'demoCount' => $demo, 'standard' => FitProperSetting::current()]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', FitProperActivity::class);
        $request->validate(['is_simulation' => 'nullable|boolean']);
        $activityRecord = $this->service->create($request->except(['demo_key']), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Kegiatan dibuat.', 'redirect' => route('fit-proper.show', $activityRecord)], 201);
        }

        return redirect()->route('fit-proper.show', $activityRecord);
    }

    public function show(Request $request, FitProperActivity $activity): View|RedirectResponse
    {
        Gate::authorize('view', $activity);
        if (! Gate::allows('manage', $activity)) {
            return redirect()->route('fit-proper.assess', [$activity, $activity->examiners()->where('user_id', $request->user()->id)->sole()]);
        }
        $activity->load(['participants', 'pics', 'examiners.scores']);

        return view('fit-proper.show', ['activity' => $activity, 'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'recap' => $activity->final_snapshot ?? $this->service->calculation->recap($activity),
            'histories' => $activity->histories()->orderByDesc('id')->get()]);
    }

    public function update(Request $request, FitProperActivity $activity): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $activity);
        $this->service->mutate($activity, function ($activityRecord) use ($request) {
            $activityRecord->update($this->service->activityData($request->all()));
            $this->service->history($activityRecord, 'Kegiatan diperbarui', $request->user());
        });

        return $this->mutationResponse($request, $activity, 'Kegiatan disimpan.');
    }

    public function destroy(Request $request, FitProperActivity $activity): RedirectResponse
    {
        Gate::authorize('manage', $activity);
        $this->service->mutate($activity, function ($activityRecord) {
            abort_if($activityRecord->participants()->exists() || $activityRecord->examiners()->exists(), 422, 'Hapus peserta dan penguji terlebih dahulu.');
            $activityRecord->delete();
        });

        return redirect()->route('fit-proper');
    }

    public function settings(Request $request): RedirectResponse
    {
        Gate::authorize('create', FitProperActivity::class);
        $standard = $this->service->standard($request->all());
        DB::transaction(function () use ($standard) {
            FitProperSetting::updateOrCreate(['id' => 1], ['standard' => $standard]);
        });

        return back()->with('success', 'Standar diperbarui. Berlaku untuk kegiatan baru.');
    }

    public function employees(Request $request): JsonResponse
    {
        $activity = FitProperActivity::findOrFail($request->integer('activity'));
        Gate::authorize('manage', $activity);
        $term = $request->validate(['q' => 'required|string|min:2|max:100'])['q'];

        return response()->json(Employee::query()->where(fn ($builder) => $builder->where('name', 'like', '%'.$term.'%')
            ->orWhere('sap', 'like', '%'.$term.'%')->orWhere('personal_number', 'like', '%'.$term.'%'))
            ->limit(20)->get(['id', 'name', 'sap', 'personal_number']));
    }

    public function participantForm(FitProperActivity $activity, ?FitProperParticipant $participant = null): View
    {
        Gate::authorize('manage', $activity);
        abort_unless($activity->status === 'Draft', 403);
        abort_if($participant && $participant->fit_proper_activity_id !== $activity->id, 404);

        return view('fit-proper.participant', ['activity' => $activity, 'participant' => $participant]);
    }

    public function saveParticipant(Request $request, FitProperActivity $activity, ?FitProperParticipant $participant = null): RedirectResponse
    {
        Gate::authorize('manage', $activity);
        $request->validate(['cv' => ['nullable', 'file', 'mimes:pdf', 'max:5120'], 'remove_cv' => 'nullable|boolean']);
        $this->service->mutate($activity, function ($activityRecord) use ($request, $participant) {
            $participantRecord = $this->service->participant($activityRecord, $request->all(), $participant, $request->user());
            if ($request->boolean('remove_cv')) {
                $participantRecord->update(['cv_path' => null]);
            }
            if ($request->hasFile('cv')) {
                $participantRecord->update(['cv_path' => $request->file('cv')->store('fit-proper/cv', 'local')]);
            }
        });

        return redirect()->route('fit-proper.show', $activity)->with('success', 'Peserta disimpan.');
    }

    public function deleteParticipant(Request $request, FitProperActivity $activity, FitProperParticipant $participant): RedirectResponse
    {
        Gate::authorize('manage', $activity);
        abort_unless($participant->fit_proper_activity_id === $activity->id, 404);
        $this->service->mutate($activity, function ($activityRecord) use ($participant, $request) {
            $participant->delete();
            $this->service->history($activityRecord, 'Peserta dihapus', $request->user(), ['participant_id' => $participant->id, 'name' => $participant->name]);
        });

        return back();
    }

    public function pics(Request $request, FitProperActivity $activity): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', FitProperActivity::class);
        $valid = $request->validate(['users' => 'required|array|min:1', 'users.*' => 'integer|distinct|exists:users,id']);
        $this->service->assignPics($activity, $valid['users'] ?? [], $request->user());

        return $this->mutationResponse($request, $activity, 'PIC ditambahkan.');
    }

    public function deletePic(Request $request, FitProperActivity $activity, User $user): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', FitProperActivity::class);
        $this->service->removePic($activity, $user, $request->user());

        return $this->mutationResponse($request, $activity, 'PIC dilepas.');
    }

    public function examiner(Request $request, FitProperActivity $activity): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $activity);
        [$examiner, $token] = $this->service->mutate($activity, function ($lockedActivity) use ($request) {
            $examiner = $this->service->examiner($lockedActivity, $request->all(), $request->user());

            return [$examiner, $this->service->token($lockedActivity, $examiner, $request->user())];
        });

        return $this->mutationResponse($request, $activity, 'Penguji ditambahkan. Salin tautan sekarang; aktif ketika kegiatan Berjalan.', [
            'examiner_id' => $examiner->id, 'examiner_link' => route('fit-proper.link', $token),
        ]);
    }

    public function deleteExaminer(Request $request, FitProperActivity $activity, FitProperExaminer $examiner): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $activity);
        abort_unless($examiner->activity_id === $activity->id, 404);
        $this->service->mutate($activity, function ($activityRecord) use ($examiner, $request) {
            $examiner->delete();
            $this->service->history($activityRecord, 'Penguji dan nilainya dihapus', $request->user(), ['examiner_id' => $examiner->id, 'name' => $examiner->name]);
        });

        return $this->mutationResponse($request, $activity, 'Penguji dan nilainya dihapus.');
    }

    public function token(Request $request, FitProperActivity $activity, FitProperExaminer $examiner): RedirectResponse|JsonResponse
    {
        Gate::authorize('manage', $activity);
        if ($request->boolean('revoke')) {
            $this->service->mutate($activity, function ($activityRecord) use ($examiner, $request) {
                abort_unless($examiner->activity_id === $activityRecord->id, 404);
                $examiner->update(['revoked_at' => now()]);
                $this->service->history($activityRecord, 'Tautan penguji dicabut', $request->user(), ['examiner_id' => $examiner->id]);
            }, false);

            return $this->mutationResponse($request, $activity, 'Tautan dicabut.', ['revoked_examiner_id' => $examiner->id]);
        }
        $token = $this->service->token($activity, $examiner, $request->user());

        return $this->mutationResponse($request, $activity, 'Salin tautan sekarang. Tautan berlaku 14 hari dan hanya aktif ketika Berjalan.', [
            'examiner_id' => $examiner->id, 'examiner_link' => route('fit-proper.link', $token),
        ]);
    }

    private function mutationResponse(Request $request, FitProperActivity $activity, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            $view = $this->show($request, $activity->fresh());

            return response()->json(['message' => $message, 'html' => $view->render()] + $extra)->header('Cache-Control', 'private, no-store');
        }

        return back()->with('success', $message)->with($extra);
    }

    public function transition(Request $request, FitProperActivity $activity): RedirectResponse|JsonResponse
    {
        Gate::authorize($request->input('status') === 'Selesai' ? 'finalize' : 'manage', $activity);
        $this->service->transition($activity, (string) $request->input('status'), $request->user());

        return $this->mutationResponse($request, $activity, 'Status diperbarui.', [
            'structure_changed' => true,
            'invalidate_links' => $request->input('status') !== 'Berjalan',
        ]);
    }

    private function internalExaminer(Request $request, FitProperActivity $activity, FitProperExaminer $examiner): void
    {
        abort_unless($examiner->activity_id === $activity->id && $examiner->user_id === $request->user()->id, 403);
    }

    private function assessmentView(FitProperExaminer $examiner, ?string $token = null): View|Response
    {
        try {
            $activity = $examiner->activity->load('participants');
            $scores = $examiner->scores()->whereIn('participant_id', $activity->participants->modelKeys())->get()->keyBy('participant_id');
        } catch (QueryException $exception) {
            return $this->assessmentLoadFailure($exception, $examiner);
        }

        return view('fit-proper.assess', ['activity' => $activity, 'examiner' => $examiner, 'token' => $token,
            'scores' => $scores, 'useOldScores' => (string) session()->getOldInput('_fit_proper_examiner_id') === (string) $examiner->id,
            'indicators' => FitProperCalculationService::INDICATORS]);
    }

    private function assessmentLoadFailure(QueryException $exception, ?FitProperExaminer $examiner = null): Response
    {
        Log::error('Gagal memuat formulir Fit & Proper.', ['examiner_id' => $examiner?->id, 'database_code' => $exception->getCode()]);

        return response()->view('fit-proper.assessment-error', ['retryUrl' => url()->current()], 503, ['Cache-Control' => 'private, no-store']);
    }

    public function assess(Request $request, FitProperActivity $activity, FitProperExaminer $examiner): View|Response
    {
        $this->internalExaminer($request, $activity, $examiner);

        return $this->assessmentView($examiner);
    }

    public function submit(Request $request, FitProperActivity $activity, FitProperExaminer $examiner): RedirectResponse|JsonResponse
    {
        $this->internalExaminer($request, $activity, $examiner);
        $request->merge(['_fit_proper_examiner_id' => $examiner->id]);
        $this->service->saveScores($examiner, $request->all(), $request->input('intent') === 'submit', $request->user());

        return $this->assessmentSavedResponse($request, $examiner);
    }

    public function link(string $token): View|Response
    {
        try {
            return $this->assessmentView($this->service->resolveToken($token), $token);
        } catch (QueryException $exception) {
            return $this->assessmentLoadFailure($exception);
        }
    }

    public function linkSubmit(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $examinerRecord = $this->service->resolveToken($token);
        $request->merge(['_fit_proper_examiner_id' => $examinerRecord->id]);
        $this->service->mutate($examinerRecord->activity, function () use ($request, $token) {
            $examiner = $this->service->resolveToken($token);
            $this->service->saveScores($examiner, $request->all(), $request->input('intent') === 'submit');
        }, false);

        return $this->assessmentSavedResponse($request, $examinerRecord);
    }

    private function assessmentSavedResponse(Request $request, FitProperExaminer $examiner): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Penilaian disimpan.', 'assessment_saved' => true, 'submitted' => $examiner->fresh()->submitted_at !== null])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('success', 'Penilaian disimpan.');
    }

    public function cv(Request $request, FitProperActivity $activity, FitProperParticipant $participant): StreamedResponse
    {
        Gate::authorize('view', $activity);
        abort_unless($participant->fit_proper_activity_id === $activity->id && $participant->cv_path, 404);

        return Storage::disk('local')->download($participant->cv_path, 'CV-'.$participant->internal_identity.'.pdf', ['Cache-Control' => 'private, no-store']);
    }

    public function linkCv(string $token, FitProperParticipant $participant): StreamedResponse
    {
        $examinerRecord = $this->service->resolveToken($token);
        abort_unless($participant->fit_proper_activity_id === $examinerRecord->activity_id && $participant->cv_path, 404);

        return Storage::disk('local')->download($participant->cv_path, 'CV-'.$participant->internal_identity.'.pdf', ['Cache-Control' => 'private, no-store']);
    }

    public function report(FitProperActivity $activity): View
    {
        Gate::authorize('manage', $activity);

        return view('fit-proper.print', ['activity' => $activity, 'recap' => $activity->final_snapshot ?? $this->service->calculation->recap($activity)]);
    }

    public function export(FitProperActivity $activity): StreamedResponse
    {
        Gate::authorize('manage', $activity);
        $recap = $activity->final_snapshot ?? $this->service->calculation->recap($activity);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Hasil gabungan');
        $sheet->fromArray([
            [$activity->name, $activity->is_simulation ? 'Data Simulasi' : 'Data Operasional', $activity->status === 'Selesai' ? 'Final' : 'Sementara'],
            ['Normalisasi FP terhadap peserta tertinggi. Perbandingan dan ranking menggunakan presisi 6 desimal.'],
            ['Standar', json_encode($recap['standard'])],
            ['Nama', 'SAP / Identitas', 'KPI', 'Asesmen', 'Rata FP', 'FP normal', 'Kontribusi KPI', 'Kontribusi asesmen', 'Kontribusi FP', 'Total', 'Rank FP', 'Rank gabungan', 'Kelulusan'],
        ], null, 'A1', true);
        $sheet->setCellValueExplicit('A1', $activity->name, DataType::TYPE_STRING);
        $line = 5;
        foreach ($recap['rows'] as $resultRow) {
            $values = [$resultRow['name'], $resultRow['sap'] ?: $resultRow['internal_identity'], $resultRow['kpi'], $resultRow['assessment'], $resultRow['raw'], $resultRow['fp'], $resultRow['contribution_kpi'], $resultRow['contribution_assessment'], $resultRow['contribution_fp'], $resultRow['total'], $resultRow['rank_fp'], $resultRow['rank_total'], $resultRow['passed'] === null ? 'Belum lengkap' : ($resultRow['passed'] ? 'Lulus' : 'Tidak lulus')];
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $line], $value ?? '', is_numeric($value) && $column > 1 && $column < 12 ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            $line++;
        }
        $detail = $book->createSheet()->setTitle('Rincian penguji');
        $detail->fromArray([['Peserta', 'Penguji', 'Dikirim', ...array_values(FitProperCalculationService::INDICATORS), 'Kompetensi inti', 'Uji kelayakan', 'Rata penguji', 'Panduan', 'Rekomendasi', 'Catatan']], null, 'A1');
        $line = 2;
        foreach ($recap['rows'] as $resultRow) {
            foreach ($resultRow['details'] as $examinerDetail) {
                $values = [$resultRow['name'], $examinerDetail['examiner_name'], $examinerDetail['submitted'] ? 'Ya' : 'Draft'];
                foreach (array_keys(FitProperCalculationService::INDICATORS) as $key) {
                    $values[] = $examinerDetail['indicators'][$key] ?? '';
                }
                array_push($values, $examinerDetail['calculation']['core'] ?? '', $examinerDetail['calculation']['eligibility'] ?? '', $examinerDetail['calculation']['average'] ?? '', $examinerDetail['calculation']['guidance'] ?? '', $examinerDetail['recommendation'], $examinerDetail['notes']);
                foreach ($values as $col => $value) {
                    $detail->setCellValueExplicit([$col + 1, $line], $value ?? '', DataType::TYPE_STRING);
                }
                $line++;
            }
        }

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'fit-proper-'.$activity->id.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}

<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FitProperActivity;
use App\Models\FitProperExaminer;
use App\Models\FitProperParticipant;
use App\Models\FitProperScore;
use App\Models\FitProperSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FitProperService
{
    public function __construct(public FitProperCalculationService $calculation) {}

    public function standard(array $data): array
    {
        $rules = [];
        foreach (['weight_kpi', 'weight_assessment', 'weight_fp'] as $key) {
            $rules[$key] = ['required', 'numeric', 'decimal:0,6', 'between:0,1'];
        }
        foreach (['max_kpi', 'max_assessment'] as $key) {
            $rules[$key] = ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:99999'];
        }
        $rules['passing_grade'] = ['required', 'numeric', 'decimal:0,6', 'between:0,100'];
        $valid = Validator::make($data, $rules)->validate();
        if (abs($valid['weight_kpi'] + $valid['weight_assessment'] + $valid['weight_fp'] - 1) > 0.000001) {
            throw ValidationException::withMessages(['weight_fp' => 'Total bobot harus 100% (jumlah pecahan desimal = 1).']);
        }

        return array_map(fn ($value) => (float) $value, $valid);
    }

    public function history(FitProperActivity $activityRecord, string $action, ?User $actor = null, array $context = []): void
    {
        $activityRecord->histories()->insert(['activity_id' => $activityRecord->id, 'user_id' => $actor?->id, 'action' => $action, 'context' => json_encode($context), 'created_at' => now()]);
    }

    public function create(array $data, ?User $actor): FitProperActivity
    {
        $valid = $this->activityData($data);

        return DB::transaction(function () use ($valid, $actor, $data) {
            $activityRecord = FitProperActivity::create($valid + ['status' => 'Draft', 'created_by' => $actor?->id, 'standard' => FitProperSetting::current(),
                'is_simulation' => (bool) ($data['is_simulation'] ?? false), 'demo_key' => $data['demo_key'] ?? null]);
            $this->history($activityRecord, 'Kegiatan dibuat', $actor);

            return $activityRecord;
        });
    }

    public function activityData(array $data): array
    {
        return Validator::make($data, ['name' => 'required|string|max:255', 'target_position' => 'required|string|max:255',
            'department' => 'nullable|string|max:255', 'scheduled_at' => 'required|date', 'description' => 'nullable|string|max:10000'])->validate();
    }

    public function mutate(FitProperActivity $activity, callable $callback, bool $draftOnly = true): mixed
    {
        return DB::transaction(function () use ($activity, $callback, $draftOnly) {
            $locked = FitProperActivity::query()->lockForUpdate()->findOrFail($activity->id);
            if ($locked->status === 'Selesai' || ($draftOnly && $locked->status !== 'Draft')) {
                throw ValidationException::withMessages(['status' => 'Kegiatan terkunci. Perubahan struktur hanya dapat dilakukan pada Draft.']);
            }

            return $callback($locked);
        });
    }

    public function participant(FitProperActivity $activity, array $data, ?FitProperParticipant $participant = null, ?User $actor = null): FitProperParticipant
    {
        return $this->mutate($activity, function ($activityRecord) use ($data, $participant, $actor) {
            if ($participant) {
                abort_unless($participant->fit_proper_activity_id === $activityRecord->id, 404);
            }
            $valid = Validator::make($data, [
                'employee_id' => ['nullable', 'integer', 'exists:employees,id', Rule::unique('fit_proper_participants')->where('fit_proper_activity_id', $activityRecord->id)->ignore($participant?->id)],
                'name' => 'required_without:employee_id|nullable|string|max:255', 'sap' => 'nullable|string|max:255',
                'birth_date' => 'nullable|date|before_or_equal:today',
                'kpi_year_1' => ['nullable', 'numeric', 'decimal:0,6', 'between:0,'.$activityRecord->standard['max_kpi']],
                'kpi_year_2' => ['nullable', 'numeric', 'decimal:0,6', 'between:0,'.$activityRecord->standard['max_kpi']],
                'assessment' => ['nullable', 'numeric', 'decimal:0,6', 'between:0,'.$activityRecord->standard['max_assessment']],
                'department' => 'nullable|string|max:255', 'current_position' => 'nullable|string|max:255', 'target_position' => 'nullable|string|max:255',
                'current_jg' => 'nullable|integer|between:0,99', 'target_jg' => 'nullable|integer|between:0,99',
                'education' => 'nullable|string|max:255', 'education_background' => 'nullable|string|max:10000',
                'experience' => 'nullable|string|max:10000', 'training' => 'nullable|string|max:10000',
                'achievement' => 'nullable|string|max:10000', 'assessment_notes' => 'nullable|string|max:10000',
            ])->validate();
            $profile = array_intersect_key($valid, array_flip(['department', 'current_position', 'target_position', 'current_jg', 'target_jg', 'education', 'education_background', 'experience', 'training', 'achievement', 'assessment_notes']));
            if (! empty($valid['employee_id'])) {
                $employee = Employee::findOrFail($valid['employee_id']);
                $valid['name'] = $employee->name ?: $employee->sap;
                $valid['sap'] = $employee->sap ?: $employee->personal_number;
                $valid['birth_date'] = $employee->birth_date?->toDateString();
                $profile['department'] = $profile['department'] ?? $employee->organizationDepartmentName();
                $profile['current_position'] = $profile['current_position'] ?? $employee->organizationPositionName();
                $profile['education'] = $profile['education'] ?? $employee->education ?? $employee->pendidikan;
            }
            $profile['gap_jg'] = isset($profile['current_jg'],$profile['target_jg']) ? $profile['current_jg'] - $profile['target_jg'] : null;
            $attributes = array_intersect_key($valid, array_flip(['employee_id', 'name', 'sap', 'birth_date', 'kpi_year_1', 'kpi_year_2', 'assessment']));
            $attributes['profile'] = $profile;
            $attributes['internal_identity'] = $participant?->internal_identity ?? (string) Str::uuid();
            if ($participant) {
                $participant->update($attributes);
            } else {
                $participant = $activityRecord->participants()->create($attributes);
            }
            $this->history($activityRecord, 'Peserta disimpan', $actor, ['participant_id' => $participant->id]);

            return $participant;
        });
    }

    public function examiner(FitProperActivity $activity, array $data, ?User $actor = null): FitProperExaminer
    {
        return $this->mutate($activity, function ($activityRecord) use ($data, $actor) {
            $valid = Validator::make($data, [
                'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('fit_proper_examiners')->where('activity_id', $activityRecord->id)],
                'name' => 'required_without:user_id|nullable|string|max:255',
            ])->validate();
            if (! empty($valid['user_id'])) {
                $valid['name'] = User::findOrFail($valid['user_id'])->name;
            }
            $examinerRecord = $activityRecord->examiners()->create($valid);
            $this->history($activityRecord, 'Penguji ditugaskan', $actor, ['examiner_id' => $examinerRecord->id, 'name' => $examinerRecord->name]);

            return $examinerRecord;
        });
    }

    public function assignPics(FitProperActivity $activity, array $userIds, ?User $actor): void
    {
        Validator::make(['users' => $userIds], ['users' => 'array', 'users.*' => 'integer|distinct|exists:users,id'])->validate();
        $this->mutate($activity, function ($activityRecord) use ($userIds, $actor) {
            if ($activityRecord->pics()->whereIn('users.id', $userIds)->exists()) {
                throw ValidationException::withMessages(['users' => 'PIC tersebut sudah ditugaskan pada kegiatan ini.']);
            }
            $activityRecord->pics()->syncWithoutDetaching($userIds);
            $this->history($activityRecord, 'Penugasan PIC diperbarui', $actor, ['users' => $userIds]);
        });
    }

    public function removePic(FitProperActivity $activity, User $pic, ?User $actor): void
    {
        $this->mutate($activity, function ($activityRecord) use ($pic, $actor) {
            abort_unless($activityRecord->pics()->where('users.id', $pic->id)->exists(), 404);
            $activityRecord->pics()->detach($pic->id);
            $this->history($activityRecord, 'PIC dilepas', $actor, ['user_id' => $pic->id, 'name' => $pic->name]);
        });
    }

    public function token(FitProperActivity $activity, FitProperExaminer $examiner, ?User $actor): string
    {
        return $this->mutate($activity, function ($activityRecord) use ($examiner, $actor) {
            abort_unless($examiner->activity_id === $activityRecord->id, 404);
            $token = Str::random(64);
            $examiner->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(14), 'revoked_at' => null]);
            $this->history($activityRecord, 'Tautan penguji diterbitkan ulang', $actor, ['examiner_id' => $examiner->id]);

            return $token;
        }, false);
    }

    public function resolveToken(string $token): FitProperExaminer
    {
        $examinerRecord = FitProperExaminer::where('token_hash', hash('sha256', $token))->first();
        abort_unless($examinerRecord, 404, 'Tautan penilaian tidak valid atau telah diganti.');
        abort_if($examinerRecord->activity->status === 'Selesai', 404, 'Kegiatan sudah selesai. Tautan penilaian tidak aktif lagi.');
        abort_if($examinerRecord->revoked_at, 404, 'Tautan penilaian telah dicabut.');
        abort_unless($examinerRecord->expires_at?->isFuture(), 404, 'Tautan penilaian sudah kedaluwarsa. Hubungi PIC untuk tautan baru.');
        abort_unless($examinerRecord->activity->status === 'Berjalan', 404, 'Kegiatan belum dibuka untuk penilaian. Tunggu informasi dari PIC.');

        return $examinerRecord;
    }

    public function saveScores(FitProperExaminer $examiner, array $data, bool $submit, ?User $actor = null): void
    {
        $this->mutate($examiner->activity, function ($activityRecord) use ($examiner, $data, $submit, $actor) {
            if ($activityRecord->status !== 'Berjalan') {
                throw ValidationException::withMessages(['status' => 'Penilaian hanya aktif ketika kegiatan Berjalan.']);
            }
            $examinerRecord = $activityRecord->examiners()->lockForUpdate()->findOrFail($examiner->id);
            if ($examinerRecord->submitted_at) {
                throw ValidationException::withMessages(['scores' => 'Penilaian sudah dikirim dan terkunci.']);
            }
            $participants = $activityRecord->participants()->get();
            $rules = ['scores' => 'required|array'];
            foreach ($participants as $participantRecord) {
                foreach (array_keys(FitProperCalculationService::INDICATORS) as $key) {
                    $rules['scores.'.$participantRecord->id.'.'.$key] = [$submit ? 'required' : 'nullable', 'numeric', 'decimal:0,6', 'between:0,5'];
                }
                $rules['scores.'.$participantRecord->id.'.recommendation'] = 'nullable|string|max:10000';
                $rules['scores.'.$participantRecord->id.'.notes'] = 'nullable|string|max:10000';
            }
            $valid = Validator::make($data, $rules)->validate();
            $allowedIds = $participants->pluck('id')->map(fn ($id) => (string) $id)->all();
            if (array_diff(array_map('strval', array_keys($data['scores'] ?? [])), $allowedIds)) {
                throw ValidationException::withMessages(['scores' => 'Peserta di luar kegiatan tidak diizinkan.']);
            }
            foreach ($participants as $participantRecord) {
                $row = $valid['scores'][$participantRecord->id] ?? [];
                $indicators = [];
                foreach (array_keys(FitProperCalculationService::INDICATORS) as $key) {
                    $indicators[$key] = isset($row[$key]) ? (float) $row[$key] : null;
                }
                FitProperScore::updateOrCreate(['participant_id' => $participantRecord->id, 'examiner_id' => $examinerRecord->id],
                    ['indicators' => $indicators, 'recommendation' => $row['recommendation'] ?? null, 'notes' => $row['notes'] ?? null]);
            }
            if ($submit) {
                $examinerRecord->update(['submitted_at' => now()]);
            }
            $this->history($activityRecord, $submit ? 'Penilaian dikirim' : 'Draft penilaian disimpan', $actor, ['examiner_id' => $examinerRecord->id, 'name' => $examinerRecord->name]);
        }, false);
    }

    public function transition(FitProperActivity $activity, string $target, ?User $actor = null): void
    {
        $this->mutate($activity, function ($activityRecord) use ($target, $actor) {
            $valid = ($activityRecord->status === 'Draft' && $target === 'Berjalan') || ($activityRecord->status === 'Berjalan' && in_array($target, ['Draft', 'Selesai'], true));
            if (! $valid) {
                throw ValidationException::withMessages(['status' => 'Transisi status tidak diizinkan.']);
            }
            if ($target === 'Berjalan' && (! $activityRecord->participants()->exists() || ! $activityRecord->examiners()->exists() || ! $activityRecord->pics()->exists())) {
                throw ValidationException::withMessages(['status' => 'Isi minimal satu peserta, PIC, dan penguji sebelum mulai.']);
            }
            if ($target === 'Draft') {
                $activityRecord->examiners()->update(['token_hash' => null, 'revoked_at' => now(), 'submitted_at' => null]);
            }
            if ($target === 'Selesai') {
                $recap = $this->calculation->recap($activityRecord);
                if (! $recap['complete']) {
                    throw ValidationException::withMessages(['status' => 'Finalisasi ditolak: lengkapi seluruh KPI, asesmen dan kiriman penguji; rata-rata tertinggi harus lebih dari nol.']);
                }
                $activityRecord->final_snapshot = $recap + ['activity' => $activityRecord->only(['name', 'target_position', 'department', 'scheduled_at', 'description', 'is_simulation', 'created_by']),
                    'pics' => $activityRecord->pics()->get(['users.id', 'users.name'])->toArray(), 'finalized_by' => $actor?->only(['id', 'name']), 'finalized_at' => now()->toIso8601String()];
                $activityRecord->finalized_at = now();
                $activityRecord->examiners()->update(['revoked_at' => now()]);
            }
            $activityRecord->status = $target;
            $activityRecord->save();
            $this->history($activityRecord, 'Status menjadi '.$target, $actor);
        }, false);
    }
}

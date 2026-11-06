<?php

namespace Database\Seeders;

use App\Enums\CriticalPositionCriticality;
use App\Enums\UserRole;
use App\Enums\WlaActivityOrigin;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaParticipantStatus;
use App\Enums\WlaPeriodUnit;
use App\Models\CriticalPosition;
use App\Models\DemotionApprovalConfig;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeDemotionBatch;
use App\Models\EmployeeImportBatch;
use App\Models\EmployeeMutation;
use App\Models\EmployeeMutationBatch;
use App\Models\EmployeePromotion;
use App\Models\EmployeePromotionBatch;
use App\Models\FitProperActivity;
use App\Models\FitProperSetting;
use App\Models\MutationApprovalConfig;
use App\Models\Position;
use App\Models\PromotionApprovalConfig;
use App\Models\Successor;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WorkCalendar;
use App\Models\WorkSchedule;
use App\Services\FitProperCalculationService;
use App\Services\FitProperService;
use App\Services\Personnel\EmployeeMovementFinalizationService;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WlaFinalizationService;
use App\Services\Workforce\WlaParticipantCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeatureDemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(
        PersonnelMovementSnapshotService $movementSnapshotService,
        EmployeeMovementFinalizationService $movementFinalizationService,
        FitProperService $fitProperService,
        WlaCalculationService $wlaCalculationService,
        WlaFinalizationService $wlaFinalizationService,
        WlaParticipantCalculationService $participantCalculationService,
    ): void {
        DB::transaction(function () use (
            $movementSnapshotService,
            $movementFinalizationService,
            $fitProperService,
            $wlaCalculationService,
            $wlaFinalizationService,
            $participantCalculationService,
        ): void {
            $this->seedWorkCalendars();

            $actor = User::query()->where('role', UserRole::SUPER_ADMIN->value)->first()
                ?? User::query()->first();

            if ($actor === null) {
                return;
            }

            $employees = $this->employees();

            if ($employees->isEmpty()) {
                return;
            }

            $this->seedApprovalConfigurations($actor);
            $this->seedEmployeeImportHistory($actor, $employees);
            $this->seedTalentPool($employees);
            $this->seedPersonnelMovements($actor, $employees, $movementSnapshotService);
            $this->seedFinalPersonnelMovements(
                $actor,
                $movementSnapshotService,
                $movementFinalizationService,
            );
            $employees = $this->employees();
            $criticalPositions = $this->seedCriticalPositions($actor);
            $this->seedSuccessors($criticalPositions, $employees);
            $this->seedFitProper($actor, $criticalPositions, $employees, $fitProperService);
            $this->seedWla(
                $actor,
                $wlaCalculationService,
                $participantCalculationService,
            );
            $this->seedFinalWla($actor, $wlaFinalizationService);
        });
    }

    /** @return Collection<int, Employee> */
    private function employees(): Collection
    {
        return Employee::query()
            ->whereNotNull('directorate_id')
            ->whereNotNull('department_id')
            ->whereNotNull('unit_id')
            ->whereNotNull('position_id')
            ->with([
                'organizationDirectorate',
                'organizationDepartment',
                'unit',
                'organizationSection',
                'organizationPosition',
                'user',
            ])
            ->orderBy('id')
            ->get();
    }

    private function seedWorkCalendars(): void
    {
        foreach (range(now()->year - 2, now()->year + 1) as $year) {
            WorkCalendar::query()->firstOrCreate(
                ['year' => $year],
                [
                    'total_days' => CarbonImmutable::create($year, 12, 31)->dayOfYear,
                    'total_weeks' => 52,
                    'annual_leave' => 12,
                    'national_holiday' => 17,
                    'common_leave' => 8,
                    'saturday_days' => 52,
                    'sunday_days' => 52,
                    'notes' => 'Kalender kerja contoh untuk pengisian fitur WLA.',
                    'active' => true,
                ],
            );
        }
    }

    private function seedApprovalConfigurations(User $actor): void
    {
        $approvers = User::query()
            ->whereIn('role', [UserRole::MANAGER->value, UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value])
            ->orderBy('id')
            ->limit(2)
            ->get();

        foreach ([
            MutationApprovalConfig::class,
            PromotionApprovalConfig::class,
            DemotionApprovalConfig::class,
        ] as $configClass) {
            if ($configClass::query()->exists()) {
                continue;
            }

            foreach ($approvers as $index => $approver) {
                $configClass::query()->create([
                    'step_order' => $index + 1,
                    'user_id' => $approver->id,
                    'updated_by' => $actor->id,
                ]);
            }
        }
    }

    /** @param Collection<int, Employee> $employees */
    private function seedEmployeeImportHistory(User $actor, Collection $employees): void
    {
        $importedEmployees = $employees->take(5)->values();
        $batch = EmployeeImportBatch::query()->firstOrCreate(
            ['original_file_name' => 'demo-master-pegawai.csv'],
            [
                'source_file' => 'employee-imports/demo-master-pegawai.csv',
                'source_type' => 'csv',
                'imported_by' => $actor->id,
                'imported_at' => now()->subDays(7),
                'status' => 'PROCESSED',
                'total_rows' => $importedEmployees->count(),
                'valid_rows' => $importedEmployees->count(),
                'invalid_rows' => 0,
                'approved_rows' => $importedEmployees->count(),
                'processed_rows' => $importedEmployees->count(),
            ],
        );

        foreach ($importedEmployees as $index => $employee) {
            $payload = [
                'SAP' => $employee->sap,
                'Personal Number' => $employee->personal_number,
                'Position' => $employee->organizationPosition?->name,
                'Department' => $employee->organizationDepartment?->name,
                'Unit' => $employee->unit?->name,
                'Email' => $employee->email,
            ];

            $batch->rows()->firstOrCreate(
                ['source_row' => $index + 2],
                [
                    'source_reference' => (string) $employee->sap,
                    'source_payload' => $payload,
                    'normalized_payload' => [
                        'sap' => $employee->sap,
                        'personal_number' => $employee->personal_number,
                        'position' => $employee->organizationPosition?->name,
                        'txt_dept' => $employee->organizationDepartment?->name,
                        'txt_biro' => $employee->unit?->name,
                        'email' => $employee->email,
                    ],
                    'validation_status' => 'VALID',
                    'mapping_status' => 'APPROVED',
                    'validation_errors' => null,
                    'mapping_errors' => null,
                    'processing_result' => [
                        'operation' => 'UPDATE',
                        'employee_id' => $employee->id,
                        'sap' => $employee->sap,
                    ],
                    'approved_by' => $actor->id,
                    'approved_at' => now()->subDays(7),
                    'processed_by' => $actor->id,
                    'processed_at' => now()->subDays(7),
                ],
            );
        }
    }

    /** @param Collection<int, Employee> $employees */
    private function seedTalentPool(Collection $employees): void
    {
        $readinessOptions = Employee::SUCCESSION_READINESS_OPTIONS;
        $updatedCount = 0;

        foreach (range(1, 5) as $talentBox) {
            $neededCount = max(0, 9 - $employees->where('talent_mapping', $talentBox)->count());
            $candidates = $employees
                ->filter(fn (Employee $employee): bool => $employee->talent_mapping === null)
                ->take($neededCount)
                ->values();

            foreach ($candidates as $employee) {
                $employee->update([
                    'talent_mapping' => $talentBox,
                    'succession_readiness' => $readinessOptions[$updatedCount % count($readinessOptions)],
                    'idp_percentage' => 55 + ($updatedCount % 10) * 4,
                ]);

                $updatedCount++;
            }
        }
    }

    /** @param Collection<int, Employee> $employees */
    private function seedPersonnelMovements(
        User $actor,
        Collection $employees,
        PersonnelMovementSnapshotService $snapshotService,
    ): void {
        $effectiveDate = now()->addMonth()->startOfMonth()->toDateString();
        $mutationBatch = EmployeeMutationBatch::query()->firstOrCreate(
            ['batch_number' => 'DEMO-MUT-001'],
            [
                'name' => 'Mutasi Antar Unit',
                'description' => 'Data contoh yang bersumber dari master pegawai dan organisasi.',
                'default_tmt' => $effectiveDate,
                'created_by' => $actor->id,
            ],
        );
        $promotionBatch = EmployeePromotionBatch::query()->firstOrCreate(
            ['batch_number' => 'DEMO-PRO-001'],
            [
                'name' => 'Promosi Pegawai Potensial',
                'description' => 'Data contoh yang bersumber dari talent pool pegawai.',
                'default_tmt' => $effectiveDate,
                'created_by' => $actor->id,
            ],
        );
        $demotionBatch = EmployeeDemotionBatch::query()->firstOrCreate(
            ['batch_number' => 'DEMO-DEM-001'],
            [
                'name' => 'Penyesuaian Level Organisasi',
                'description' => 'Data contoh untuk alur demosi pegawai.',
                'default_tmt' => $effectiveDate,
                'created_by' => $actor->id,
            ],
        );

        $usedEmployeeIds = [];
        foreach ($employees->take(3) as $employee) {
            $target = $this->targetPosition($employee, $employee->organizationPosition?->job_level);

            if ($target === null) {
                continue;
            }

            $usedEmployeeIds[] = $employee->id;
            $attributes = $this->movementAttributes(
                $employee,
                $target,
                $effectiveDate,
                (string) $employee->employee_subgroup,
                $snapshotService,
            );
            EmployeeMutation::query()->firstOrCreate(
                [
                    'employee_mutation_batch_id' => $mutationBatch->id,
                    'employee_id' => $employee->id,
                ],
                [...$attributes, 'verification_status' => 'Draft'],
            );
        }

        $promotionEmployees = $employees
            ->whereNotIn('id', $usedEmployeeIds)
            ->filter(fn (Employee $employee): bool => $this->promotedLevel($employee->employee_subgroup) !== null)
            ->take(3);
        foreach ($promotionEmployees as $employee) {
            $newLevel = $this->promotedLevel($employee->employee_subgroup);
            $target = $this->targetPosition($employee, $this->positionJobLevel($newLevel));

            if ($newLevel === null || $target === null) {
                continue;
            }

            $usedEmployeeIds[] = $employee->id;
            $attributes = $this->movementAttributes(
                $employee,
                $target,
                $effectiveDate,
                $newLevel,
                $snapshotService,
            );
            EmployeePromotion::query()->firstOrCreate(
                [
                    'employee_promotion_batch_id' => $promotionBatch->id,
                    'employee_id' => $employee->id,
                ],
                [...$attributes, 'verification_status' => 'Draft'],
            );
        }

        $demotionEmployees = $employees
            ->whereNotIn('id', $usedEmployeeIds)
            ->filter(fn (Employee $employee): bool => $this->demotedLevel($employee->employee_subgroup) !== null)
            ->take(3);
        foreach ($demotionEmployees as $employee) {
            $newLevel = $this->demotedLevel($employee->employee_subgroup);
            $target = $this->targetPosition($employee, $this->positionJobLevel($newLevel));

            if ($newLevel === null || $target === null) {
                continue;
            }

            $attributes = $this->movementAttributes(
                $employee,
                $target,
                $effectiveDate,
                $newLevel,
                $snapshotService,
            );
            EmployeeDemotion::query()->firstOrCreate(
                [
                    'employee_demotion_batch_id' => $demotionBatch->id,
                    'employee_id' => $employee->id,
                ],
                [...$attributes, 'verification_status' => 'Draft'],
            );
        }
    }

    private function seedFinalPersonnelMovements(
        User $actor,
        PersonnelMovementSnapshotService $snapshotService,
        EmployeeMovementFinalizationService $finalizationService,
    ): void {
        $effectiveDate = now()->subMonth()->startOfMonth()->toDateString();
        $mutationBatch = EmployeeMutationBatch::query()->firstOrCreate(
            ['batch_number' => 'DEMO-MUT-FINAL'],
            [
                'name' => 'Mutasi Final Contoh',
                'description' => 'Riwayat final untuk validasi ringkasan dan filter status.',
                'default_tmt' => $effectiveDate,
                'created_by' => $actor->id,
            ],
        );
        $promotionBatch = EmployeePromotionBatch::query()->firstOrCreate(
            ['batch_number' => 'DEMO-PRO-FINAL'],
            [
                'name' => 'Promosi Final Contoh',
                'description' => 'Riwayat final untuk validasi ringkasan dan filter status.',
                'default_tmt' => $effectiveDate,
                'created_by' => $actor->id,
            ],
        );
        $demotionBatch = EmployeeDemotionBatch::query()->firstOrCreate(
            ['batch_number' => 'DEMO-DEM-FINAL'],
            [
                'name' => 'Demosi Final Contoh',
                'description' => 'Riwayat final untuk validasi ringkasan dan filter status.',
                'default_tmt' => $effectiveDate,
                'created_by' => $actor->id,
            ],
        );

        $usedEmployeeIds = collect([
            EmployeeMutation::query()->pluck('employee_id'),
            EmployeePromotion::query()->pluck('employee_id'),
            EmployeeDemotion::query()->pluck('employee_id'),
        ])->flatten()->unique()->values()->all();
        $availableEmployees = $this->employees()->whereNotIn('id', $usedEmployeeIds);

        $mutationNeeded = max(0, 3 - $mutationBatch->mutations()->count());
        foreach ($availableEmployees->take($mutationNeeded) as $employee) {
            $target = $this->targetPosition($employee, $employee->organizationPosition?->job_level);

            if ($target === null) {
                continue;
            }

            $this->createFinalMovement(
                EmployeeMutation::class,
                'employee_mutation_batch_id',
                $mutationBatch->id,
                $employee,
                $target,
                (string) $employee->employee_subgroup,
                $effectiveDate,
                3,
                $actor,
                $snapshotService,
                $finalizationService,
            );
            $usedEmployeeIds[] = $employee->id;
        }

        $promotionEmployees = $availableEmployees
            ->whereNotIn('id', $usedEmployeeIds)
            ->filter(fn (Employee $employee): bool => $this->promotedLevel($employee->employee_subgroup) !== null)
            ->take(max(0, 3 - $promotionBatch->promotions()->count()));
        foreach ($promotionEmployees as $employee) {
            $newLevel = $this->promotedLevel($employee->employee_subgroup);
            $target = $this->targetPosition($employee, $this->positionJobLevel($newLevel));

            if ($newLevel === null || $target === null) {
                continue;
            }

            $this->createFinalMovement(
                EmployeePromotion::class,
                'employee_promotion_batch_id',
                $promotionBatch->id,
                $employee,
                $target,
                $newLevel,
                $effectiveDate,
                4,
                $actor,
                $snapshotService,
                $finalizationService,
            );
            $usedEmployeeIds[] = $employee->id;
        }

        $demotionEmployees = $availableEmployees
            ->whereNotIn('id', $usedEmployeeIds)
            ->filter(fn (Employee $employee): bool => $this->demotedLevel($employee->employee_subgroup) !== null)
            ->take(max(0, 3 - $demotionBatch->demotions()->count()));
        foreach ($demotionEmployees as $employee) {
            $newLevel = $this->demotedLevel($employee->employee_subgroup);
            $target = $this->targetPosition($employee, $this->positionJobLevel($newLevel));

            if ($newLevel === null || $target === null) {
                continue;
            }

            $this->createFinalMovement(
                EmployeeDemotion::class,
                'employee_demotion_batch_id',
                $demotionBatch->id,
                $employee,
                $target,
                $newLevel,
                $effectiveDate,
                3,
                $actor,
                $snapshotService,
                $finalizationService,
            );
            $usedEmployeeIds[] = $employee->id;
        }
    }

    /**
     * @param  class-string<EmployeeMutation|EmployeePromotion|EmployeeDemotion>  $movementClass
     */
    private function createFinalMovement(
        string $movementClass,
        string $batchForeignKey,
        int $batchId,
        Employee $employee,
        Position $target,
        string $newLevel,
        string $effectiveDate,
        int $approvalSteps,
        User $actor,
        PersonnelMovementSnapshotService $snapshotService,
        EmployeeMovementFinalizationService $finalizationService,
    ): void {
        $submittedAt = now()->subDays(10);
        $movement = $movementClass::query()->firstOrCreate(
            [
                $batchForeignKey => $batchId,
                'employee_id' => $employee->id,
            ],
            [
                ...$this->movementAttributes(
                    $employee,
                    $target,
                    $effectiveDate,
                    $newLevel,
                    $snapshotService,
                ),
                'verification_status' => 'Approved',
                'approval_round' => 1,
                'current_approval_step' => null,
                'submitted_by' => $actor->id,
                'submitted_at' => $submittedAt,
                'finalized_at' => now()->subDays(2),
            ],
        );

        if ($movement->wasRecentlyCreated) {
            $submissionSnapshot = $snapshotService->forApprovalRound(
                $movement,
                $actor,
                1,
                $submittedAt,
            );
            $approvers = User::query()
                ->whereIn('role', [UserRole::ADMIN->value, UserRole::MANAGER->value])
                ->orderBy('id')
                ->get();

            foreach (range(1, $approvalSteps) as $step) {
                $approver = $approvers->get(($step - 1) % max(1, $approvers->count())) ?? $actor;
                $movement->approvals()->create([
                    'approval_round' => 1,
                    'step_order' => $step,
                    'manager_id' => $approver->id,
                    'approver_name_snapshot' => $approver->name,
                    'approver_role_snapshot' => $approver->role,
                    'approver_number_snapshot' => $approver->employee?->sap,
                    'submission_snapshot' => $submissionSnapshot,
                    'status' => 'Approved',
                    'note' => 'Disetujui untuk data demonstrasi final.',
                    'approved_at' => now()->subDays(2),
                ]);
            }
        }

        if ($movement->verification_status === 'Approved' && $movement->employee_applied_at === null) {
            $finalizationService->apply($movement);
        }
    }

    private function targetPosition(Employee $employee, ?string $jobLevel): ?Position
    {
        $query = Position::query()
            ->where('active', true)
            ->whereKeyNot($employee->position_id)
            ->with(['unit.department.directorate', 'section'])
            ->orderBy('id');

        if ($jobLevel !== null && Position::isValidJobLevel($jobLevel)) {
            $target = (clone $query)->where('job_level', $jobLevel)->first();

            if ($target !== null) {
                return $target;
            }
        }

        return $query->first();
    }

    /** @return array<string, mixed> */
    private function movementAttributes(
        Employee $employee,
        Position $target,
        string $effectiveDate,
        string $newLevel,
        PersonnelMovementSnapshotService $snapshotService,
    ): array {
        $oldLevel = (string) $employee->employee_subgroup;

        return $snapshotService->fromEmployee($employee, [
            'new_directorate_id' => $target->unit->department->directorate_id,
            'new_department_id' => $target->unit->department_id,
            'new_unit_id' => $target->unit_id,
            'new_section_id' => $target->section_id,
            'new_position_id' => $target->id,
            'tmt' => $effectiveDate,
            'pg' => $employee->cost_center ?: 'DEMO',
            'jg_lama' => (string) $this->levelRank($oldLevel),
            'level_baru' => $newLevel,
            'jg_baru' => (string) $this->levelRank($newLevel),
        ]);
    }

    private function promotedLevel(?string $currentLevel): ?string
    {
        return match ($currentLevel) {
            'Senior Manager' => 'General Manager',
            'Manager' => 'Senior Manager',
            'Associate' => 'Manager',
            'Supervisor' => 'Associate',
            'Non-Eselon' => 'Supervisor',
            default => null,
        };
    }

    private function demotedLevel(?string $currentLevel): ?string
    {
        return match ($currentLevel) {
            'General Manager' => 'Senior Manager',
            'Senior Manager' => 'Manager',
            'Manager' => 'Associate',
            'Associate' => 'Supervisor',
            'Supervisor' => 'Non-Eselon',
            default => null,
        };
    }

    private function positionJobLevel(?string $employeeLevel): ?string
    {
        return match ($employeeLevel) {
            'General Manager', 'Senior Manager', 'Manager' => $employeeLevel,
            'Associate', 'Supervisor', 'Non-Eselon' => Position::JOB_LEVEL_STAFF,
            default => null,
        };
    }

    private function levelRank(string $level): int
    {
        return match ($level) {
            'General Manager' => 1,
            'Senior Manager' => 2,
            'Manager' => 3,
            'Associate' => 4,
            'Supervisor' => 5,
            default => 6,
        };
    }

    /** @return Collection<int, CriticalPosition> */
    private function seedCriticalPositions(User $actor): Collection
    {
        $ownedCount = CriticalPosition::query()
            ->where('notes', 'like', '[Feature Demo]%')
            ->count();
        $neededCount = max(0, 6 - $ownedCount);
        $criticalities = CriticalPositionCriticality::cases();

        Position::query()
            ->where('active', true)
            ->whereIn('job_level', [
                Position::JOB_LEVEL_GENERAL_MANAGER,
                Position::JOB_LEVEL_SENIOR_MANAGER,
                Position::JOB_LEVEL_MANAGER,
            ])
            ->whereDoesntHave('criticalPosition')
            ->whereHas('employees')
            ->orderByRaw("CASE job_level WHEN 'General Manager' THEN 1 WHEN 'Senior Manager' THEN 2 WHEN 'Manager' THEN 3 ELSE 4 END")
            ->orderBy('id')
            ->limit($neededCount)
            ->get()
            ->each(function (Position $position, int $index) use ($actor, $criticalities, $ownedCount): void {
                CriticalPosition::query()->create([
                    'position_id' => $position->id,
                    'criticality' => $criticalities[($ownedCount + $index) % count($criticalities)],
                    'assessment_reason' => 'Posisi berdampak langsung pada kesinambungan proses bisnis dan pencapaian target organisasi.',
                    'assessed_at' => now()->subMonth()->toDateString(),
                    'review_due_at' => now()->addMonths(11)->toDateString(),
                    'assessed_by' => $actor->id,
                    'notes' => '[Feature Demo] Bersumber dari master posisi dan incumbent aktif.',
                ]);
            });

        return CriticalPosition::query()
            ->where('notes', 'like', '[Feature Demo]%')
            ->with('position.unit.department.directorate')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, CriticalPosition>  $criticalPositions
     * @param  Collection<int, Employee>  $employees
     */
    private function seedSuccessors(Collection $criticalPositions, Collection $employees): void
    {
        $candidates = $employees
            ->filter(fn (Employee $employee): bool => in_array((int) $employee->talent_mapping, [7, 8, 9], true))
            ->values();

        foreach ($criticalPositions as $positionIndex => $criticalPosition) {
            $position = $criticalPosition->position;
            $positionCandidates = $candidates
                ->reject(fn (Employee $employee): bool => $employee->position_id === $position->id)
                ->slice($positionIndex * 2, 2)
                ->values();

            foreach ($positionCandidates as $candidateIndex => $employee) {
                $performanceScore = min(99, 76 + ((int) $employee->talent_mapping * 2) + $candidateIndex);
                Successor::query()->firstOrCreate(
                    [
                        'target_position_id' => $position->id,
                        'sap' => (string) $employee->sap,
                    ],
                    [
                        'name' => $this->employeeName($employee),
                        'current_position' => $employee->organizationPosition?->name ?: $employee->position,
                        'position_history' => [[
                            'period' => ($employee->hiring?->year ?? now()->subYears(5)->year).' - '.now()->year,
                            'position' => $employee->organizationPosition?->name ?: $employee->position,
                        ]],
                        'birth_date' => $employee->birth_date,
                        'last_education' => $employee->education ?? $employee->pendidikan ?? 'Belum tersedia',
                        'talent_box' => (int) $employee->talent_mapping,
                        'position' => $employee->organizationPosition?->name ?: $employee->position,
                        'talent_mapping' => (string) $employee->talent_mapping,
                        'succession_readiness' => $employee->succession_readiness
                            ?? Employee::SUCCESSION_READINESS_OPTIONS[$candidateIndex],
                        'recommendation' => '[Feature Demo] Kandidat dipilih dari talent pool pegawai aktif.',
                        'targeted_band' => $position->job_level,
                        'performance_year' => now()->year,
                        'performance_score' => $performanceScore,
                        'performance_category' => $performanceScore >= 90 ? 'Sangat Baik' : 'Baik',
                        'mbti_type' => $candidateIndex === 0 ? 'ENTJ' : 'INTJ',
                        'mbti_description' => 'Profil kepemimpinan mendukung kebutuhan posisi target.',
                        'top_job_family_1' => 'Leadership',
                        'top_job_family_2' => 'Business Management',
                        'idp' => (int) round((float) ($employee->idp_percentage ?? 70)),
                        'education' => $employee->education ?? $employee->pendidikan,
                        'tenure' => $employee->hiring?->diffForHumans(now(), true),
                        'directorate' => $employee->organizationDirectorate?->name,
                    ],
                );
            }
        }
    }

    /**
     * @param  Collection<int, CriticalPosition>  $criticalPositions
     * @param  Collection<int, Employee>  $employees
     */
    private function seedFitProper(
        User $actor,
        Collection $criticalPositions,
        Collection $employees,
        FitProperService $fitProperService,
    ): void {
        FitProperSetting::query()->firstOrCreate(
            ['id' => 1],
            ['standard' => FitProperSetting::DEFAULT_STANDARD],
        );

        foreach ($criticalPositions->take(5)->values() as $index => $criticalPosition) {
            $position = $criticalPosition->position;
            $targetStatus = match (true) {
                $index < 2 => 'Draft',
                $index === 2 => 'Berjalan',
                default => 'Selesai',
            };
            $activity = FitProperActivity::query()->firstOrCreate(
                ['demo_key' => 'feature-demo-fit-proper-'.($index + 1)],
                [
                    'name' => 'Fit & Proper '.$position->name,
                    'target_position' => $position->name,
                    'department' => $position->unit->department->name,
                    'scheduled_at' => now()->addWeeks($index + 1)->toDateString(),
                    'description' => 'Kegiatan contoh menggunakan kandidat dari master pegawai.',
                    'status' => 'Draft',
                    'created_by' => $actor->id,
                    'standard' => FitProperSetting::current(),
                    'is_simulation' => false,
                ],
            );

            if ($activity->status === 'Draft') {
                $activity->pics()->syncWithoutDetaching([$actor->id]);
                $activity->examiners()->firstOrCreate(
                    ['user_id' => $actor->id],
                    ['name' => $actor->name],
                );

                $participants = $employees
                    ->where('department_id', $position->unit->department_id)
                    ->reject(fn (Employee $employee): bool => $employee->position_id === $position->id)
                    ->take(4);

                if ($participants->isEmpty()) {
                    $participants = $employees->take(4);
                }

                foreach ($participants->values() as $participantIndex => $employee) {
                    $participant = $activity->participants()->firstOrCreate(
                        ['employee_id' => $employee->id],
                        [
                            'internal_identity' => (string) Str::uuid(),
                            'name' => $this->employeeName($employee),
                            'sap' => $employee->sap,
                            'birth_date' => $employee->birth_date,
                            'profile' => [
                                'department' => $employee->organizationDepartment?->name,
                                'current_position' => $employee->organizationPosition?->name,
                                'target_position' => $position->name,
                                'education' => $employee->education ?? $employee->pendidikan,
                            ],
                            'kpi_year_1' => 3.7 + ($participantIndex * 0.1),
                            'kpi_year_2' => 3.8 + ($participantIndex * 0.1),
                            'assessment' => 3.2 + ($participantIndex * 0.1),
                        ],
                    );

                    $participant->update([
                        'name' => $this->employeeName($employee),
                        'sap' => $employee->sap,
                        'birth_date' => $employee->birth_date,
                    ]);
                }

                if ($targetStatus !== 'Draft') {
                    $fitProperService->transition($activity, 'Berjalan', $actor);
                    $activity->refresh();
                }
            }

            if ($targetStatus === 'Selesai' && $activity->status === 'Berjalan') {
                $examiner = $activity->examiners()->firstOrFail();
                $scores = $activity->participants()
                    ->orderBy('id')
                    ->get()
                    ->mapWithKeys(function ($participant, int $participantIndex): array {
                        $indicatorScore = min(5, 3 + ($participantIndex % 3));

                        return [$participant->id => [
                            ...array_fill_keys(
                                array_keys(FitProperCalculationService::INDICATORS),
                                $indicatorScore,
                            ),
                            'recommendation' => $indicatorScore >= 4
                                ? 'Direkomendasikan untuk posisi target.'
                                : 'Direkomendasikan dengan rencana pengembangan.',
                            'notes' => 'Nilai demonstrasi untuk validasi rekap final.',
                        ]];
                    })->all();

                if ($examiner->submitted_at === null) {
                    $fitProperService->saveScores($examiner, ['scores' => $scores], true, $actor);
                }

                $fitProperService->transition($activity, 'Selesai', $actor);
            }
        }
    }

    private function seedWla(
        User $actor,
        WlaCalculationService $calculationService,
        WlaParticipantCalculationService $participantCalculationService,
    ): void {
        $calendar = WorkCalendar::query()->where('year', now()->year)->first();
        $schedule = WorkSchedule::query()->where('code', 'DAYSHIFT')->first()
            ?? WorkSchedule::query()->where('active', true)->first();

        if ($calendar === null || $schedule === null) {
            return;
        }

        $positions = Position::query()
            ->where('active', true)
            ->whereHas('employees')
            ->with(['unit.department', 'employees' => fn ($query) => $query->orderBy('id')])
            ->withCount('employees')
            ->orderByDesc('employees_count')
            ->orderBy('id')
            ->limit(3)
            ->get();
        $activities = [
            ['name' => 'Perencanaan dan koordinasi pekerjaan', 'frequency' => '1.00', 'unit' => WlaPeriodUnit::Day, 'hours' => '2.00'],
            ['name' => 'Pelaksanaan aktivitas operasional', 'frequency' => '1.00', 'unit' => WlaPeriodUnit::Day, 'hours' => '4.00'],
            ['name' => 'Monitoring dan evaluasi hasil kerja', 'frequency' => '2.00', 'unit' => WlaPeriodUnit::Week, 'hours' => '2.00'],
            ['name' => 'Penyusunan laporan kinerja', 'frequency' => '1.00', 'unit' => WlaPeriodUnit::Month, 'hours' => '6.00'],
        ];

        foreach ($positions as $index => $position) {
            $assessment = WlaAssessment::query()->firstOrCreate(
                ['assessment_code' => sprintf('DEMO-WLA-%d-%02d', now()->year, $index + 1)],
                [
                    'period' => now()->year,
                    'department_id' => $position->unit->department_id,
                    'unit_id' => $position->unit_id,
                    'position_id' => $position->id,
                    'work_schedule_id' => $schedule->id,
                    'work_calendar_id' => $calendar->id,
                    'efficiency_factor' => config('wla.default_efficiency_factor', '0.90'),
                    'status' => WlaAssessmentStatus::Draft,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ],
            );

            if ($assessment->status !== WlaAssessmentStatus::Draft) {
                continue;
            }

            foreach ($activities as $activityIndex => $activityData) {
                $assessment->activities()->updateOrCreate(
                    ['sort_order' => $activityIndex + 1],
                    [
                        'origin' => WlaActivityOrigin::Management,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                        'activity_name' => $activityData['name'],
                        'frequency' => $activityData['frequency'],
                        'period_unit' => $activityData['unit']->value,
                        'frequency_unit' => $activityData['unit']->value,
                        'volume' => '1.00',
                        'volume_unit' => $activityData['unit']->value,
                        'time_allocated' => $activityData['hours'],
                        'time_unit' => 'Hour',
                        'time_allocated_hours' => $activityData['hours'],
                        'notes' => 'Aktivitas contoh berdasarkan uraian pekerjaan posisi.',
                    ],
                );
            }

            foreach ($position->employees->take(3) as $employee) {
                $participant = $assessment->participants()->firstOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'assigned_by' => $actor->id,
                        'status' => WlaParticipantStatus::Draft,
                    ],
                );

                foreach (array_slice($activities, 0, 3) as $activityIndex => $activityData) {
                    $participant->activities()->updateOrCreate(
                        ['sort_order' => $activityIndex + 1],
                        [
                            'activity_name' => $activityData['name'],
                            'frequency' => $activityData['frequency'],
                            'period_unit' => $activityData['unit'],
                            'time_allocated_hours' => $activityData['hours'],
                            'annual_workload_hours' => $participantCalculationService->annualWorkload(
                                $assessment,
                                $activityData['frequency'],
                                $activityData['hours'],
                                $activityData['unit'],
                            ),
                            'notes' => 'Aktivitas peserta yang diturunkan dari posisi organisasi.',
                        ],
                    );
                }
            }

            $calculationService->recalculate($assessment);
        }
    }

    private function seedFinalWla(User $actor, WlaFinalizationService $finalizationService): void
    {
        $schedule = WorkSchedule::query()->where('code', 'DAYSHIFT')->first()
            ?? WorkSchedule::query()->where('active', true)->first();

        if ($schedule === null) {
            return;
        }

        $positions = Position::query()
            ->where('active', true)
            ->whereHas('employees')
            ->with('unit.department')
            ->withCount('employees')
            ->orderBy('employees_count')
            ->orderBy('id')
            ->get();
        $shortagePosition = $positions->first();
        $balancedPosition = $positions
            ->first(fn (Position $position): bool => $position->id !== $shortagePosition?->id);
        $surplusPosition = $positions
            ->sortByDesc('employees_count')
            ->first(fn (Position $position): bool => $position->employees_count >= 3
                && ! in_array($position->id, [
                    $shortagePosition?->id,
                    $balancedPosition?->id,
                ], true));
        $scenarios = collect([
            ['status' => 'shortage', 'position' => $shortagePosition],
            ['status' => 'balanced', 'position' => $balancedPosition],
            ['status' => 'surplus', 'position' => $surplusPosition],
        ])->filter(fn (array $scenario): bool => $scenario['position'] instanceof Position)->values();

        foreach (range(now()->year - 2, now()->year) as $year) {
            $calendar = WorkCalendar::query()->where('year', $year)->first();

            if ($calendar === null) {
                continue;
            }

            foreach ($scenarios as $index => $scenario) {
                /** @var Position $position */
                $position = $scenario['position'];
                $actualEmployees = (int) $position->employees_count;
                $recommendedEmployees = match ($scenario['status']) {
                    'shortage' => $actualEmployees + 2,
                    'balanced' => $actualEmployees,
                    default => max(1, $actualEmployees - 2),
                };
                $dailyWorkloadHours = ($recommendedEmployees * 6.3) - 0.1;
                $assessment = WlaAssessment::query()->firstOrCreate(
                    ['assessment_code' => sprintf('DEMO-WLA-FINAL-%d-%02d', $year, $index + 1)],
                    [
                        'period' => $year,
                        'department_id' => $position->unit->department_id,
                        'unit_id' => $position->unit_id,
                        'position_id' => $position->id,
                        'work_schedule_id' => $schedule->id,
                        'work_calendar_id' => $calendar->id,
                        'efficiency_factor' => config('wla.default_efficiency_factor', '0.90'),
                        'status' => WlaAssessmentStatus::Draft,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ],
                );

                if ($assessment->status !== WlaAssessmentStatus::Draft) {
                    continue;
                }

                foreach ([
                    ['name' => 'Koordinasi pekerjaan harian', 'hours' => 2.0],
                    ['name' => 'Pelaksanaan beban kerja utama', 'hours' => $dailyWorkloadHours - 2.0],
                ] as $activityIndex => $activityData) {
                    $assessment->activities()->updateOrCreate(
                        ['sort_order' => $activityIndex + 1],
                        [
                            'origin' => WlaActivityOrigin::Management,
                            'created_by' => $actor->id,
                            'updated_by' => $actor->id,
                            'activity_name' => $activityData['name'],
                            'frequency' => '1.00',
                            'period_unit' => WlaPeriodUnit::Day->value,
                            'frequency_unit' => WlaPeriodUnit::Day->value,
                            'volume' => '1.00',
                            'volume_unit' => WlaPeriodUnit::Day->value,
                            'time_allocated' => number_format($activityData['hours'], 2, '.', ''),
                            'time_unit' => 'Hour',
                            'time_allocated_hours' => number_format($activityData['hours'], 2, '.', ''),
                            'notes' => 'Aktivitas final untuk skenario headcount '.$scenario['status'].'.',
                        ],
                    );
                }

                $finalizationService->finalize($assessment, $actor);
            }
        }
    }

    private function employeeName(Employee $employee): string
    {
        return trim((string) ($employee->personal_number ?: $employee->name ?: $employee->sap));
    }
}

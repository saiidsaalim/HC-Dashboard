<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FitProperActivity;
use App\Models\FitProperParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FitProperSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_fit_proper_schema_uses_the_final_contract(): void
    {
        foreach ([
            'fit_proper_settings',
            'fit_proper_activities',
            'fit_proper_pics',
            'fit_proper_participants',
            'fit_proper_examiners',
            'fit_proper_scores',
            'fit_proper_histories',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasColumns('fit_proper_activities', [
            'target_position',
            'scheduled_at',
            'standard',
            'final_snapshot',
            'finalized_at',
            'is_simulation',
            'demo_key',
        ]));
        $this->assertTrue(Schema::hasColumns('fit_proper_participants', [
            'fit_proper_activity_id',
            'employee_id',
            'internal_identity',
            'name',
            'sap',
            'birth_date',
            'profile',
            'assessment',
        ]));
        $this->assertFalse(Schema::hasColumn('fit_proper_participants', 'activity_id'));
        $this->assertFalse(Schema::hasTable('fit_proper_activity_user'));
    }

    public function test_final_participant_relations_support_employee_and_manual_candidates(): void
    {
        $creator = User::factory()->create();
        $employee = Employee::query()->forceCreate(['sap' => 'FP-FINAL-001', 'name' => 'Candidate']);
        $activity = FitProperActivity::query()->create([
            'name' => 'Final Schema Activity',
            'target_position' => 'Manager',
            'scheduled_at' => '2026-11-01',
            'created_by' => $creator->id,
            'standard' => [],
        ]);
        $employeeParticipant = $activity->participants()->create([
            'employee_id' => $employee->id,
            'internal_identity' => (string) Str::uuid(),
            'name' => $employee->name,
            'profile' => [],
        ]);
        $manualParticipant = $activity->participants()->create([
            'internal_identity' => (string) Str::uuid(),
            'name' => 'Manual Candidate',
            'profile' => [],
        ]);

        $this->assertSame($activity->id, $employeeParticipant->activity->id);
        $this->assertSame($employee->id, $employeeParticipant->employee_id);
        $this->assertNull($manualParticipant->employee_id);
        $this->assertSame(2, FitProperParticipant::query()->count());
    }
}

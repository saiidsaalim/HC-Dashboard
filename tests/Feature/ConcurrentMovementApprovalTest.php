<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\MutationApprovalConfig;
use App\Models\User;
use App\Services\Personnel\MovementApprovalWorkflowService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentMovementApprovalTest extends TestCase
{
    public function test_concurrent_approval_is_serialized_without_duplicate_progress(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Test concurrency ini khusus database testing MySQL.');
        }

        $suffix = str()->uuid()->toString();
        $employee = Employee::query()->create(['sap' => "CONCURRENT-{$suffix}"]);
        $submitter = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $approvers = User::factory()->count(3)->create(['role' => UserRole::MANAGER->value]);

        foreach ($approvers as $index => $approver) {
            MutationApprovalConfig::query()->create([
                'step_order' => $index + 1,
                'user_id' => $approver->id,
            ]);
        }

        $movement = EmployeeMutation::query()->create([
            'employee_id' => $employee->id,
            'sap' => $employee->sap,
            'nama' => 'Pegawai Concurrent',
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Jabatan Lama',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Jabatan Baru',
            'tmt' => today()->addMonth(),
        ]);
        app(MovementApprovalWorkflowService::class)->submit($movement, $submitter);

        $approvalCode = sprintf(
            <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app(\App\Services\Personnel\ApprovalService::class)->approve(\App\Models\EmployeeMutation::findOrFail(%d), \App\Models\User::findOrFail(%d));
PHP,
            $movement->id,
            $approvers[0]->id,
        );
        $firstProcess = new Process([PHP_BINARY, '-r', $approvalCode], base_path());
        $secondProcess = new Process([PHP_BINARY, '-r', $approvalCode], base_path());

        try {
            $firstProcess->start();
            $secondProcess->start();
            $firstProcess->wait();
            $secondProcess->wait();

            $this->assertTrue($firstProcess->isSuccessful(), $firstProcess->getOutput().$firstProcess->getErrorOutput());
            $this->assertTrue($secondProcess->isSuccessful(), $secondProcess->getOutput().$secondProcess->getErrorOutput());
            $movement->refresh();
            $this->assertSame(2, $movement->current_approval_step);
            $this->assertSame(1, $movement->approvals()->where('status', 'Approved')->count());
            $this->assertSame(2, $movement->approvals()->where('status', 'Pending')->count());
            $this->assertDatabaseCount('mutation_approvals', 3);
        } finally {
            $movement->delete();
            MutationApprovalConfig::query()->whereIn('user_id', $approvers->modelKeys())->delete();
            User::query()->whereIn('id', [...$approvers->modelKeys(), $submitter->id])->delete();
            $employee->delete();
        }
    }
}

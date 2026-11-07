<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendMovementApprovalNotification;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\MovementApprovalDelivery;
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
        $notificationQueue = "concurrent-approval-{$suffix}";
        $queueTable = (string) config('queue.connections.database.table', 'jobs');
        config([
            'movement-notifications.connection' => 'database',
            'movement-notifications.queue' => $notificationQueue,
            'queue.connections.database.connection' => DB::getDefaultConnection(),
        ]);
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
config(['queue.connections.database.connection' => \Illuminate\Support\Facades\DB::getDefaultConnection()]);
app(\App\Services\Personnel\ApprovalService::class)->approve(\App\Models\EmployeeMutation::findOrFail(%d), \App\Models\User::findOrFail(%d));
PHP,
            $movement->id,
            $approvers[0]->id,
        );
        $processEnvironment = [
            'MOVEMENT_APPROVAL_QUEUE_CONNECTION' => 'database',
            'MOVEMENT_APPROVAL_QUEUE' => $notificationQueue,
        ];
        $firstProcess = new Process([PHP_BINARY, '-r', $approvalCode], base_path(), $processEnvironment);
        $secondProcess = new Process([PHP_BINARY, '-r', $approvalCode], base_path(), $processEnvironment);

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

            $deliveries = MovementApprovalDelivery::query()
                ->where('movement_type', 'mutation')
                ->where('movement_id', $movement->id)
                ->orderBy('step_order')
                ->get();
            $this->assertCount(2, $deliveries);
            $this->assertSame([1, 1], $deliveries->pluck('approval_round')->all());
            $this->assertSame([1, 2], $deliveries->pluck('step_order')->all());
            $this->assertSame([$approvers[0]->id, $approvers[1]->id], $deliveries->pluck('recipient_id')->all());
            $this->assertSame(['queued', 'queued'], $deliveries->pluck('status')->all());

            $queuedDeliveryIds = DB::table($queueTable)->where('queue', $notificationQueue)
                ->pluck('payload')
                ->map(function (string $payload): int {
                    $jobPayload = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
                    $this->assertSame(SendMovementApprovalNotification::class, $jobPayload['data']['commandName']);
                    $job = unserialize($jobPayload['data']['command'], ['allowed_classes' => [SendMovementApprovalNotification::class]]);
                    $this->assertInstanceOf(SendMovementApprovalNotification::class, $job);

                    return $job->deliveryId;
                })->all();
            $this->assertCount(2, $queuedDeliveryIds);
            $this->assertEqualsCanonicalizing($deliveries->modelKeys(), $queuedDeliveryIds);
        } finally {
            DB::table($queueTable)->where('queue', $notificationQueue)->delete();
            MovementApprovalDelivery::query()->where('movement_type', 'mutation')->where('movement_id', $movement->id)->delete();
            $movement->delete();
            MutationApprovalConfig::query()->whereIn('user_id', $approvers->modelKeys())->delete();
            User::query()->whereIn('id', [...$approvers->modelKeys(), $submitter->id])->delete();
            $employee->delete();
        }
    }
}

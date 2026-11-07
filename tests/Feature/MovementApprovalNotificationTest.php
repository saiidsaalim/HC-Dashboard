<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendMovementApprovalNotification;
use App\Models\DemotionApprovalConfig;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeDemotionBatch;
use App\Models\EmployeeMutation;
use App\Models\EmployeeMutationBatch;
use App\Models\EmployeePromotion;
use App\Models\EmployeePromotionBatch;
use App\Models\MovementApprovalDelivery;
use App\Models\MutationApprovalConfig;
use App\Models\Permission;
use App\Models\Position;
use App\Models\PromotionApprovalConfig;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionSynchronizer;
use App\Services\Authorization\RolePermissionCache;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\MovementApprovalNotificationService;
use App\Services\Personnel\MovementApprovalWorkflowService;
use App\Services\Personnel\PromotionApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

class MovementApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('movementTypes')]
    public function test_submit_only_queues_the_active_approver_and_worker_sends_one_email(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();

        $this->actingAs($fixture['submitter'])
            ->post(route($fixture['route'].'.submit', $fixture['movement']))
            ->assertRedirect();

        $delivery = MovementApprovalDelivery::query()->sole();
        $this->assertSame($type, $delivery->movement_type);
        $this->assertSame($fixture['movement']->id, $delivery->movement_id);
        $this->assertSame(1, $delivery->approval_round);
        $this->assertSame(1, $delivery->step_order);
        $this->assertSame($fixture['approvers'][0]->id, $delivery->recipient_id);
        $this->assertSame('queued', $delivery->status);
        $this->assertNotNull($delivery->queued_at);
        Queue::assertPushedOn('approval-mail', SendMovementApprovalNotification::class,
            fn (SendMovementApprovalNotification $job): bool => $job->deliveryId === $delivery->id
                && $job->connection === 'database');
        Queue::assertPushed(SendMovementApprovalNotification::class, 1);
        $this->assertCount(0, $transport->messages());

        $this->handle($delivery);

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->sent_at);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertSame([$fixture['approvers'][0]->email], array_map(
            static fn (Address $address): string => $address->getAddress(), $message->getTo(),
        ));
        $this->assertSame([], $message->getCc());
        $this->assertSame([], $message->getBcc());

        $this->handle($delivery);
        $this->assertCount(1, $transport->messages());
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    #[DataProvider('movementTypes')]
    public function test_drafts_and_duplicate_submissions_do_not_create_extra_deliveries(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);

        app(MovementApprovalNotificationService::class)->recordActiveStep($fixture['movement']);
        $this->assertDatabaseCount('movement_approval_deliveries', 0);
        Queue::assertNothingPushed();

        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.submit', $fixture['movement']))
            ->assertRedirect();
        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.submit', $fixture['movement']))
            ->assertForbidden();
        app(MovementApprovalNotificationService::class)->recordActiveStep($fixture['movement']->fresh());
        app(MovementApprovalNotificationService::class)->recordActiveStep($fixture['movement']->fresh());

        $this->assertDatabaseCount('movement_approval_deliveries', 1);
        $this->assertSame(1, $fixture['movement']->fresh()->approval_round);
        Queue::assertPushed(SendMovementApprovalNotification::class, 1);
    }

    #[DataProvider('movementTypes')]
    public function test_only_advancing_to_a_real_next_step_creates_another_delivery(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $this->submit($fixture);

        foreach ($fixture['approvers'] as $index => $approver) {
            $this->approve($fixture['movement']->fresh(), $approver);
            $expectedCount = min($index + 2, $fixture['steps']);
            $this->assertDatabaseCount('movement_approval_deliveries', $expectedCount);
            if ($index + 1 < $fixture['steps']) {
                $this->approve($fixture['movement']->fresh(), $approver);
            } else {
                app(MovementApprovalNotificationService::class)->recordActiveStep($fixture['movement']->fresh());
            }
            $this->assertDatabaseCount('movement_approval_deliveries', $expectedCount);
            Queue::assertPushed(SendMovementApprovalNotification::class, $expectedCount);
        }

        $deliveries = MovementApprovalDelivery::query()->orderBy('step_order')->get();
        $this->assertSame(range(1, $fixture['steps']), $deliveries->pluck('step_order')->all());
        $this->assertSame(array_map(static fn (User $user): int => $user->id, $fixture['approvers']),
            $deliveries->pluck('recipient_id')->all());
        $this->assertNull($fixture['movement']->fresh()->current_approval_step);
    }

    #[DataProvider('movementTypes')]
    public function test_resubmission_creates_a_new_round_and_old_jobs_cannot_mail(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $old = MovementApprovalDelivery::query()->sole();

        $this->actingAs($fixture['approvers'][0])
            ->post(route($fixture['route'].'.send-back', $fixture['movement']), ['return_note' => 'Perbaiki data usulan.'])
            ->assertRedirect();
        $this->assertDatabaseCount('movement_approval_deliveries', 1);
        $this->submit($fixture);
        $current = MovementApprovalDelivery::query()->where('approval_round', 2)->sole();
        $this->assertSame(1, $current->step_order);
        $this->assertSame($old->recipient_id, $current->recipient_id);
        Queue::assertPushed(SendMovementApprovalNotification::class, 2);

        $this->handle($old);
        $this->assertSame('skipped', $old->fresh()->status);
        $this->assertSame('stale_approval', $old->fresh()->skip_reason);
        $this->assertCount(0, $transport->messages());

        $this->handle($current);
        $this->assertSame('sent', $current->fresh()->status);
        $this->assertCount(1, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_delayed_previous_step_is_skipped_and_configuration_cannot_retarget_recipient(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $first = MovementApprovalDelivery::query()->sole();
        $replacement = User::factory()->create(['role' => UserRole::MANAGER->value]);
        $fixture['configClass']::query()->where('step_order', 2)->update(['user_id' => $replacement->id]);

        $this->approve($fixture['movement']->fresh(), $fixture['approvers'][0]);
        $second = MovementApprovalDelivery::query()->where('step_order', 2)->sole();
        $this->assertSame($fixture['approvers'][1]->id, $second->recipient_id);
        $this->handle($first);
        $this->assertSame('stale_approval', $first->fresh()->skip_reason);
        $this->assertCount(0, $transport->messages());
        $this->handle($second);
        $this->assertCount(1, $transport->messages());
        $this->assertSame($fixture['approvers'][1]->email,
            $transport->messages()->sole()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    #[DataProvider('movementTypes')]
    public function test_returned_submission_cannot_send_email_while_awaiting_correction(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $delivery = MovementApprovalDelivery::query()->sole();

        $this->actingAs($fixture['approvers'][0])
            ->post(route($fixture['route'].'.send-back', $fixture['movement']), ['return_note' => 'Perbaiki pengajuan.'])
            ->assertRedirect();
        $this->handle($delivery);

        $this->assertSame('Returned', $fixture['movement']->fresh()->verification_status);
        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->assertSame('stale_approval', $delivery->fresh()->skip_reason);
        $this->assertCount(0, $transport->messages());
        Queue::assertPushed(SendMovementApprovalNotification::class, 1);
    }

    #[DataProvider('ineligibleDeliveries')]
    public function test_worker_skips_ineligible_deliveries_without_mail(string $type, string $reason): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $delivery = MovementApprovalDelivery::query()->sole();

        match ($reason) {
            'invalid_email' => $fixture['approvers'][0]->update(['email' => 'invalid-address']),
            'missing_recipient' => $fixture['approvers'][0]->delete(),
            'recipient_unauthorized' => $fixture['approvers'][0]->update(['role' => UserRole::STAFF->value]),
            'missing_movement' => $fixture['movement']->delete(),
        };

        $this->handle($delivery);
        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->assertSame($reason, $delivery->fresh()->skip_reason);
        $this->assertNotNull($delivery->fresh()->skipped_at);
        $this->assertCount(0, $transport->messages());
        (new SendMovementApprovalNotification($delivery->id))->failed(new RuntimeException('Late worker failure'));
        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->handle($delivery);
        $this->assertCount(0, $transport->messages());
    }

    #[DataProvider('revokedPermissions')]
    public function test_worker_checks_current_view_approve_and_workspace_permissions(string $type, string $permissionSuffix): void
    {
        app(PermissionSynchronizer::class)->synchronize();
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $delivery = MovementApprovalDelivery::query()->sole();
        $key = $permissionSuffix === 'workspace'
            ? 'workspace.hc-od-career-management.view'
            : $fixture['permission'].'.'.$permissionSuffix;
        $permissionId = Permission::query()->where('key', $key)->value('id');
        if ($permissionSuffix === 'workspace') {
            $fixture['approvers'][0]->permissionOverrides()->syncWithoutDetaching([$permissionId => ['granted' => false]]);
        } else {
            DB::table('permission_role')->where('role', UserRole::MANAGER->value)->where('permission_id', $permissionId)->delete();
        }
        app(RolePermissionCache::class)->clear();

        $this->handle($delivery);

        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->assertSame('recipient_unauthorized', $delivery->fresh()->skip_reason);
        $this->assertCount(0, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_worker_uses_current_valid_email_for_the_assigned_account(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $fixture['approvers'][0]->update(['email' => 'changed-recipient@example.test']);

        $this->handle(MovementApprovalDelivery::query()->sole());

        $this->assertCount(1, $transport->messages());
        $this->assertSame('changed-recipient@example.test',
            $transport->messages()->sole()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    #[DataProvider('movementTypes')]
    public function test_email_has_canonical_subdirectory_review_link_and_minimal_reference_only(string $type): void
    {
        config()->set('app.url', 'https://official.example.test/hr');
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $relativeSubmit = route($fixture['route'].'.submit', $fixture['movement'], absolute: false);

        $this->actingAs($fixture['submitter'])->post('https://attacker.example.test'.$relativeSubmit)
            ->assertRedirect();
        $this->handle(MovementApprovalDelivery::query()->sole());

        $message = $transport->messages()->sole()->getOriginalMessage();
        $label = match ($type) {
            'mutation' => 'Mutasi', 'promotion' => 'Promosi', 'demotion' => 'Demosi',
        };
        $this->assertSame('[HC Dashboard] Persetujuan '.$label.' Diperlukan — #'.$fixture['movement']->id, $message->getSubject());
        $content = $message->getSubject()."\n".$message->getTextBody()."\n".html_entity_decode($message->getHtmlBody());
        $this->assertStringContainsString('Pengajuan '.$label.' dengan referensi #'.$fixture['movement']->id, $content);
        $this->assertStringContainsString('https://official.example.test/hr/'.$fixture['route'].'?review='.$fixture['movement']->id, $content);
        foreach (['attacker.example.test', $fixture['movement']->sap, $fixture['movement']->nama,
            'Departemen Lama', 'Departemen Baru', 'Posisi Lama', 'Posisi Baru', 'PG-1', 'JG-1', 'JG-2'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $content);
        }
    }

    #[DataProvider('movementTypes')]
    public function test_saving_and_editing_drafts_over_http_does_not_notify_approvers(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $employee = $fixture['movement']->employee;
        $fixture['movement']->delete();
        $employee->update(['employee_subgroup' => $type === 'demotion' ? 'Manager' : 'Associate']);
        $directorate = Directorate::query()->create(['code' => 'NOTICE-DIR', 'name' => 'Directorate Notifikasi']);
        $department = Department::query()->create(['directorate_id' => $directorate->id, 'code' => 'NOTICE-DEP', 'name' => 'Department Notifikasi']);
        $unit = Unit::query()->create(['department_id' => $department->id, 'code' => 'NOTICE-UNIT', 'name' => 'Unit Notifikasi']);
        $position = Position::query()->create(['unit_id' => $unit->id, 'code' => 'NOTICE-POS', 'name' => 'Position Notifikasi']);
        $payload = [
            'employee_id' => $employee->id, 'new_directorate_id' => $directorate->id,
            'new_department_id' => $department->id, 'new_unit_id' => $unit->id, 'new_position_id' => $position->id,
            'tmt' => today()->addMonth()->toDateString(), 'pg' => 'PG-1', 'jg_lama' => 'JG-1', 'jg_baru' => 'JG-2',
            'level_baru' => $type === 'demotion' ? 'Associate' : 'Manager',
        ];

        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $draft = $fixture['movement']::query()->sole();
        $this->assertSame('Draft', $draft->verification_status);
        $this->actingAs($fixture['submitter'])->put(route($fixture['route'].'.update', $draft), [
            ...$payload, 'tmt' => today()->addMonths(2)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Draft', $draft->fresh()->verification_status);
        $this->assertSame(today()->addMonths(2)->toDateString(), $draft->fresh()->tmt->toDateString());
        $this->assertDatabaseCount('movement_approval_deliveries', 0);
        Queue::assertNothingPushed();
        $this->assertCount(0, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_queue_publication_waits_for_outer_commit_and_stays_async_with_global_sync(string $type): void
    {
        $fixture = $this->fixture($type);
        config()->set('queue.default', 'sync');
        config()->set('movement-notifications.connection', 'database');
        config()->set('movement-notifications.queue', 'approval-mail');
        $transport = $this->arrayTransport();

        DB::beginTransaction();
        try {
            $this->submit($fixture);
            $this->assertDatabaseCount('movement_approval_deliveries', 1);
            $this->assertSame('pending', MovementApprovalDelivery::query()->sole()->status);
            $this->assertDatabaseCount('jobs', 0);
            $this->assertCount(0, $transport->messages());
        } finally {
            DB::commit();
        }

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'approval-mail']);
        $this->assertSame('queued', MovementApprovalDelivery::query()->sole()->status);
        $this->assertCount(0, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_rollback_removes_deliveries_and_never_publishes_jobs(string $type): void
    {
        $fixture = $this->fixture($type);
        config()->set('movement-notifications.connection', 'database');

        DB::beginTransaction();
        try {
            $this->submit($fixture);
            $this->assertDatabaseCount('movement_approval_deliveries', 1);
            $this->assertDatabaseCount('jobs', 0);
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseCount('movement_approval_deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('Draft', $fixture['movement']->fresh()->verification_status);
        $this->assertSame(0, $fixture['movement']->fresh()->approvals()->count());
    }

    #[DataProvider('movementTypes')]
    public function test_failed_queue_publication_is_recovered_once_by_the_dispatch_command(string $type): void
    {
        $fixture = $this->fixture($type);
        config()->set('movement-notifications.connection', 'unconfigured-approval-test');

        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.submit', $fixture['movement']))
            ->assertRedirect();
        $delivery = MovementApprovalDelivery::query()->sole();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('queue_unavailable', $delivery->last_error);
        $this->assertSame(1, $fixture['movement']->fresh()->approval_round);
        $this->assertDatabaseCount('jobs', 0);

        config()->set('movement-notifications.connection', 'database');
        $this->artisan('personnel:dispatch-approval-notifications')->assertSuccessful();
        $this->assertSame('queued', $delivery->fresh()->status);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('personnel:dispatch-approval-notifications')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 1);
    }

    #[DataProvider('movementTypes')]
    public function test_a_sync_notification_connection_cannot_send_mail_inside_the_submit_request(string $type): void
    {
        $fixture = $this->fixture($type);
        config()->set('movement-notifications.connection', 'sync');
        $transport = $this->arrayTransport();

        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.submit', $fixture['movement']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $delivery = MovementApprovalDelivery::query()->sole();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('queue_unavailable', $delivery->last_error);
        $this->assertNull($delivery->queued_at);
        $this->assertSame(1, $fixture['movement']->fresh()->current_approval_step);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(0, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_transport_failure_records_attempt_and_retry_sends_only_once(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $this->submit($fixture);
        $delivery = MovementApprovalDelivery::query()->sole();
        $mailer = Mail::mailer('array');
        $transport = $this->arrayTransport();
        $failure = $this->mock(TransportInterface::class);
        $failure->shouldReceive('send')->once()->andThrow(new TransportException('Synthetic transport failure'));
        $mailer->setSymfonyTransport($failure);

        try {
            $this->handle($delivery);
            $this->fail('The transport failure must reach the queue worker for retry.');
        } catch (TransportException $exception) {
            $this->assertSame('Synthetic transport failure', $exception->getMessage());
        } finally {
            $mailer->setSymfonyTransport($transport);
        }

        $this->assertSame('queued', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertNull($delivery->fresh()->sent_at);
        $this->assertSame('transport_failure', $delivery->fresh()->last_error);
        $this->assertSame(1, $fixture['movement']->fresh()->current_approval_step);

        $this->handle($delivery);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertCount(1, $transport->messages());
        $this->handle($delivery);
        $this->assertCount(1, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_exhausted_job_can_be_retried_without_overwriting_a_successful_delivery(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        $this->submit($fixture);
        $delivery = MovementApprovalDelivery::query()->sole();
        $job = new SendMovementApprovalNotification($delivery->id);

        $job->failed(new RuntimeException('Synthetic exhausted retries'));

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame('delivery_failed', $delivery->fresh()->last_error);
        $this->assertNotNull($delivery->fresh()->failed_at);
        $this->assertCount(0, $transport->messages());
        $this->handle($delivery);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->failed_at);
        $this->assertCount(1, $transport->messages());

        $job->failed(new RuntimeException('Late exhausted retries'));
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->handle($delivery);
        $this->assertCount(1, $transport->messages());
    }

    #[DataProvider('movementTypes')]
    public function test_batch_submission_and_selected_approval_notify_only_actual_active_steps(string $type): void
    {
        $fixture = $this->fixture($type);
        Queue::fake([SendMovementApprovalNotification::class]);
        $transport = $this->arrayTransport();
        [$batchClass, $relation, $foreignKey, $idsKey] = match ($type) {
            'mutation' => [EmployeeMutationBatch::class, 'mutations', 'employee_mutation_batch_id', 'mutation_ids'],
            'promotion' => [EmployeePromotionBatch::class, 'promotions', 'employee_promotion_batch_id', 'promotion_ids'],
            'demotion' => [EmployeeDemotionBatch::class, 'demotions', 'employee_demotion_batch_id', 'demotion_ids'],
        };
        $batch = $batchClass::factory()->create(['created_by' => $fixture['submitter']->id]);
        $first = $fixture['movement'];
        $first->update([$foreignKey => $batch->id]);
        $second = $first->replicate();
        $second->save();

        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.batches.submit', $batch))->assertRedirect();
        $this->assertSame(2, $batch->{$relation}()->count());
        $this->assertDatabaseCount('movement_approval_deliveries', 2);
        $this->assertSame([$fixture['approvers'][0]->id], MovementApprovalDelivery::query()->pluck('recipient_id')->unique()->all());
        Queue::assertPushed(SendMovementApprovalNotification::class, 2);
        $this->handle(MovementApprovalDelivery::query()->where('movement_id', $first->id)->sole());
        $message = $transport->messages()->sole()->getOriginalMessage();
        $this->assertStringContainsString($batch->batch_number.' / Item #'.$first->id, $message->getSubject());
        $this->assertStringNotContainsString($first->sap, $message->getSubject().$message->getTextBody());
        $this->assertStringNotContainsString($first->nama, $message->getSubject().$message->getTextBody());

        $this->actingAs($fixture['approvers'][0])
            ->post(route($fixture['route'].'.batches.approve-selected', $batch), [$idsKey => [$first->id]])->assertRedirect();
        $this->assertDatabaseCount('movement_approval_deliveries', 3);
        $this->assertDatabaseHas('movement_approval_deliveries', [
            'movement_type' => $type, 'movement_id' => $first->id, 'step_order' => 2,
            'recipient_id' => $fixture['approvers'][1]->id,
        ]);
        $this->assertDatabaseMissing('movement_approval_deliveries', ['movement_type' => $type, 'movement_id' => $second->id, 'step_order' => 2]);
        Queue::assertPushed(SendMovementApprovalNotification::class, 3);

        $this->actingAs($fixture['approvers'][1])
            ->post(route($fixture['route'].'.send-back', $first), ['return_note' => 'Koreksi satu item batch.'])->assertRedirect();
        $this->actingAs($fixture['submitter'])->post(route($fixture['route'].'.batches.submit', $batch))->assertRedirect();
        $this->assertDatabaseCount('movement_approval_deliveries', 4);
        $this->assertDatabaseHas('movement_approval_deliveries', [
            'movement_type' => $type, 'movement_id' => $first->id, 'approval_round' => 2,
            'step_order' => 1, 'recipient_id' => $fixture['approvers'][0]->id,
        ]);
        $this->assertSame(1, $second->fresh()->approval_round);
        Queue::assertPushed(SendMovementApprovalNotification::class, 4);
    }

    /** @return array<string, array{string}> */
    public static function movementTypes(): array
    {
        return ['mutation' => ['mutation'], 'promotion' => ['promotion'], 'demotion' => ['demotion']];
    }

    /** @return array<string, array{string, string}> */
    public static function ineligibleDeliveries(): array
    {
        $cases = [];
        foreach (array_keys(self::movementTypes()) as $type) {
            foreach (['invalid_email', 'missing_recipient', 'recipient_unauthorized', 'missing_movement'] as $reason) {
                $cases[$type.' '.$reason] = [$type, $reason];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, string}> */
    public static function revokedPermissions(): array
    {
        $cases = [];
        foreach (array_keys(self::movementTypes()) as $type) {
            foreach (['view', 'approve', 'workspace'] as $permission) {
                $cases[$type.' '.$permission] = [$type, $permission];
            }
        }

        return $cases;
    }

    /**
     * @return array{movement: EmployeeMutation|EmployeePromotion|EmployeeDemotion, submitter: User, approvers: array<int, User>, route: string, type: string, configClass: class-string<MutationApprovalConfig|PromotionApprovalConfig|DemotionApprovalConfig>, permission: string, steps: int}
     */
    private function fixture(string $type): array
    {
        [$movementClass, $configClass, $route, $permission, $steps] = match ($type) {
            'mutation' => [EmployeeMutation::class, MutationApprovalConfig::class, 'mutasi', 'mutations', 3],
            'promotion' => [EmployeePromotion::class, PromotionApprovalConfig::class, 'promosi', 'promotions', 4],
            'demotion' => [EmployeeDemotion::class, DemotionApprovalConfig::class, 'demosi', 'demotions', 3],
        };
        $submitter = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $approvers = User::factory()->count($steps)->create(['role' => UserRole::MANAGER->value])->all();
        foreach ($approvers as $index => $approver) {
            $configClass::query()->create(['step_order' => $index + 1, 'user_id' => $approver->id]);
        }
        $employee = Employee::query()->create(['sap' => fake()->unique()->numerify('NOTICE-########'), 'name' => 'Pegawai Notifikasi']);
        $movement = $movementClass::query()->create([
            'employee_id' => $employee->id, 'sap' => $employee->sap, 'nama' => $employee->name,
            'departemen_lama' => 'Departemen Lama', 'jabatan_lama' => 'Posisi Lama',
            'departemen_baru' => 'Departemen Baru', 'jabatan_baru' => 'Posisi Baru',
            'tmt' => today()->addMonth()->toDateString(), 'pg' => 'PG-1',
            'level_lama' => 'Associate', 'level_baru' => 'Manager', 'jg_lama' => 'JG-1', 'jg_baru' => 'JG-2',
            'verification_status' => 'Draft',
        ]);

        return compact('movement', 'submitter', 'approvers', 'route', 'type', 'configClass', 'permission', 'steps');
    }

    /** @param array{movement: EmployeeMutation|EmployeePromotion|EmployeeDemotion, submitter: User} $fixture */
    private function submit(array $fixture): void
    {
        if ($fixture['movement'] instanceof EmployeePromotion) {
            app(PromotionApprovalWorkflowService::class)->submit($fixture['movement']->fresh(), $fixture['submitter']);
        } else {
            app(MovementApprovalWorkflowService::class)->submit($fixture['movement']->fresh(), $fixture['submitter']);
        }
    }

    private function approve(EmployeeMutation|EmployeePromotion|EmployeeDemotion $movement, User $approver): void
    {
        if ($movement instanceof EmployeePromotion) {
            app(PromotionApprovalWorkflowService::class)->approve($movement, $approver);
        } else {
            app(ApprovalService::class)->approve($movement, $approver);
        }
    }

    private function handle(MovementApprovalDelivery $delivery): void
    {
        app()->call([new SendMovementApprovalNotification($delivery->id), 'handle']);
    }

    private function arrayTransport(): ArrayTransport
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport;
    }
}

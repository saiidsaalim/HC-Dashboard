<?php

namespace App\Services\Personnel;

use App\Enums\PromotionStatus;
use App\Jobs\SendMovementApprovalNotification;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\MovementApprovalDelivery;
use App\Models\User;
use App\Notifications\MovementApprovalRequested;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class MovementApprovalNotificationService
{
    public function recordActiveStep(EmployeeMutation|EmployeePromotion|EmployeeDemotion $movement): void
    {
        if (! $this->isWaiting($movement) || $movement->approval_round < 1 || $movement->current_approval_step < 1) {
            return;
        }

        $approvals = $movement->approvals()
            ->where('approval_round', $movement->approval_round)
            ->where('step_order', $movement->current_approval_step)
            ->where('status', 'Pending')
            ->get(['id', 'manager_id']);

        foreach ($approvals as $approval) {
            if (! $approval->manager_id) {
                $this->log('missing_approver', ['movement_type' => $this->movementType($movement), 'movement_id' => $movement->getKey(), 'approval_id' => $approval->getKey()]);

                continue;
            }

            $delivery = MovementApprovalDelivery::query()->firstOrCreate([
                'movement_type' => $this->movementType($movement),
                'movement_id' => $movement->getKey(),
                'approval_round' => $movement->approval_round,
                'step_order' => $movement->current_approval_step,
                'recipient_id' => $approval->manager_id,
            ]);

            $deliveryId = (int) $delivery->getKey();
            $this->dispatchDelivery($deliveryId);
        }
    }

    public function dispatchDelivery(int $deliveryId): void
    {
        DB::afterCommit(fn () => $this->publishDelivery($deliveryId));
    }

    private function publishDelivery(int $deliveryId): void
    {
        try {
            DB::transaction(function () use ($deliveryId): void {
                $delivery = MovementApprovalDelivery::query()->lockForUpdate()->find($deliveryId);

                if (! $delivery || $delivery->queued_at || in_array($delivery->status, ['sent', 'skipped', 'failed'], true)) {
                    return;
                }

                $connection = (string) config('movement-notifications.connection', 'database');
                $driver = config("queue.connections.{$connection}.driver");

                if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
                    throw new RuntimeException('Approval notifications require an asynchronous queue connection.');
                }

                $delivery->update(['status' => 'queued', 'queued_at' => now(), 'last_error' => null]);

                // The approval transaction has already committed. Publish atomically with the ledger on the default database queue.
                Bus::dispatch((new SendMovementApprovalNotification($deliveryId))
                    ->onConnection($connection)
                    ->onQueue((string) config('movement-notifications.queue', 'approval-mail'))
                    ->beforeCommit());
            });
        } catch (Throwable $exception) {
            try {
                MovementApprovalDelivery::query()->whereKey($deliveryId)->whereNull('queued_at')->where('status', 'pending')
                    ->update(['last_error' => 'queue_unavailable']);
            } catch (Throwable) {
                // The committed outbox remains available to the scheduled dispatcher after database recovery.
            }

            $this->log('queue_unavailable', ['delivery_id' => $deliveryId, 'exception_class' => $exception::class]);
        }
    }

    public function deliver(int $deliveryId): void
    {
        $identity = MovementApprovalDelivery::query()->find($deliveryId);

        if (! $identity) {
            return;
        }

        try {
            DB::transaction(function () use ($identity, $deliveryId): void {
                $modelClass = $this->movementClass($identity->movement_type);
                $movement = $modelClass ? $modelClass::query()->lockForUpdate()->find($identity->movement_id) : null;
                $delivery = MovementApprovalDelivery::query()->lockForUpdate()->find($deliveryId);

                if (! $delivery || $delivery->sent_at || in_array($delivery->status, ['sent', 'skipped'], true)) {
                    return;
                }

                if (! $movement) {
                    $this->skip($delivery, 'missing_movement');

                    return;
                }

                $recipient = User::query()->find($delivery->recipient_id);

                if (! $recipient) {
                    $this->skip($delivery, 'missing_recipient');

                    return;
                }

                if (! $this->isWaiting($movement)
                    || $movement->approval_round !== $delivery->approval_round
                    || $movement->current_approval_step !== $delivery->step_order
                    || ! $movement->approvals()->where('approval_round', $delivery->approval_round)
                        ->where('step_order', $delivery->step_order)->where('manager_id', $delivery->recipient_id)->where('status', 'Pending')->exists()) {
                    $this->skip($delivery, 'stale_approval');

                    return;
                }

                if (! $recipient->hasPermission('workspace.hc-od-career-management.view')
                    || Gate::forUser($recipient)->denies('view', $movement)
                    || Gate::forUser($recipient)->denies('approve', $movement)) {
                    $this->skip($delivery, 'recipient_unauthorized');

                    return;
                }

                if (Validator::make(['email' => $recipient->email], ['email' => ['required', 'string', 'email:rfc']])->fails()) {
                    $this->skip($delivery, 'invalid_email');

                    return;
                }

                Notification::sendNow($recipient, new MovementApprovalRequested(
                    $delivery->movement_type, (int) $movement->getKey(), $this->reference($movement),
                ));

                $delivery->update([
                    'status' => 'sent', 'sent_at' => now(), 'last_attempted_at' => now(),
                    'attempts' => $delivery->attempts + 1, 'last_error' => null, 'failed_at' => null,
                ]);
            });
        } catch (Throwable $exception) {
            MovementApprovalDelivery::query()->whereKey($deliveryId)->whereNull('sent_at')->whereNull('skipped_at')->increment('attempts', 1, [
                'last_attempted_at' => now(), 'last_error' => 'transport_failure',
            ]);
            $this->log('transport_failure', ['delivery_id' => $deliveryId, 'exception_class' => $exception::class]);

            throw $exception;
        }
    }

    public function markFailed(int $deliveryId, ?Throwable $exception): void
    {
        MovementApprovalDelivery::query()->whereKey($deliveryId)->whereNull('sent_at')->whereNull('skipped_at')->update([
            'status' => 'failed', 'failed_at' => now(), 'last_error' => 'delivery_failed',
        ]);
        $this->log('delivery_failed', ['delivery_id' => $deliveryId, 'exception_class' => $exception ? $exception::class : null]);
    }

    private function skip(MovementApprovalDelivery $delivery, string $reason): void
    {
        $delivery->update(['status' => 'skipped', 'skipped_at' => now(), 'skip_reason' => $reason, 'last_error' => null]);
        $this->log($reason, ['delivery_id' => $delivery->getKey()]);
    }

    private function isWaiting(EmployeeMutation|EmployeePromotion|EmployeeDemotion $movement): bool
    {
        return $movement->verification_status === ($movement instanceof EmployeePromotion ? PromotionStatus::WaitingApproval->value : 'Pending');
    }

    private function movementType(EmployeeMutation|EmployeePromotion|EmployeeDemotion $movement): string
    {
        return match (true) {
            $movement instanceof EmployeeMutation => 'mutation',
            $movement instanceof EmployeePromotion => 'promotion',
            $movement instanceof EmployeeDemotion => 'demotion',
        };
    }

    /** @return class-string<EmployeeMutation>|class-string<EmployeePromotion>|class-string<EmployeeDemotion>|null */
    private function movementClass(string $type): ?string
    {
        return match ($type) {
            'mutation' => EmployeeMutation::class,
            'promotion' => EmployeePromotion::class,
            'demotion' => EmployeeDemotion::class,
            default => null,
        };
    }

    private function reference(EmployeeMutation|EmployeePromotion|EmployeeDemotion $movement): string
    {
        $number = $movement->batch?->batch_number;

        return $number ? "{$number} / Item #{$movement->getKey()}" : "#{$movement->getKey()}";
    }

    /** @param array<string, mixed> $context */
    private function log(string $reason, array $context): void
    {
        try {
            Log::warning('Movement approval notification: '.$reason, $context);
        } catch (Throwable) {
            // Logging failure must not change an approval result or cause a successfully sent email to be retried.
        }
    }
}

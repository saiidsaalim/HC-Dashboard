<?php

namespace App\Jobs;

use App\Services\Personnel\MovementApprovalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendMovementApprovalNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $deliveryId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    /**
     * Execute the job.
     */
    public function handle(MovementApprovalNotificationService $notifications): void
    {
        $notifications->deliver($this->deliveryId);
    }

    public function failed(?Throwable $exception): void
    {
        app(MovementApprovalNotificationService::class)->markFailed($this->deliveryId, $exception);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\MovementApprovalDelivery;
use App\Services\Personnel\MovementApprovalNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('personnel:dispatch-approval-notifications {--limit=100 : Maximum pending notifications to enqueue}')]
#[Description('Enqueue committed movement approval notifications that have not reached the queue')]
class DispatchMovementApprovalNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MovementApprovalNotificationService $notifications): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);

        if ($limit === false) {
            $this->error('The limit must be between 1 and 10000.');

            return self::INVALID;
        }

        $deliveries = MovementApprovalDelivery::query()->where('status', 'pending')->whereNull('queued_at')->orderBy('id')->limit($limit)->pluck('id');

        foreach ($deliveries as $deliveryId) {
            $notifications->dispatchDelivery((int) $deliveryId);
        }

        $this->info('Checked '.$deliveries->count().' pending approval notifications.');

        return self::SUCCESS;
    }
}

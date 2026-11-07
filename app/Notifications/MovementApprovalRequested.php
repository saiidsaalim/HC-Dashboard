<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MovementApprovalRequested extends Notification
{
    public function __construct(
        public readonly string $movementType,
        public readonly int $movementId,
        public readonly string $reference,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $label = match ($this->movementType) {
            'mutation' => 'Mutasi',
            'promotion' => 'Promosi',
            'demotion' => 'Demosi',
        };

        return (new MailMessage)
            ->subject("[HC Dashboard] Persetujuan {$label} Diperlukan — {$this->reference}")
            ->greeting('Halo,')
            ->line("Pengajuan {$label} dengan referensi {$this->reference} memerlukan persetujuan Anda.")
            ->line('Silakan masuk ke HC Dashboard untuk meninjau pengajuan dan memberikan keputusan.')
            ->action('Tinjau Pengajuan', $this->reviewUrl());
    }

    private function reviewUrl(): string
    {
        $route = match ($this->movementType) {
            'mutation' => 'mutasi',
            'promotion' => 'promosi',
            'demotion' => 'demosi',
        };

        return rtrim((string) config('app.url'), '/').route($route, [
            'review' => $this->movementId,
        ], absolute: false);
    }
}

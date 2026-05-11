<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\CheckStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class DomainStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $domainId,
        public string $url,
        public ?string $name,
        public CheckStatus $previousStatus,
        public CheckStatus $currentStatus,
        public ?int $httpStatus,
        public ?string $error,
        public Carbon $checkedAt,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->name !== null && $this->name !== '' ? "{$this->name} ({$this->url})" : $this->url;
        $isDown = $this->currentStatus === CheckStatus::Down;
        $subject = $isDown
            ? "Monitool: {$label} is DOWN"
            : "Monitool: {$label} recovered";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($isDown ? 'Heads up — a domain went down.' : 'Good news — a domain recovered.')
            ->line("Domain: {$label}")
            ->line('Previous status: '.strtoupper($this->previousStatus->value))
            ->line('Current status: '.strtoupper($this->currentStatus->value))
            ->line('Checked at: '.$this->checkedAt->toIso8601String());

        if ($this->httpStatus !== null) {
            $mail->line("HTTP status: {$this->httpStatus}");
        }

        if ($this->error !== null && $this->error !== '') {
            $mail->line("Error: {$this->error}");
        }

        return $mail;
    }
}

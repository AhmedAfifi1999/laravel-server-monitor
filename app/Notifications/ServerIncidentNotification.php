<?php

namespace App\Notifications;

use App\Channels\TelegramChannel;
use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServerIncidentNotification extends Notification
{
    use Queueable;

    /**
     * @param Incident $incident
     * @param string   $event  'opened' أو 'resolved'
     */
    public function __construct(
        public Incident $incident,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (config('monitoring.notifications.telegram_bot_token')) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->incident->label();

        if ($this->event === 'opened') {
            return (new MailMessage)
                ->subject("🔴 Incident opened: {$label}")
                ->line("The metric **{$label}** exceeded its configured threshold.")
                ->line("Threshold: {$this->incident->threshold_value}%")
                ->line("Peak value recorded: {$this->incident->peak_value}%")
                ->line("Started at: {$this->incident->started_at}");
        }

        return (new MailMessage)
            ->subject("🟢 Incident resolved: {$label}")
            ->line("The metric **{$label}** has returned to normal levels.")
            ->line("Total duration: {$this->incident->durationInMinutes()} minute(s).");
    }

    public function toTelegram(object $notifiable): string
    {
        $label = $this->incident->label();

        if ($this->event === 'opened') {
            return "🔴 *Incident opened*\n"
                ."Metric: {$label}\n"
                ."Threshold: {$this->incident->threshold_value}%\n"
                ."Peak: {$this->incident->peak_value}%\n"
                ."Started: {$this->incident->started_at}";
        }

        return "🟢 *Incident resolved*\n"
            ."Metric: {$label}\n"
            ."Duration: {$this->incident->durationInMinutes()} min";
    }
}

<?php

namespace App\Services;

use App\Models\Incident;
use App\Notifications\ServerIncidentNotification;
use Illuminate\Support\Facades\Notification;

/**
 * IncidentMonitorService
 *
 * ياخذ نتيجة قراءة واحدة من ServerMonitorService ويقارنها بالحدود
 * المُعرّفة بـ config/monitoring.php. لو القيمة تجاوزت الحد ولا يوجد
 * حادثة مفتوحة، يفتح واحدة ويُشعر. لو القيمة رجعت طبيعية ويوجد
 * حادثة مفتوحة، يُغلقها ويُشعر بالحل.
 */
class IncidentMonitorService
{
    protected array $thresholds;

    public function __construct()
    {
        $this->thresholds = config('monitoring.thresholds', []);
    }

    public function evaluate(array $metrics): void
    {
        $this->checkThreshold('cpu', $metrics['cpu']['usage_percent'] ?? 0);
        $this->checkThreshold('memory', $metrics['memory']['usage_percent'] ?? 0);

        foreach ($metrics['disk'] ?? [] as $disk) {
            $this->checkThreshold('disk', $disk['usage_percent'], $disk['mount']);
        }

        foreach ($metrics['services'] ?? [] as $service) {
            $this->checkServiceStatus($service);
        }
    }

    protected function checkThreshold(string $type, float $value, ?string $identifier = null): void
    {
        $threshold = $this->thresholds[$type] ?? null;

        if ($threshold === null) {
            return;
        }

        $open = Incident::open()
            ->where('metric_type', $type)
            ->where('identifier', $identifier)
            ->first();

        if ($value >= $threshold) {
            if ($open) {
                if ($value > $open->peak_value) {
                    $open->update(['peak_value' => $value]);
                }

                return;
            }

            $incident = Incident::create([
                'metric_type'     => $type,
                'identifier'      => $identifier,
                'status'          => 'open',
                'threshold_value' => $threshold,
                'peak_value'      => $value,
                'started_at'      => now(),
            ]);

            $this->notify($incident, 'opened');

            return;
        }

        if ($open) {
            $open->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);

            $this->notify($open, 'resolved');
        }
    }

    protected function checkServiceStatus(array $service): void
    {
        $open = Incident::open()
            ->where('metric_type', 'service')
            ->where('identifier', $service['name'])
            ->first();

        if (! $service['is_active']) {
            if ($open) {
                return;
            }

            $incident = Incident::create([
                'metric_type'     => 'service',
                'identifier'      => $service['name'],
                'status'          => 'open',
                'threshold_value' => 0,
                'peak_value'      => 0,
                'started_at'      => now(),
            ]);

            $this->notify($incident, 'opened');

            return;
        }

        if ($open) {
            $open->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);

            $this->notify($open, 'resolved');
        }
    }

    protected function notify(Incident $incident, string $event): void
    {
        $incident->update(['notified_at' => now()]);

        $email = config('monitoring.notifications.mail_to');

        if (! $email) {
            return;
        }

        Notification::route('mail', $email)
            ->notify(new ServerIncidentNotification($incident, $event));
    }
}

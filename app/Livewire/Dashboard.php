<?php

namespace App\Livewire;

use App\Models\Incident;
use App\Models\ServerMetric;
use App\Services\IncidentMonitorService;
use App\Services\ServerMonitorService;
use Livewire\Component;

class Dashboard extends Component
{
    public array $metrics = [];

    /** Last 30 CPU readings from the database, used to draw the sparkline. */
    public array $cpuHistory = [];

    /** Currently open incidents, displayed as an alert bar at the top of the page. */
    public array $activeIncidents = [];

    /** Last 10 incidents, whether open or resolved, displayed in the incident history table. */
    public array $recentIncidents = [];

    public string $serverName = '';

    public function mount(): void
    {
        $this->serverName = gethostname() ?: 'server';
        $this->refreshMetrics();
        $this->loadCpuHistory();
        $this->loadIncidents();
    }

    /**
     * Automatically called every few seconds via wire:poll from the frontend.
     * Reads live metrics only. It does not record data in the database.
     * Database recording is handled separately by the scheduled
     * monitor:record command, which runs every minute.
     */
    public function refreshMetrics(): void
    {
        $this->metrics = app(ServerMonitorService::class)->getAllMetrics();

        // Evaluate thresholds at the same time as the dashboard refresh.
        // This ensures incidents appear immediately, even if the scheduled
        // command that runs every minute has not executed yet.
        app(IncidentMonitorService::class)->evaluate($this->metrics);

        $this->loadIncidents();
    }

    protected function loadCpuHistory(): void
    {
        $this->cpuHistory = ServerMetric::query()
            ->orderByDesc('recorded_at')
            ->limit(30)
            ->pluck('cpu_percent')
            ->reverse()
            ->values()
            ->toArray();
    }

    protected function loadIncidents(): void
    {
        $this->activeIncidents = Incident::open()
            ->orderByDesc('started_at')
            ->get()
            ->toArray();

        $this->recentIncidents = Incident::query()
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Converts the CPU history into SVG polyline coordinates
     * ready for rendering without any external JavaScript library.
     */
    public function cpuSparklinePoints(int $width = 560, int $height = 64): string
    {
        $data = $this->cpuHistory;
        $count = count($data);

        if ($count < 2) {
            return '';
        }

        $max = max(max($data), 1);
        $step = $width / ($count - 1);

        $points = [];

        foreach ($data as $i => $value) {
            $x = round($i * $step, 1);
            $y = round($height - ($value / $max) * ($height - 4) - 2, 1);
            $points[] = "{$x},{$y}";
        }

        return implode(' ', $points);
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
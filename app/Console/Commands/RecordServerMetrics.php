<?php

namespace App\Console\Commands;

use App\Models\ServerMetric;
use App\Services\IncidentMonitorService;
use App\Services\ServerMonitorService;
use Illuminate\Console\Command;

class RecordServerMetrics extends Command
{
    protected $signature = 'monitor:record';

    protected $description = 'يسجل قراءة لحظية لموارد السيرفر بقاعدة البيانات ويفحص الحدود لفتح/إغلاق الحوادث';

    public function handle(ServerMonitorService $monitor, IncidentMonitorService $incidents): int
    {
        $metrics = $monitor->getAllMetrics();

        ServerMetric::create([
            'cpu_percent'    => $metrics['cpu']['usage_percent'],
            'memory_percent' => $metrics['memory']['usage_percent'],
            'disk_percent'   => collect($metrics['disk'])->max('usage_percent') ?? 0,
            'load_1min'      => $metrics['load']['1min'],
            'recorded_at'    => now(),
        ]);

        $incidents->evaluate($metrics);

        $this->info('Server metrics recorded at '.now());

        return self::SUCCESS;
    }
}

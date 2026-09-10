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

    /** آخر 30 قراءة CPU من قاعدة البيانات، تُستخدم لرسم الـ sparkline */
    public array $cpuHistory = [];

    /** الحوادث المفتوحة حالياً (لو موجودة، تظهر بشريط تنبيه أعلى الصفحة) */
    public array $activeIncidents = [];

    /** آخر 10 حوادث (مفتوحة أو محلولة) لعرضها بجدول السجل */
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
     * يُستدعى تلقائياً كل بضع ثوانٍ عبر wire:poll من الواجهة.
     * يقرأ القياسات الحية فقط (مو تسجيل بقاعدة البيانات - هذا عمل
     * الـ scheduled command monitor:record المنفصل كل دقيقة).
     */
    public function refreshMetrics(): void
    {
        $this->metrics = app(ServerMonitorService::class)->getAllMetrics();

        // نفحص الحدود بنفس لحظة العرض أيضاً، حتى لو الجدولة
        // (كل دقيقة) لسا ما وصلت، تظهر الحادثة بأسرع وقت ممكن.
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
     * يحول سجل الـ CPU إلى إحداثيات SVG polyline جاهزة للرسم
     * بدون أي مكتبة JS خارجية.
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
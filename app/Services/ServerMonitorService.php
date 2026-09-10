<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ServerMonitorService
{
    public function getAllMetrics(): array
    {
        return [
            'cpu'       => $this->getCpuUsage(),
            'memory'    => $this->getMemoryUsage(),
            'disk'      => $this->getDiskUsage(),
            'processes' => $this->getTopProcesses(),
            'services'  => $this->getServicesStatus(['nginx', 'mysql', 'php-fpm']),
            'uptime'    => $this->getUptime(),
            'load'      => $this->getLoadAverage(),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    public function getCpuUsage(): array
    {
        $read = function () {
            $stat = @file_get_contents('/proc/stat');
            preg_match('/^cpu\s+(.+)$/m', $stat, $matches);
            return array_map('intval', preg_split('/\s+/', trim($matches[1])));
        };

        $first = $read();
        usleep(200000);
        $second = $read();

        $idle1 = $first[3];
        $idle2 = $second[3];
        $total1 = array_sum($first);
        $total2 = array_sum($second);

        $totalDiff = $total2 - $total1;
        $idleDiff = $idle2 - $idle1;

        $usagePercent = $totalDiff > 0
            ? round((1 - ($idleDiff / $totalDiff)) * 100, 1)
            : 0;

        $coresResult = Process::run('nproc');
        $cores = (int) trim($coresResult->output());

        return [
            'usage_percent' => $usagePercent,
            'cores'         => $cores,
        ];
    }

    public function getMemoryUsage(): array
    {
        $result = Process::run('free -m');
        $output = $result->output();

        $lines = explode("\n", trim($output));

        $memLine = preg_split('/\s+/', trim($lines[1] ?? ''));
        $swapLine = isset($lines[2]) ? preg_split('/\s+/', trim($lines[2])) : null;

        $totalMb = (int) ($memLine[1] ?? 0);
        $usedMb  = (int) ($memLine[2] ?? 0);
        $freeMb  = (int) ($memLine[3] ?? 0);

        return [
            'total_mb'      => $totalMb,
            'used_mb'       => $usedMb,
            'free_mb'       => $freeMb,
            'usage_percent' => $totalMb > 0 ? round(($usedMb / $totalMb) * 100, 1) : 0,
            'swap_total_mb' => (int) ($swapLine[1] ?? 0),
            'swap_used_mb'  => (int) ($swapLine[2] ?? 0),
        ];
    }

    public function getDiskUsage(): array
    {
        $result = Process::run(
            "df -h -x tmpfs -x devtmpfs -x squashfs --output=target,size,used,avail,pcent"
        );

        $lines = explode("\n", trim($result->output()));
        array_shift($lines);

        $disks = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);

            if (count($parts) < 5) {
                continue;
            }

            $disks[] = [
                'mount'         => $parts[0],
                'size'          => $parts[1],
                'used'          => $parts[2],
                'available'     => $parts[3],
                'usage_percent' => (int) rtrim($parts[4], '%'),
            ];
        }

        return $disks;
    }

    public function getTopProcesses(int $limit = 10): array
    {
        $result = Process::run(
            "ps -eo pid,comm,%cpu,%mem --sort=-%cpu --no-headers | head -n {$limit}"
        );

        $lines = explode("\n", trim($result->output()));
        $processes = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s+(\S+)\s+([\d.]+)\s+([\d.]+)$/', $line, $m)) {
                $processes[] = [
                    'pid'            => (int) $m[1],
                    'name'           => $m[2],
                    'cpu_percent'    => (float) $m[3],
                    'memory_percent' => (float) $m[4],
                ];
            }
        }

        return $processes;
    }

    public function getServicesStatus(array $services): array
    {
        $statuses = [];

        foreach ($services as $service) {
            $result = Process::run("systemctl is-active {$service} 2>/dev/null");
            $status = trim($result->output()) ?: 'unknown';

            $statuses[] = [
                'name'      => $service,
                'status'    => $status,
                'is_active' => $status === 'active',
            ];
        }

        return $statuses;
    }

    public function getUptime(): string
    {
        $result = Process::run('uptime -p');

        return trim($result->output()) ?: 'unknown';
    }

    public function getLoadAverage(): array
    {
        $load = @sys_getloadavg();

        if ($load === false) {
            return [
                '1min'  => 0,
                '5min'  => 0,
                '15min' => 0,
            ];
        }

        return [
            '1min'  => round($load[0], 2),
            '5min'  => round($load[1], 2),
            '15min' => round($load[2], 2),
        ];
    }
}

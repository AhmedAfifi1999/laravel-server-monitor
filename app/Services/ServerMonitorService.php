<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * ServerMonitorService
 *
 * Executes Linux shell commands and converts their unstructured
 * text output into organized PHP arrays that can be easily
 * displayed in the dashboard UI (Blade / Livewire).
 *
 * Each method is responsible for a single metric (Single Responsibility)
 * to make individual testing easier and allow future replacement.
 */
class ServerMonitorService
{
    /**
     * Returns all metrics at once.
     * This is the method that will be called by the
     * Livewire Component or Controller.
     */
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

    /**
     * CPU usage percentage.
     *
     * Reads /proc/stat twice with a short time interval
     * to calculate the actual CPU usage accurately instead
     * of relying on top, which requires parsing text output
     * that may vary between Linux distributions.
     */
    public function getCpuUsage(): array
    {
        $read = function () {
            $stat = @file_get_contents('/proc/stat');
            preg_match('/^cpu\s+(.+)$/m', $stat, $matches);
            return array_map('intval', preg_split('/\s+/', trim($matches[1])));
        };

        $first = $read();
        usleep(200000); // 0.2 second interval
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

        // Number of CPU cores, useful for displaying alongside the usage percentage.
        $coresResult = Process::run('nproc');
        $cores = (int) trim($coresResult->output());

        return [
            'usage_percent' => $usagePercent,
            'cores'         => $cores,
        ];
    }

    /**
     * Memory (RAM) and Swap usage using the free command.
     */
    public function getMemoryUsage(): array
    {
        $result = Process::run('free -m');
        $output = $result->output();

        $lines = explode("\n", trim($output));

        // Second line: Mem: total used free shared buff/cache available
        $memLine = preg_split('/\s+/', trim($lines[1] ?? ''));

        // Third line, if available: Swap: total used free
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

    /**
     * Disk usage for each real partition.
     *
     * Temporary and virtual filesystems such as tmpfs, devtmpfs,
     * and squashfs are excluded to avoid misleading or duplicated results.
     */
    public function getDiskUsage(): array
    {
        // -x excludes specific filesystem types.
        // --output specifies only the required columns.
        $result = Process::run(
            "df -h -x tmpfs -x devtmpfs -x squashfs --output=target,size,used,avail,pcent"
        );

        $lines = explode("\n", trim($result->output()));
        array_shift($lines); // Remove the header line.

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

    /**
     * Returns the top processes by CPU usage (Top N Processes).
     */
    public function getTopProcesses(int $limit = 10): array
    {
        // Fetch extra processes because we will exclude the ps process itself.
        // This ensures we still return the requested number of processes.
        $fetchLimit = $limit + 5;

        $result = Process::run(
            "ps -eo pid,comm,%cpu,%mem --sort=-%cpu --no-headers | head -n {$fetchLimit}"
        );

        $lines = explode("\n", trim($result->output()));
        $processes = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Example line: 1234 php-fpm 12.3 4.5
            if (! preg_match('/^(\d+)\s+(\S+)\s+([\d.]+)\s+([\d.]+)$/', $line, $m)) {
                continue;
            }

            $name = $m[2];
            $cpuPercent = (float) $m[3];

            // Exclude the ps process itself.
            // It is only a measurement utility, not a real background process.
            // Because it is created during the measurement, its CPU usage
            // can sometimes appear unusually high.
            if ($name === 'ps') {
                continue;
            }

            $processes[] = [
                'pid'            => (int) $m[1],
                'name'           => $name,
                'cpu_percent'    => $cpuPercent,
                'memory_percent' => (float) $m[4],
            ];

            if (count($processes) >= $limit) {
                break;
            }
        }

        return $processes;
    }

    /**
     * Returns the status of specified systemd services.
     *
     * Services can be passed as an array so they can be configured
     * externally in the future, for example through a config file,
     * instead of being hard-coded inside the class.
     */
    public function getServicesStatus(array $services): array
    {
        $statuses = [];

        foreach ($services as $service) {
            // is-active returns active / inactive / failed / unknown.
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

    /**
     * Returns the server uptime in a human-readable format.
     */
    public function getUptime(): string
    {
        $result = Process::run('uptime -p'); // Example: "up 3 days, 2 hours, 15 minutes"

        return trim($result->output()) ?: 'unknown';
    }

    /**
     * Returns the load average for the last 1, 5, and 15 minutes.
     */
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
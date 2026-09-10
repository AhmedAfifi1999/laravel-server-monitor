<div
    wire:poll.3s="refreshMetrics"
    class="min-h-screen bg-[#0B0E14] text-[#E6E8EB] font-['Space_Grotesk',sans-serif] px-6 py-8"
>
    {{-- ===== Header ===== --}}
    <div class="flex items-center justify-between mb-8 pb-5 border-b border-[#232936]">
        <div class="flex items-center gap-3">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#F5A623] opacity-60"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#F5A623]"></span>
            </span>
            <h1 class="text-xl font-semibold tracking-tight">Server Pulse</h1>
            <span class="text-sm text-[#8B93A1] font-mono">/ {{ $serverName }}</span>
        </div>
        <div class="text-right">
            <div class="text-xs text-[#8B93A1]">last updated</div>
            <div class="font-mono text-sm">{{ $metrics['timestamp'] ?? '—' }}</div>
        </div>
    </div>

    {{-- ===== Primary metric cards ===== --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

        {{-- CPU — radial gauge --}}
        <div class="bg-[#131822] border border-[#232936] rounded p-5">
            <div class="text-xs text-[#8B93A1] mb-4">CPU</div>
            <div class="flex items-center gap-4">
                <div
                    class="relative w-16 h-16 rounded-full shrink-0"
                    style="background: conic-gradient(#F5A623 {{ ($metrics['cpu']['usage_percent'] ?? 0) * 3.6 }}deg, #232936 0deg)"
                >
                    <div class="absolute inset-1.5 rounded-full bg-[#131822] flex items-center justify-center">
                        <span class="font-mono text-sm">{{ $metrics['cpu']['usage_percent'] ?? 0 }}%</span>
                    </div>
                </div>
                <div class="text-sm text-[#8B93A1]">
                    {{ $metrics['cpu']['cores'] ?? '—' }} cores
                </div>
            </div>
        </div>

        {{-- Memory — radial gauge --}}
        <div class="bg-[#131822] border border-[#232936] rounded p-5">
            <div class="text-xs text-[#8B93A1] mb-4">MEMORY</div>
            <div class="flex items-center gap-4">
                <div
                    class="relative w-16 h-16 rounded-full shrink-0"
                    style="background: conic-gradient(#F5A623 {{ ($metrics['memory']['usage_percent'] ?? 0) * 3.6 }}deg, #232936 0deg)"
                >
                    <div class="absolute inset-1.5 rounded-full bg-[#131822] flex items-center justify-center">
                        <span class="font-mono text-sm">{{ $metrics['memory']['usage_percent'] ?? 0 }}%</span>
                    </div>
                </div>
                <div class="text-sm text-[#8B93A1] font-mono">
                    {{ $metrics['memory']['used_mb'] ?? 0 }} / {{ $metrics['memory']['total_mb'] ?? 0 }} MB
                </div>
            </div>
        </div>

        {{-- Disk — horizontal bars per partition --}}
        <div class="bg-[#131822] border border-[#232936] rounded p-5">
            <div class="text-xs text-[#8B93A1] mb-4">DISK</div>
            <div class="space-y-2.5">
                @forelse (($metrics['disk'] ?? []) as $disk)
                    <div>
                        <div class="flex justify-between text-xs font-mono mb-1">
                            <span class="text-[#8B93A1]">{{ $disk['mount'] }}</span>
                            <span>{{ $disk['usage_percent'] }}%</span>
                        </div>
                        <div class="h-1.5 bg-[#232936] rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full {{ $disk['usage_percent'] >= 90 ? 'bg-[#F87171]' : ($disk['usage_percent'] >= 75 ? 'bg-[#FBBF24]' : 'bg-[#34D399]') }}"
                                style="width: {{ $disk['usage_percent'] }}%"
                            ></div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-[#8B93A1]">no data</div>
                @endforelse
            </div>
        </div>

        {{-- Load average — compact numbers --}}
        <div class="bg-[#131822] border border-[#232936] rounded p-5">
            <div class="text-xs text-[#8B93A1] mb-4">LOAD AVERAGE</div>
            <div class="flex items-end justify-between font-mono">
                <div class="text-center">
                    <div class="text-lg">{{ $metrics['load']['1min'] ?? 0 }}</div>
                    <div class="text-[10px] text-[#8B93A1] mt-1">1m</div>
                </div>
                <div class="text-center">
                    <div class="text-lg">{{ $metrics['load']['5min'] ?? 0 }}</div>
                    <div class="text-[10px] text-[#8B93A1] mt-1">5m</div>
                </div>
                <div class="text-center">
                    <div class="text-lg">{{ $metrics['load']['15min'] ?? 0 }}</div>
                    <div class="text-[10px] text-[#8B93A1] mt-1">15m</div>
                </div>
            </div>
            <div class="text-xs text-[#8B93A1] mt-4 pt-3 border-t border-[#232936]">
                {{ $metrics['uptime'] ?? '—' }}
            </div>
        </div>
    </div>

    {{-- ===== Processes + Services ===== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        {{-- Top processes --}}
        <div class="lg:col-span-2 bg-[#131822] border border-[#232936] rounded p-5">
            <div class="text-xs text-[#8B93A1] mb-4">TOP PROCESSES</div>
            <table class="w-full text-sm font-mono">
                <thead>
                    <tr class="text-left text-[#8B93A1] text-xs">
                        <th class="font-normal pb-2">PID</th>
                        <th class="font-normal pb-2">NAME</th>
                        <th class="font-normal pb-2 text-right">CPU%</th>
                        <th class="font-normal pb-2 text-right">MEM%</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($metrics['processes'] ?? []) as $process)
                        <tr class="border-t border-[#232936]">
                            <td class="py-1.5 text-[#8B93A1]">{{ $process['pid'] }}</td>
                            <td class="py-1.5">{{ $process['name'] }}</td>
                            <td class="py-1.5 text-right">{{ $process['cpu_percent'] }}</td>
                            <td class="py-1.5 text-right">{{ $process['memory_percent'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-[#8B93A1]">no data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Services status --}}
        <div class="bg-[#131822] border border-[#232936] rounded p-5">
            <div class="text-xs text-[#8B93A1] mb-4">SERVICES</div>
            <div class="space-y-3">
                @forelse (($metrics['services'] ?? []) as $service)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-mono">{{ $service['name'] }}</span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full {{ $service['is_active'] ? 'bg-[#34D399]' : 'bg-[#F87171]' }}"></span>
                            <span class="text-xs text-[#8B93A1] font-mono">{{ $service['status'] }}</span>
                        </span>
                    </div>
                @empty
                    <div class="text-sm text-[#8B93A1]">no data</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ===== CPU history sparkline ===== --}}
    <div class="bg-[#131822] border border-[#232936] rounded p-5">
        <div class="text-xs text-[#8B93A1] mb-4">CPU — LAST FEW MINUTES</div>
        <svg viewBox="0 0 560 64" class="w-full h-16" preserveAspectRatio="none">
            <polyline
                points="{{ $this->cpuSparklinePoints() }}"
                fill="none"
                stroke="#F5A623"
                stroke-width="1.5"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        </svg>
    </div>
</div>
@php
    $d = $this->data();
    $devices = $d['devices'];
    $total = $d['total'];
    $referrers = $d['referrers'];

    // The donut is a conic-gradient: stop positions accumulate around the ring, so each
    // device gets a from/to pair and the last one closes at 100%.
    $accumulator = 0;
    $stops = [];

    foreach ($devices as $device) {
        $from = $total > 0 ? ($accumulator / $total) * 100 : 0;
        $accumulator += $device['views'];
        $to = $total > 0 ? ($accumulator / $total) * 100 : 0;

        $stops[] = $device['colour'].' '.$from.'% '.$to.'%';
    }

    $ring = count($stops) > 0
        ? 'conic-gradient('.implode(', ', $stops).')'
        : 'conic-gradient(var(--gray-200) 0% 100%)';
@endphp

<x-filament-widgets::widget>
    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
    >
        <x-filament::section>
        <x-slot name="heading">
            Where they came from
        </x-slot>

        <x-slot name="description">
            Device split and referrer sources for the selected range.
        </x-slot>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                @if ($total === 0)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No page views in this range yet. The donut fills in as the beacon records
                        visits.
                    </p>
                @else
                    <div class="flex items-center gap-5">
                        {{-- Hand-built ring: a conic-gradient with a punched centre, the same
                             trick the design mockup used. The palette is ShopChartPalette's, so
                             the colours match every other chart on the page. --}}
                        <div
                            class="relative h-32 w-32 shrink-0 rounded-full"
                            style="background: {{ $ring }}"
                            role="img"
                            aria-label="Device split: {{ implode(', ', array_map(static fn ($device) => $device['label'].' '.$device['percent'].'%', $devices)) }}"
                        >
                            <div class="absolute inset-[22%] rounded-full border border-gray-200 bg-white dark:border-white/10 dark:bg-[#121315]"></div>
                            <div class="absolute inset-0 grid place-content-center text-center">
                                <span class="font-mono text-lg font-bold text-gray-950 dark:text-white">
                                    {{ number_format($total) }}
                                </span>
                                <span class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    views
                                </span>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            @foreach ($devices as $device)
                                <div class="mb-2 flex items-center gap-2 last:mb-0">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $device['colour'] }}" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1 truncate text-sm text-gray-950 dark:text-white">
                                        {{ $device['label'] }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format($device['views']) }} · {{ $device['percent'] }}%
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Top referrers
                </p>

                @if ($referrers === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No referrer data in this range yet.
                    </p>
                @else
                    <div class="flex flex-col divide-y divide-gray-950/5 dark:divide-white/10">
                        @foreach ($referrers as $source)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="min-w-0 flex-1 truncate text-sm text-gray-950 dark:text-white">
                                    {{ $source['label'] }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($source['views']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
    </div>
</x-filament-widgets::widget>

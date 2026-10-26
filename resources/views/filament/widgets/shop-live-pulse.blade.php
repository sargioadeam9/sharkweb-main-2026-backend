@php
    $pulse = $this->pulse();
@endphp

<x-filament-widgets::widget>
    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
    >
        <x-filament::section>
        <x-slot name="heading">
            <span class="inline-flex items-center gap-2.5">
                {{-- The pulse itself: a pinging ring under a solid dot. Rendered even when the
                     count is zero — the point of the widget is that it is always looking. --}}
                <span class="relative flex h-3 w-3" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-success-500"></span>
                </span>

                {{ $pulse['count'] }} {{ trans_choice('{1} person is|[:count,*] people are', $pulse['count']) }} on the site right now
            </span>
        </x-slot>

        <x-slot name="description">
            First-party tracking — a hashed session, no cookies, gone from this pulse after
            three minutes of silence.
        </x-slot>

        @if ($pulse['count'] === 0)
            <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-500/10">
                <x-filament::icon
                    icon="heroicon-o-moon"
                    class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                />
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Nobody is on the site right now. Visits from the last few minutes appear here
                    the moment the beacon sees them.
                </p>
            </div>
        @else
            <div class="flex flex-col divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($pulse['top'] as $row)
                    <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                        <span class="truncate font-mono text-sm text-gray-950 dark:text-white">
                            {{ $row['path'] }}
                        </span>

                        <x-filament::badge :color="$loop->first ? 'success' : 'gray'" size="sm">
                            {{ $row['count'] }} {{ trans_choice('{1} visitor|[:count,*] visitors', $row['count']) }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
    </div>
</x-filament-widgets::widget>

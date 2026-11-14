@php
    $d = $this->data();
    $rows = $d['rows'];
    $clear = $d['clear'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Needs attention
        </x-slot>

        <x-slot name="description">
            Live state, not window-bound — a TrxID waiting for a decision is waiting right now.
        </x-slot>

        @if ($clear)
            {{-- Shown rather than hidden: a widget that disappears when all is well leaves the
                 reader unsure whether it was checked. --}}
            <div class="flex items-center gap-3 rounded-lg bg-success-50 p-4 dark:bg-success-500/10">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="h-5 w-5 shrink-0 text-success-600 dark:text-success-400"
                />
                <p class="text-sm text-success-800 dark:text-success-300">
                    Nothing needs a decision. No TrxID is waiting, no order is stuck unpaid past
                    {{ (int) config('sharkweb.shop.automations.unpaid_reminder_hours', 24) }} hours,
                    and every published product has a file to deliver.
                </p>
            </div>
        @else
            <div class="flex flex-col divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($rows as $row)
                    @php
                        $dot = match ($row['severity']) {
                            'danger' => 'bg-danger-500',
                            'warning' => 'bg-warning-500',
                            default => 'bg-gray-400',
                        };
                        $badge = match ($row['severity']) {
                            'danger' => 'danger',
                            'warning' => 'warning',
                            default => 'gray',
                        };
                    @endphp

                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $row['label'] }}
                                    </span>

                                    <x-filament::badge :color="$badge" size="sm">
                                        {{ $row['count'] }}
                                    </x-filament::badge>
                                </div>

                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row['detail'] }}
                                </p>
                            </div>
                        </div>

                        @if ($row['url'] !== null)
                            <x-filament::button
                                :href="$row['url']"
                                tag="a"
                                size="sm"
                                color="gray"
                                outlined
                                icon="heroicon-m-arrow-right"
                            >
                                Open
                            </x-filament::button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
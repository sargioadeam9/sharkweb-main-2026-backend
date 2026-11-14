<x-filament-panels::page>
    {{--
        Shop analytics.

        TABS ARE SERVER-SIDE. `wire:click="setTab(...)"` re-renders and only the visible tab's
        widgets are instantiated, so six tabs do not mean six charts and twenty aggregate queries
        on every page load. The tab strip is Filament's own component rather than a hand-rolled
        one so it inherits the panel's focus states and dark-mode colours.

        THE RANGE IS A SEGMENTED ROW OF BUTTONS, NOT A SELECT. Four options, one visible at a time,
        and the current one is readable at a glance — a dropdown would hide the state of the page
        behind a click, and "which window am I looking at" is the first question any of these
        numbers raises.
    --}}

    <div wire:poll.20s class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-filament::tabs class="flex-1">
                @foreach ($this->tabs() as $key => $label)
                    <x-filament::tabs.item
                        wire:click="setTab('{{ $key }}')"
                        :active="$tab === $key"
                        :badge="$this->getTabBadge($key)"
                        :badge-color="$this->getTabBadgeColor($key)"
                    >
                        {{ $label }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <div class="inline-flex items-center gap-2 self-start sm:self-center px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40 text-xs font-medium shadow-sm">
                <span class="relative flex h-2 w-2" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                <span>🟢 Live • Auto-syncing</span>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Range
                </span>

                @foreach ($this->rangeOptions() as $value => $label)
                    <x-filament::button
                        type="button"
                        size="sm"
                        :color="($days === (int) $value && ! $startDate && ! $endDate) ? 'primary' : 'gray'"
                        wire:click="setDays({{ (int) $value }})"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach

                @if ($days !== \App\Services\ShopAnalyticsService::DEFAULT_RANGE || $startDate || $endDate)
                    <x-filament::button type="button" size="sm" color="gray" outlined wire:click="clearCustomDates">
                        Reset to 30 days
                    </x-filament::button>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    Custom
                </span>
                <input
                    type="date"
                    wire:model.live="startDate"
                    aria-label="Start date"
                    class="h-8 rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100"
                />
                <span class="text-xs text-gray-400 dark:text-gray-500">to</span>
                <input
                    type="date"
                    wire:model.live="endDate"
                    aria-label="End date"
                    class="h-8 rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-100"
                />
                @if ($startDate || $endDate)
                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        outlined
                        wire:click="clearCustomDates"
                    >
                        Clear
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{--
            The month's target bar. `monthlyProgress()` returns percent null when no target is
            configured (or it is zero), and then the whole card stays hidden — a gauge with no
            target is decoration, not information. The bar itself is clamped to 100% while the
            printed percentage keeps the real figure, so beating the target is still visible.
        --}}
        @php
            $progress = $this->monthlyProgress();
        @endphp

        @if ($progress['target_minor'] > 0)
            <div class="rounded-2xl border border-gray-200 bg-white/60 p-4 dark:border-white/10 dark:bg-gray-900/40">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        This month · collected {{ $progress['collected_label'] }} of {{ $progress['target_label'] }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($progress['percent'] !== null)
                            {{ $progress['percent'] }}% of target ·
                        @endif
                        {{ $progress['remaining_days'] }} {{ trans_choice('{1} day left|[:count,*] days left', $progress['remaining_days']) }}
                    </span>
                </div>

                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-black/40">
                    <div
                        class="h-full rounded-full bg-primary-500 transition-all"
                        style="width: {{ min(max($progress['percent'] ?? 0, 0), 100) }}%"
                    ></div>
                </div>
            </div>
        @endif
    </div>

    {{--
        The range is passed down through `getWidgetData()`. Including it in the widget keys means a
        range change remounts the widgets and re-queries, rather than re-rendering cached data under
        a label that no longer matches it. The grid is the command center's twelve columns on
        desktop: the widgets carry their own spans (`xl` splits, `default` stacks full width).
    --}}
    <x-filament-widgets::widgets
        :widgets="$this->widgetsForCurrentTab()"
        :data="$this->getWidgetData()"
        :columns="['default' => 1, 'md' => 2, 'xl' => 12]"
        class="mt-6"
    />

    <p class="mt-6 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
        Figures are computed from the shop's own tables. Visitor tracking is first-party and
        privacy-safe: a SHA-256 of IP, user agent and app key — no cookies, no third-party script,
        no plaintext address stored — pruned after six months and purgeable from the Visitors tab.
        Money is summed on the database and converted once, in integer paisa.
    </p>
</x-filament-panels::page>
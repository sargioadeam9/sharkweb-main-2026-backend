@php
    $d = $this->data();
    $p = $d['profit'];
    $hasCost = $d['hasCost'];
    $productsUrl = $d['productsUrl'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Profit &amp; loss
        </x-slot>

        <x-slot name="description">
            Revenue here is what was collected after the checkout discount, so revenue − cost really
            is profit. That discount is applied once per order, so its share is allocated across the
            lines at the window's realised rate.
        </x-slot>

        <div class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Revenue collected
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['revenue_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $p['total_lines'] }} paid order line(s)
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Cost of goods
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['cost_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        From the snapshot on {{ $p['costed_lines'] }} line(s)
                    </div>
                </div>

                {{-- The headline. A dash, not a zero, when no sold line has a cost: zero would
                     report a break-even month that never happened. --}}
                <div class="rounded-lg p-4 {{ $hasCost ? 'bg-primary-50 dark:bg-primary-500/10' : 'bg-warning-50 dark:bg-warning-500/10' }}">
                    <div class="text-xs font-medium uppercase tracking-wide {{ $hasCost ? 'text-primary-700 dark:text-primary-400' : 'text-warning-700 dark:text-warning-400' }}">
                        Profit
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $this->moneyOrDash($p['profit_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($hasCost)
                            Margin {{ $this->percentLabel($p['margin_percent']) }} of costed revenue
                        @else
                            No sold line has a cost set
                        @endif
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Costed revenue
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['costed_revenue_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        The part of revenue with a known cost behind it
                    </div>
                </div>
            </div>

            {{-- The caveat sits beside the number, not under it: a reader who cannot see what was excluded
                 cannot judge the figure, and this is the one tab where a wrong number gets acted on. --}}
            @if ((int) $p['uncosted_lines'] > 0)
                <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 dark:border-warning-500/40 dark:bg-warning-500/10">
                    <div class="flex items-start gap-3">
                        <x-filament::icon
                            icon="heroicon-o-exclamation-triangle"
                            class="mt-0.5 h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400"
                        />

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-warning-800 dark:text-warning-300">
                                {{ $p['uncosted_lines'] }} of {{ $p['total_lines'] }} sold line(s) have no cost set.
                            </p>
                            <p class="mt-1 text-sm text-warning-700 dark:text-warning-300/80">
                                {{ $this->money((int) $p['uncosted_revenue_minor']) }} of revenue is left out of
                                the profit figure above. Subtracting a partial cost from the full revenue would
                                report a margin that is too high, so those lines are counted and excluded rather
                                than assumed free.
                            </p>
                            <div class="mt-3">
                                <x-filament::button :href="$productsUrl" tag="a" size="sm" color="warning" icon="heroicon-m-shopping-bag">
                                    Set product costs
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Catalogue discounts
                    </div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['catalogue_discount_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        MRP vs selling price, on the paid lines
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Checkout discounts
                    </div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['launch_discount_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        The launch rate, applied once per order
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Refunded
                    </div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['refunded_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Money that went back — not a cost of goods
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Cancelled
                    </div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->money((int) $p['cancelled_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Never collected, so never revenue
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
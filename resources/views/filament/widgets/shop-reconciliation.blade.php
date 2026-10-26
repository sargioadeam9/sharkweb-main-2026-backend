@php
    $d = $this->data();
    $funnel = $d['funnel'];
    $turnaround = $d['turnaround'];
    $queue = $d['queue'];
    $reasons = $d['reasons'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Reconciliation
        </x-slot>

        <x-slot name="description">
            How orders move, why payments get rejected, and how long a decision takes. This is the
            order's own journey — the shop keeps no visitor data, so it is not a traffic funnel.
        </x-slot>

        <div class="flex flex-col gap-8">
            {{-- The queue, right now: not window-bound, because this is what needs doing today. --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-warning-50 p-4 dark:bg-warning-500/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-warning-700 dark:text-warning-400">
                        Waiting for a decision
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $queue['awaiting_verification'] }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($queue['oldest_waiting_minutes'] !== null)
                            Oldest {{ $queue['oldest_waiting_minutes'] }} min &middot; alert at
                            {{ (int) config('sharkweb.shop.automations.stale_verification_minutes', 30) }} min
                        @else
                            Nothing in the queue
                        @endif
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Placed, no payment sent
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $queue['awaiting_submission'] }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Reminder after
                        {{ (int) config('sharkweb.shop.automations.unpaid_reminder_hours', 24) }} hours
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Oldest unpaid order
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $queue['oldest_unpaid_hours'] ?? 0 }}<span class="text-sm font-medium"> h</span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Hours since it was placed
                    </div>
                </div>
            </div>

            {{-- The funnel --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Order journey
                </h4>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Percentages are of the orders placed in this window, not of visitors.
                </p>

                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($funnel as $step)
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700 dark:text-gray-200">
                                    {{ $step['label'] }}
                                </span>
                                <span class="tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $step['count'] }}
                                    @if ($step['percent'] !== null)
                                        &middot; {{ $step['percent'] }}%
                                    @endif
                                </span>
                            </div>

                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                                <div
                                    class="h-full rounded-full {{ $step['key'] === 'rejected' ? 'bg-danger-500' : 'bg-primary-500' }}"
                                    style="width: {{ max($step['percent'] ?? 0, $step['count'] > 0 ? 2 : 0) }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Submitted and rejected overlap: an order whose TrxID was refused still recorded a
                    submission, and a rejection can be retried — so each order appears once, in
                    whatever state it is in today.
                </p>
            </div>

            {{-- Turnaround: the buyer's delay and the operator's, kept apart --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Turnaround
                </h4>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Measured separately, because a slow submission is not the operator's fault and
                    averaging the two together would flatter the number this shop controls.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Placed → TrxID sent
                        </div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                            {{ $turnaround['avg_minutes_to_submission'] !== null ? $turnaround['avg_minutes_to_submission'].' min' : '—' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            The buyer's own delay &middot; {{ $turnaround['submitted_orders'] }} submission(s)
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            TrxID → decision
                        </div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                            {{ $turnaround['avg_minutes_to_decision'] !== null ? $turnaround['avg_minutes_to_decision'].' min' : '—' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Average &middot; {{ $turnaround['decided_orders'] }} decided
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            90th percentile
                        </div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                            {{ $turnaround['p90_minutes_to_decision'] !== null ? $turnaround['p90_minutes_to_decision'].' min' : '—' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            90% were decided within this
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rejections, in the operators' own words --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Why payments were rejected
                </h4>

                @if ($reasons === [])
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        No rejections in this window.
                    </p>
                @else
                    <div class="mt-3 flex flex-col divide-y divide-gray-950/5 dark:divide-white/10">
                        @foreach ($reasons as $reason)
                            <div class="flex flex-wrap items-baseline justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                                <span class="min-w-0 flex-1 text-sm text-gray-700 dark:text-gray-200">
                                    {{ $reason['reason'] }}
                                </span>
                                <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $reason['orders'] }} order(s) &middot;
                                    {{ $this->money((int) $reason['value_minor']) }} at stake
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Grouped on the operator's own wording — the rejection form is free text, so two
                        phrasings of the same problem appear as two rows.
                    </p>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
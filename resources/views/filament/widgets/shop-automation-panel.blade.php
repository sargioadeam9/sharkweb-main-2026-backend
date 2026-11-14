<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Automations
        </x-slot>

        <x-slot name="description">
            These nudge, alert, clean up and re-send. None of them can mark an order paid — only an
            operator approving a matched TrxID does that.
        </x-slot>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->automations() as $automation)
                <div class="flex flex-col rounded-lg border border-gray-950/5 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $automation['label'] }}
                            </h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $automation['schedule'] }}
                            </p>
                        </div>

                        <x-filament::badge
                            :color="$automation['sends_email'] ? 'info' : 'gray'"
                            size="sm"
                        >
                            {{ $automation['sends_email'] ? 'Email' : 'Telegram' }}
                        </x-filament::badge>
                    </div>

                    <p class="mt-3 flex-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ $automation['description'] }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-950/5 pt-3 dark:border-white/10">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($automation['last_run'] !== null)
                                Last ran {{ $automation['last_run']->diffForHumans() }}
                            @else
                                Never run
                            @endif
                        </div>

                        @if ($automation['per_order'])
                            {{-- No "Run now": this one cannot act without a subject, so offering a
                                 button here would promise something it cannot do. --}}
                            <span class="text-xs italic text-gray-500 dark:text-gray-400">
                                Run per order, from the Orders table
                            </span>
                        @else
                            <x-filament::button
                                type="button"
                                size="sm"
                                color="gray"
                                icon="heroicon-m-play"
                                wire:click="runAutomation('{{ $automation['key'] }}')"
                                wire:confirm="Run this now? It may send emails or alerts. It cannot change any order's payment state."
                            >
                                Run now
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
            The digest of this page is the run log below. A run that found nothing to do still writes
            a line, because an automation that has silently stopped is the failure nobody notices.
            Muting any of these is a config change
            (<code class="font-mono">sharkweb.shop.automations</code>), not a code change.
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
@php
    $rows = $this->data()['rows'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Profit by product
        </x-slot>

        <x-slot name="description">
            Revenue here is the allocated, after-discount figure, so the subtraction on each row is
            honest. A dash means the line had no cost snapshot — not a profit of zero.
        </x-slot>

        @if ($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nothing sold in this window.
            </p>
        @else
            <div class="-mx-2 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th scope="col" class="px-2 pb-2 text-left font-medium">Product</th>
                            <th scope="col" class="px-2 pb-2 text-center font-medium">Units</th>
                            <th scope="col" class="px-2 pb-2 text-right font-medium">Revenue</th>
                            <th scope="col" class="px-2 pb-2 text-right font-medium">Cost</th>
                            <th scope="col" class="px-2 pb-2 text-right font-medium">Profit</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                        @foreach ($rows as $row)
                            @php
                                $uncosted = (int) $row['uncosted_lines'];
                            @endphp

                            <tr>
                                <td class="px-2 py-2.5">
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-gray-950 dark:text-white">
                                            {{ $row['label'] }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row['costed_lines'] }} costed
                                            @if ($uncosted > 0)
                                                &middot; <span class="text-warning-600 dark:text-warning-400">{{ $uncosted }} without a cost</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="px-2 py-2.5 text-center tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ $row['units'] }}
                                </td>

                                <td class="px-2 py-2.5 text-right tabular-nums text-gray-950 dark:text-white">
                                    {{ $this->money((int) $row['net_minor']) }}
                                </td>

                                <td class="px-2 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                    @if ((int) $row['costed_lines'] > 0)
                                        {{ $this->money((int) $row['cost_minor']) }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>

                                <td class="px-2 py-2.5 text-right font-semibold tabular-nums {{ $row['profit_minor'] !== null ? 'text-gray-950 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $this->moneyOrDash($row['profit_minor'] === null ? null : (int) $row['profit_minor']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rows !== [] && array_filter($rows, static fn (array $r): bool => (int) $r['uncosted_lines'] > 0) !== [])
                <p class="mt-4 text-xs leading-relaxed text-warning-700 dark:text-warning-400">
                    Rows with a dash sit on lines that were sold before a cost was recorded. Their
                    revenue is real and their margin is unknown, so it is left out rather than guessed.
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
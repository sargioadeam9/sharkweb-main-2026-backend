@php
    $d = $this->data();
    $rows = $d['rows'];
    $colours = $d['colours'];
    $total_minor = $d['total_minor'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Product performance
        </x-slot>

        <x-slot name="description">
            Ranked by revenue. Share is of the whole window, not of these rows — so a top-ten list
            does not claim to be everything that sold.
        </x-slot>

        @if ($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nothing sold in this window. An unpaid order has not sold anything.
            </p>
        @else
            <div class="-mx-2 overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th scope="col" class="px-2 pb-2 text-left font-medium">Product</th>
                            <th scope="col" class="px-2 pb-2 text-center font-medium">Units</th>
                            <th scope="col" class="px-2 pb-2 text-right font-medium">Revenue</th>
                            <th scope="col" class="px-2 pb-2 text-right font-medium">Share</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td class="px-2 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        {{-- The chart's own colour for this row, so the table and any
                                             chart of the same data read as one thing. --}}
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            style="background-color: {{ $colours[$index] ?? '#6B7280' }}"
                                            aria-hidden="true"
                                        ></span>

                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-gray-950 dark:text-white">
                                                {{ $row['label'] }}
                                            </div>

                                            @if ((int) $row['uncosted_lines'] > 0)
                                                <div class="mt-0.5 text-xs text-warning-600 dark:text-warning-400">
                                                    {{ $row['uncosted_lines'] }} line(s) with no cost
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="px-2 py-2.5 text-center tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ $row['units'] }}
                                </td>

                                <td class="px-2 py-2.5 text-right font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ $this->money((int) $row['net_minor']) }}
                                </td>

                                <td class="px-2 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-300">
                                    {{ $this->percentLabel($row['share_percent'] === null ? null : (int) $row['share_percent']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="border-t border-gray-950/10 dark:border-white/10">
                            <td class="px-2 pt-2.5 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Shown
                            </td>
                            <td></td>
                            <td class="px-2 pt-2.5 text-right font-semibold tabular-nums text-gray-950 dark:text-white">
                                {{ $this->money((int) $total_minor) }}
                            </td>
                            <td class="px-2 pt-2.5 text-right text-xs text-gray-500 dark:text-gray-400">
                                {{ count($rows) }} row(s)
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
@php
    $d = $this->data();
    $health = $d['health'];
    $slowMovers = $d['slowMovers'];
    $productsUrl = $d['productsUrl'];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Catalogue health
        </x-slot>

        <x-slot name="description">
            A published product with no file can be paid for and cannot be delivered. That is the one
            tile here worth acting on.
        </x-slot>

        <div class="flex flex-col gap-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Published
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $health['published'] }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $health['unpublished'] }} unpublished &middot; {{ $health['featured'] }} featured
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Average price
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $this->money((int) $health['average_price_minor']) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Average discount {{ $health['average_discount_percent'] }}% off MRP
                    </div>
                </div>

                {{-- The payable dead end: a real product, on sale, with nothing to hand over. --}}
                <div class="rounded-lg p-4 {{ $health['without_file'] > 0 ? 'bg-danger-50 dark:bg-danger-500/10' : 'bg-gray-50 dark:bg-white/5' }}">
                    <div class="text-xs font-medium uppercase tracking-wide {{ $health['without_file'] > 0 ? 'text-danger-700 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }}">
                        No file to deliver
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $health['without_file'] }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($health['without_file'] > 0)
                            Buyers are told the files are being prepared
                        @else
                            Every published product has a file
                        @endif
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        No cost set
                    </div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $health['without_cost'] }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Excluded from every profit figure
                    </div>
                </div>
            </div>

            {{-- Never bought — "never sold" means never PAID for, not never ordered --}}
            <div>
                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                    Never bought
                </h4>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Published products with no <em>paid</em> order. An abandoned unpaid order has not
                    sold anything, so it does not count.
                </p>

                @if ($slowMovers === [])
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Every published product has been bought at least once.
                    </p>
                @else
                    <div class="mt-3 flex flex-col divide-y divide-gray-950/5 dark:divide-white/10">
                        @foreach ($slowMovers as $product)
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $product['title'] }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $product['category'] }} &middot; /products/{{ $product['slug'] }}
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    @if (! $product['has_file'])
                                        <x-filament::badge color="danger" size="sm">
                                            No file
                                        </x-filament::badge>
                                    @endif

                                    @if (! $product['has_cost'])
                                        <x-filament::badge color="gray" size="sm">
                                            No cost
                                        </x-filament::badge>
                                    @endif

                                    <span class="text-sm tabular-nums text-gray-600 dark:text-gray-300">
                                        {{ $this->money((int) $product['price_minor']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <x-filament::button :href="$productsUrl" tag="a" size="sm" color="gray" outlined icon="heroicon-m-shopping-bag">
                    Open products
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
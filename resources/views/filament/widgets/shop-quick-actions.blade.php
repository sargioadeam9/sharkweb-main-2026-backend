<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick actions
        </x-slot>

        <x-slot name="description">
            The buttons that run a sweep cannot approve a payment — money is only released by an
            operator on the orders screen.
        </x-slot>

        <div class="flex flex-wrap gap-2">
            @foreach ($this->actions() as $action)
                @if ($action['action'] !== null)
                    {{-- An automation. It nudges, alerts or cleans up; it never changes an order's
                         payment state. --}}
                    <x-filament::button
                        type="button"
                        size="sm"
                        :color="$action['color']"
                        :icon="$action['icon']"
                        wire:click="runAutomation('{{ $action['action'] }}')"
                    >
                        {{ $action['label'] }}
                    </x-filament::button>
                @else
                    <x-filament::button
                        :href="$action['url']"
                        tag="a"
                        size="sm"
                        :color="$action['color']"
                        :icon="$action['icon']"
                        :target="$action['external'] ? '_blank' : null"
                        :rel="$action['external'] ? 'noopener noreferrer' : null"
                    >
                        {{ $action['label'] }}
                    </x-filament::button>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->actions() as $action)
                @php
                    $isExternal = str_starts_with($action['url'], 'http');
                @endphp
                <x-filament::button
                    :href="$action['url']"
                    :icon="$action['icon']"
                    :color="$action['color']"
                    size="sm"
                    tag="a"
                    :target="$isExternal ? '_blank' : null"
                    :rel="$isExternal ? 'noopener noreferrer' : null"
                >
                    {{ $action['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if (app()->isDownForMaintenance())
                <x-filament::button
                    type="button"
                    color="success"
                    icon="heroicon-o-play"
                    wire:click="disable"
                    wire:confirm="Bring the site back online? Visitors will see the live site again."
                >
                    Bring the site online
                </x-filament::button>
            @else
                <x-filament::button
                    type="button"
                    color="danger"
                    icon="heroicon-o-pause"
                    wire:click="enable"
                    wire:confirm="Take the site down? Save the bypass link from the notification — the admin panel becomes unreachable without it."
                >
                    Take the site down
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>

<x-filament-panels::page>
    @php
        /* Livewire computed properties are only reachable through $this. */
        $diskUsage = $this->diskUsage;
        $categories = $this->categories;
        $neverTouch = $this->neverTouch;
        $pruneCounts = $this->pruneCounts;

        $tone = $diskUsage === null
            ? 'gray'
            : ($diskUsage['percent'] >= 90 ? 'danger' : ($diskUsage['percent'] >= 75 ? 'warning' : 'success'));
    @endphp

    {{--
        WHY THIS PAGE CARRIES ITS OWN <style> BLOCK.

        The panel ships Filament's precompiled CSS, which contains only the utilities
        Filament's own components use — and the panel is FORCED DARK. Utility classes such
        as `bg-gray-50`, `bg-danger-50` or `bg-success-500` simply do not exist in that
        bundle, so cards written with them kept their light backgrounds (near-white) on a
        near-black page — the "white screen" mismatch.

        Everything custom here is therefore built from Filament's own CSS variables
        (injected at runtime, e.g. `--danger-500: 239, 68, 68`) with fallbacks, and each
        colour has an explicit `.dark` variant. Buttons and cards use Filament components,
        whose styling is always in the bundle.
    --}}
    <style>
        .sw-muted { font-size: .875rem; color: rgb(var(--gray-500, 107, 114, 128)); }
        .dark .sw-muted { color: rgb(var(--gray-400, 156, 163, 175)); }

        .sw-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8125rem; }

        .sw-bar { height: .75rem; width: 100%; border-radius: 9999px; overflow: hidden;
                  background-color: rgb(var(--gray-200, 229, 231, 235)); }
        .dark .sw-bar { background-color: rgb(var(--gray-800, 31, 41, 55)); }
        .sw-bar > span { display: block; height: 100%; border-radius: 9999px; }

        .sw-alert { margin-top: 1rem; padding: 1rem; border-radius: .75rem;
                    border: 1px solid rgba(var(--danger-500, 239, 68, 68), .5);
                    background-color: rgba(var(--danger-500, 239, 68, 68), .1); }
        .sw-alert-title { font-weight: 600; color: rgb(var(--danger-600, 220, 38, 38)); }
        .dark .sw-alert-title { color: rgb(var(--danger-400, 248, 113, 113)); }

        .sw-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .sw-box { border: 1px solid rgb(var(--gray-200, 229, 231, 235)); border-radius: .75rem; padding: 1rem; }
        .dark .sw-box { border-color: rgb(var(--gray-700, 55, 65, 81)); }

        .sw-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        .sw-stack { display: grid; gap: 1.5rem; }
        .sw-warning { font-size: .875rem; color: rgb(var(--warning-600, 217, 119, 6)); }
        .dark .sw-warning { color: rgb(var(--warning-400, 251, 191, 36)); }
    </style>

    <div class="sw-stack">
        {{-- Disk usage --}}
        <x-filament::section
            heading="Disk usage"
            description="The cPanel quota this cleaner exists to defend."
        >
            @if ($diskUsage !== null)
                <p class="sw-muted">
                    {{ \App\Filament\Pages\ManageCleaner::formatBytes($diskUsage['used']) }} used of
                    {{ \App\Filament\Pages\ManageCleaner::formatBytes($diskUsage['total']) }} —
                    {{ \App\Filament\Pages\ManageCleaner::formatBytes($diskUsage['free']) }} free
                    ({{ $diskUsage['percent'] }}% full)
                </p>

                <div class="sw-bar" style="margin-top: .75rem">
                    <span style="width: {{ min($diskUsage['percent'], 100) }}%;
                        background-color: rgb(var(--{{ $tone }}-500, 107, 114, 128))"></span>
                </div>
            @else
                <p class="sw-muted">Disk usage is not available on this system.</p>
            @endif
        </x-filament::section>

        {{-- Junk categories --}}
        @forelse ($categories as $category)
            <x-filament::section :heading="$category['label']" :description="$category['description']">
                <div class="sw-row">
                    <div>
                        <x-filament::badge color="gray">
                            {{ count($category['items']) }} file{{ count($category['items']) === 1 ? '' : 's' }}
                            · {{ \App\Filament\Pages\ManageCleaner::formatBytes($category['size']) }}
                        </x-filament::badge>

                        @if ($category['warning'] !== null)
                            <p class="sw-warning" style="margin-top: .5rem">{{ $category['warning'] }}</p>
                        @endif
                    </div>

                    <x-filament::button
                        color="danger"
                        icon="heroicon-o-trash"
                        wire:click="askClean('{{ $category['key'] }}')"
                    >
                        Clean
                    </x-filament::button>
                </div>

                {{-- Per-category confirmation --}}
                @if ($confirmingClean === $category['key'])
                    <div class="sw-alert">
                        <p class="sw-alert-title">
                            Delete {{ count($category['items']) }} file{{ count($category['items']) === 1 ? '' : 's' }}
                            ({{ \App\Filament\Pages\ManageCleaner::formatBytes($category['size']) }})?
                            This cannot be undone.
                        </p>

                        <div style="margin-top: .75rem; display: flex; gap: .75rem">
                            <x-filament::button color="danger" wire:click="confirmClean">
                                Yes, delete
                            </x-filament::button>

                            <x-filament::button color="gray" wire:click="cancelClean">
                                Cancel
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="sw-muted">Nothing to clean — every whitelisted category is empty.</p>
            </x-filament::section>
        @endforelse

        {{-- Database pruning --}}
        <x-filament::section
            heading="Database pruning"
            description="Tables that grow without bound. These also run on schedule; the buttons are for immediate relief."
        >
            <div class="sw-grid">
                <div class="sw-box">
                    <p style="font-weight: 600">Webhook logs</p>
                    <p class="sw-muted" style="margin: .25rem 0 .75rem">
                        {{ number_format($pruneCounts['webhook_logs']) }} rows
                    </p>

                    <x-filament::button color="gray" size="sm" wire:click="pruneModel('webhook_logs')">
                        Prune now
                    </x-filament::button>
                </div>
            </div>

            <div class="sw-row" style="margin-top: 1rem; justify-content: flex-start">
                <x-filament::button color="gray" size="sm" wire:click="runActivityRollup">
                    Run activity rollup &amp; prune
                </x-filament::button>

                <p class="sw-muted">
                    Folds raw activity into daily rollups (kept forever) and deletes raw rows older than
                    {{ \Illuminate\Support\Facades\Config::get('sharkweb.activity.retention_days', 180) }} days.
                </p>
            </div>
        </x-filament::section>

        {{-- Safety rail --}}
        <x-filament::section
            icon="heroicon-o-shield-check"
            icon-color="success"
            heading="Never touched by the cleaner"
            description="The cleaner only deletes files in the categories above — an explicit whitelist. These paths are guarded in the service itself and can never be removed by it."
        >
            <ul style="display: grid; gap: .25rem">
                @foreach ($neverTouch as $path)
                    <li class="sw-mono">{{ $path }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    @php
        $activeRun = $this->activeRun;
        $lastRun = $this->lastRun;
        $bytes = fn (int $bytes): string => number_format($bytes / 1024, 1).' KB';
    @endphp

    {{--
        SAME STYLING CONTRACT AS THE CLEANER PAGE.

        The panel ships Filament's precompiled CSS and runs in FORCED DARK mode; utilities
        like `bg-amber-50`, `bg-danger-50` or `dark:bg-gray-800/60` are not in that bundle,
        so cards built from them stayed light on a dark page. Custom colours here come from
        Filament's own CSS variables (with fallbacks) and carry explicit `.dark` variants;
        cards and buttons use Filament components.
    --}}
    <style>
        .sw-muted { font-size: .875rem; color: rgb(var(--gray-500, 107, 114, 128)); }
        .dark .sw-muted { color: rgb(var(--gray-400, 156, 163, 175)); }

        .sw-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .8125rem; }

        .sw-panel { margin-top: 1rem; padding: 1rem; border-radius: .75rem; }
        .sw-panel-amber { border: 1px solid rgba(var(--warning-500, 234, 179, 8), .5);
                          background-color: rgba(var(--warning-500, 234, 179, 8), .1); }
        .sw-panel-danger { border: 1px solid rgba(var(--danger-500, 239, 68, 68), .5);
                           background-color: rgba(var(--danger-500, 239, 68, 68), .1); }

        .sw-title { font-weight: 600; margin-bottom: .5rem; }
        .sw-title-amber { color: rgb(var(--warning-600, 217, 119, 6)); }
        .dark .sw-title-amber { color: rgb(var(--warning-400, 251, 191, 36)); }
        .sw-title-danger { color: rgb(var(--danger-600, 220, 38, 38)); }
        .dark .sw-title-danger { color: rgb(var(--danger-400, 248, 113, 113)); }

        .sw-bar { height: .75rem; width: 100%; border-radius: 9999px; overflow: hidden;
                  background-color: rgb(var(--gray-200, 229, 231, 235)); }
        .dark .sw-bar { background-color: rgb(var(--gray-800, 31, 41, 55)); }
        .sw-bar > span { display: block; height: 100%; border-radius: 9999px;
                         background-color: rgb(var(--warning-500, 234, 179, 8)); transition: width .3s; }

        .sw-table-wrap { margin-top: .75rem; overflow-x: auto; border-radius: .75rem;
                         border: 1px solid rgb(var(--gray-200, 229, 231, 235)); }
        .dark .sw-table-wrap { border-color: rgb(var(--gray-700, 55, 65, 81)); }
        .sw-table { width: 100%; font-size: .875rem; }
        .sw-table th, .sw-table td { padding: .625rem 1rem; text-align: left; }
        .sw-table th { font-weight: 500; background-color: rgb(var(--gray-50, 249, 250, 251)); }
        .dark .sw-table th { background-color: rgb(var(--gray-800, 31, 41, 55)); }
        .sw-table tbody tr { border-top: 1px solid rgb(var(--gray-200, 229, 231, 235)); }
        .dark .sw-table tbody tr { border-color: rgb(var(--gray-700, 55, 65, 81)); }
        .sw-right { text-align: right; }
        .sw-nowrap { white-space: nowrap; }
        .sw-tabular { font-variant-numeric: tabular-nums; }
        .sw-row { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .sw-between { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        .sw-danger-text { color: rgb(var(--danger-600, 220, 38, 38)); }
        .dark .sw-danger-text { color: rgb(var(--danger-400, 248, 113, 113)); }
        .sw-success-text { color: rgb(var(--success-600, 22, 163, 74)); }
        .dark .sw-success-text { color: rgb(var(--success-400, 74, 222, 128)); }
        .sw-input { width: 100%; border-radius: .5rem; padding: .5rem .75rem;
                    border: 1px solid rgb(var(--gray-300, 209, 213, 219));
                    background-color: rgb(var(--gray-50, 249, 250, 251));
                    color: rgb(var(--gray-950, 3, 7, 18)); }
        .dark .sw-input { border-color: rgb(var(--gray-700, 55, 65, 81));
                          background-color: rgb(var(--gray-900, 17, 24, 39));
                          color: rgb(var(--gray-50, 249, 250, 251)); }
    </style>

    {{-- While a restore is running the page polls itself; each poll advances one chunk. --}}
    <div {!! $activeRun !== null ? 'wire:poll.1s="advanceRestore"' : '' !!}>
        {{-- Active restore progress --}}
        @if ($activeRun)
            <div class="sw-panel sw-panel-amber">
                <h3 class="sw-title sw-title-amber">Database restore in progress</h3>

                <div class="sw-bar" style="margin-bottom: .5rem">
                    <span style="width: {{ $activeRun->percentComplete() }}%"></span>
                </div>

                <p class="sw-muted">
                    {{ number_format($activeRun->processed_statements) }} / {{ number_format($activeRun->total_statements) }} statements
                    · {{ $activeRun->percentComplete() }}%
                    — keep this page open.
                </p>
            </div>
        @endif

        {{-- Typed confirmation card --}}
        @if ($confirmingRestore !== null && $activeRun === null)
            <div class="sw-panel sw-panel-danger">
                <h2 class="sw-title sw-title-danger">
                    Restore database from <code class="sw-mono">{{ basename($confirmingRestore) }}</code>?
                </h2>

                <ul class="sw-muted" style="list-style: disc; padding-left: 1.25rem; display: grid; gap: .25rem">
                    <li>Every table in the live database is dropped and rebuilt from the backup.</li>
                    <li>Anything saved since the backup is gone. A safety backup is taken first.</li>
                    <li>Your admin session ends when the database is replaced — log in again.</li>
                </ul>

                <label style="display: block; margin: 1rem 0 .5rem; font-size: .875rem; font-weight: 500">
                    Type <strong>RESTORE</strong> to confirm
                </label>

                <input
                    type="text"
                    class="sw-input"
                    wire:model="restoreConfirmation"
                    placeholder="RESTORE"
                />

                <div class="sw-row" style="margin-top: 1rem">
                    <x-filament::button
                        color="danger"
                        icon="heroicon-o-exclamation-triangle"
                        wire:click="confirmRestore"
                        wire:loading.attr="disabled"
                        :disabled="trim($restoreConfirmation) !== 'RESTORE'"
                    >
                        Restore now
                    </x-filament::button>

                    <x-filament::button color="gray" wire:click="cancelRestore">
                        Cancel
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Last run outcome --}}
        @if ($lastRun !== null && $activeRun === null)
            <p class="sw-muted" style="margin-top: 1rem">
                Last restore:
                <span class="{{ $lastRun->status === 'done' ? 'sw-success-text' : ($lastRun->status === 'failed' ? 'sw-danger-text' : '') }}">
                    {{ $lastRun->status }}
                </span>
                @if ($lastRun->finished_at)
                    · {{ $lastRun->finished_at->timezone(config('sharkweb.filament.timezone'))->format('d M Y, H:i') }}
                @endif
                @if ($lastRun->safety_backup)
                    · safety backup: <span class="sw-mono">{{ $lastRun->safety_backup }}</span>
                @endif
                @if ($lastRun->error)
                    · <span class="sw-danger-text">{{ $lastRun->error }}</span>
                @endif
            </p>
        @endif

        {{-- Backups table --}}
        <div class="sw-between" style="margin-top: 1.5rem">
            <h2 style="font-size: 1.125rem; font-weight: 600">Backups</h2>

            <x-filament::button
                color="primary"
                icon="heroicon-o-plus"
                wire:click="runBackup"
                wire:loading.attr="disabled"
            >
                Run backup now
            </x-filament::button>
        </div>

        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Created</th>
                        <th class="sw-right">Size</th>
                        <th class="sw-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->backups as $backup)
                        <tr>
                            <td class="sw-mono">{{ $backup['name'] }}</td>
                            <td class="sw-nowrap">
                                {{ $backup['date']->timezone(config('sharkweb.filament.timezone'))->format('d M Y, H:i') }}
                                <span class="sw-muted">({{ $this->formatAge($backup['date']) }})</span>
                            </td>
                            <td class="sw-right sw-nowrap sw-tabular">{{ $bytes($backup['size']) }}</td>
                            <td class="sw-right sw-nowrap">
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    icon="heroicon-o-arrow-down-tray"
                                    wire:click="downloadBackup('{{ $backup['path'] }}')"
                                >
                                    Download
                                </x-filament::button>

                                @if ($backup['restorable'] && $activeRun === null)
                                    <x-filament::button
                                        size="sm"
                                        color="danger"
                                        wire:click="askRestore('{{ $backup['path'] }}')"
                                    >
                                        Restore
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="sw-muted" style="text-align: center; padding: 1.5rem 1rem">
                                No backups yet — run one now, and the nightly 02:00 schedule keeps them coming.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="sw-muted" style="margin-top: .75rem">
            Restore replays <strong>the database only</strong> — uploaded media stays as-is. Nightly backups run at 02:00 and rotate automatically.
        </p>
    </div>
</x-filament-panels::page>

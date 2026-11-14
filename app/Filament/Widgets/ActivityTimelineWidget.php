<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Spatie\Activitylog\Models\Activity;

/**
 * Recent admin activity (§11 — Activity Timeline Widget).
 *
 * Backed by `spatie/laravel-activitylog`, which is wired to the content models. This is the
 * answer to "who changed that article?" — and on a project where the owner and a contractor
 * may both have logins, that question comes up.
 *
 * Read-only by construction: a TableWidget has no create or edit actions unless you add them.
 */
class ActivityTimelineWidget extends TableWidget
{
    protected static ?string $heading = 'Recent activity';

    /**
     * Always last. This is an audit trail — you come looking for it after something looks
     * wrong, so it should never push the numbers you check daily further down.
     */
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest('id'))
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25])
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->tooltip(fn (Activity $record) => $record->created_at?->format('j M Y, H:i:s')),

                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (Activity $record): string => match ($record->event) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'updated' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Item')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? class_basename($state)
                        : '—')
                    ->description(fn (Activity $record): ?string => $record->subject_id
                        ? '#'.$record->subject_id
                        : null),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('system')
                    ->badge()
                    ->color('gray'),
            ])
            ->emptyStateHeading('No activity recorded yet')
            ->emptyStateDescription('Edits to content, leads and settings appear here.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /** Nothing to show on a fresh install, and an empty table on a dashboard is noise. */
    public static function canView(): bool
    {
        return Activity::query()->exists();
    }
}

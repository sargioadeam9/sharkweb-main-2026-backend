<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Leads that have been sitting unanswered for more than a day.
 *
 * WHY THIS IS THE MOST IMPORTANT WIDGET ON THE DASHBOARD. On the day this was written,
 * eight of nineteen leads were in exactly this state. Every one of them was paid for,
 * either in ad spend or in the time it took to earn the visit, and none of them had been
 * picked up. Nothing in the panel showed it: the leads list is sorted newest-first, so the
 * neglected ones sink further out of sight with every new enquiry that arrives.
 *
 * SORTED OLDEST FIRST, deliberately. The lead that has waited longest is the one most
 * likely to be lost, and it is the one furthest down the default list.
 *
 * THE 24 HOUR LINE IS A JUDGEMENT, NOT A RULE. "Reply within 24 hours" is the promise the
 * site makes on the contact page, so a lead older than that is a broken promise rather
 * than a slow one.
 */
class UnansweredLeads extends TableWidget
{
    protected static ?string $heading = 'Waiting for a reply';

    protected static ?string $description = 'Still marked new after 24 hours. The site promises a reply within one day.';

    /**
     * Directly under the numbers, above the analysis charts.
     *
     * The order of a dashboard is an argument about what matters. "Eight leads are waiting"
     * is a thing to do; "leads by channel" is a thing to understand. The doing comes first.
     */
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Lead::query()
                    ->where('status', LeadStatus::New)
                    ->where('created_at', '<', now()->subDay())
                    ->oldest('created_at'),
            )
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waiting')
                    ->since()
                    ->tooltip(fn (Lead $record): ?string => $record->created_at?->format('j M Y, H:i'))
                    // The colour is the whole message: anything past a day is a broken
                    // promise, past three is probably gone.
                    ->color(fn (Lead $record): string => $record->created_at !== null && $record->created_at->lt(now()->subDays(3))
                        ? 'danger'
                        : 'warning')
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Lead $record): ?string => $record->email),

                Tables\Columns\TextColumn::make('goal')
                    ->label('Wants')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('lead_score')
                    ->label('Score')
                    ->badge()
                    ->formatStateUsing(fn (Lead $record): string => ucfirst($record->lead_score?->value ?? 'unknown'))
                    ->color(fn (Lead $record): string => match ($record->lead_score?->value) {
                        'hot' => 'danger',
                        'warm' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('source_page')
                    ->label('From')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Lead $record): string => LeadResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Nothing is waiting')
            ->emptyStateDescription('Every lead from the last day has been picked up.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /** With no leads at all, an empty table is noise. */
    public static function canView(): bool
    {
        return Lead::query()->exists();
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Exports\LeadsExport;
use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Services\LeadService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Leads — the sales pipeline (§Module A).
 *
 * This is the screen the owner actually lives in, so it is optimised for triage rather
 * than data entry:
 *  - colour-coded score and status badges, taken straight from the enums
 *  - one-click status transitions, because updating a lead should not cost three clicks
 *  - bulk CSV export for CRM hand-off
 *  - a Trash tab, because leads ARE the pipeline and a mis-click must be recoverable
 *
 * Creating a lead by hand stays available (a lead can arrive by phone), but the normal
 * path is the public API.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Pipeline';

    protected static ?int $navigationSort = 1;

    /** Unresolved leads are the whole point of the badge — it is a to-do counter. */
    public static function getNavigationBadge(): ?string
    {
        $open = Lead::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /* ================================================================== *
     * Form
     * ================================================================== */

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contact')
                ->description('Who got in touch.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(20),
                    Forms\Components\TextInput::make('goal')
                        ->maxLength(100)
                        ->placeholder('Growth Audit'),
                ]),

            Forms\Components\Section::make('Enquiry')
                ->schema([
                    Forms\Components\Textarea::make('message')
                        ->rows(5)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Pipeline')
                ->description('Where this lead stands and what you have done about it.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(LeadStatus::class)
                        ->default(LeadStatus::New)
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('lead_score')
                        ->options(LeadScore::class)
                        ->default(LeadScore::Warm)
                        ->required()
                        ->native(false)
                        // Scoring is automatic on submission; this is the manual override.
                        ->helperText('Set automatically on submission — override if needed.'),
                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Internal notes')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Never shown to the client.'),
                ]),

            Forms\Components\Section::make('Attribution')
                ->description('Captured from the submission — used to judge which channels actually produce leads.')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('utm_source')->maxLength(100),
                    Forms\Components\TextInput::make('utm_medium')->maxLength(100),
                    Forms\Components\TextInput::make('utm_campaign')->maxLength(100),
                    Forms\Components\TextInput::make('utm_term')->maxLength(100),
                    Forms\Components\TextInput::make('utm_content')->maxLength(100),
                    Forms\Components\TextInput::make('source_page')->maxLength(50),
                    Forms\Components\TextInput::make('referrer_url')
                        ->columnSpan(2)
                        ->maxLength(500),
                    Forms\Components\TextInput::make('ip_address')
                        ->label('IP address')
                        ->maxLength(45),
                    Forms\Components\Textarea::make('score_reasons')
                        ->label('Score reasons')
                        ->rows(2)
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /* ================================================================== *
     * Infolist — read-only detail view
     * ================================================================== */

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Contact')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('email')->copyable(),
                    Infolists\Components\TextEntry::make('phone')->placeholder('—'),
                ]),

            Infolists\Components\Section::make('Status')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('lead_score')->badge(),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Submitted')
                        ->dateTime('j M Y, H:i'),
                ]),

            Infolists\Components\Section::make('Message')
                ->schema([
                    Infolists\Components\TextEntry::make('message')
                        ->placeholder('No message supplied.')
                        ->prose(),
                ]),

            Infolists\Components\Section::make('Attribution')
                ->columns(3)
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('utm_source')->placeholder('—'),
                    Infolists\Components\TextEntry::make('utm_medium')->placeholder('—'),
                    Infolists\Components\TextEntry::make('utm_campaign')->placeholder('—'),
                    Infolists\Components\TextEntry::make('source_page')->placeholder('—'),
                    Infolists\Components\TextEntry::make('referrer_url')->placeholder('—')->columnSpan(2),
                    Infolists\Components\TextEntry::make('ip_address')->placeholder('—'),
                    Infolists\Components\TextEntry::make('score_reasons')
                        ->badge()
                        ->placeholder('—')
                        ->listWithLineBreaks()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /* ================================================================== *
     * Table
     * ================================================================== */

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('lead_score')
                    ->label('Score')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Lead $record): string => (string) $record->email)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('goal')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->limit(48)
                    ->placeholder('—')
                    ->tooltip(fn (Lead $record) => $record->message)
                    ->toggleable(),

                // Quick signal for whether paid traffic is actually producing leads.
                Tables\Columns\IconColumn::make('has_attribution')
                    ->label('Campaign')
                    ->state(fn (Lead $record): bool => $record->hasAttribution())
                    ->boolean()
                    ->trueIcon('heroicon-o-megaphone')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),

                // Whether the booking invitation has gone out. A separate fact from the
                // status badge: a lead can be Qualified by hand and never invited.
                Tables\Columns\IconColumn::make('qualified_invite_sent_at')
                    ->label('Invited')
                    ->state(fn (Lead $record): bool => $record->qualified_invite_sent_at !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-calendar-days')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Lead $record): ?string => $record->qualified_invite_sent_at?->format('j M Y, H:i'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable()
                    ->tooltip(fn (Lead $record) => $record->created_at?->format('j M Y, H:i')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(LeadStatus::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('lead_score')
                    ->label('Score')
                    ->options(LeadScore::class)
                    ->multiple(),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                Tables\Filters\Filter::make('campaign_only')
                    ->label('Campaign traffic only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $q) {
                        $q->whereNotNull('utm_source')
                            ->orWhereNotNull('utm_medium')
                            ->orWhereNotNull('utm_campaign');
                    })),

                // Without this, a deleted lead is unrecoverable from the UI.
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                /*
                 * Approve and invite.
                 *
                 * DELIBERATELY NOT WIRED TO "Mark qualified". Status changes happen in a
                 * hurry, and the bulk action changes twenty at once; an email to a client
                 * must never be a side effect of a dropdown. This is its own button, with
                 * its own confirmation, and it is the only thing in the panel that can send
                 * the booking invitation.
                 *
                 * It reads `qualified_invite_sent_at` for its label, so a lead that has
                 * already been invited offers "Resend invite" instead of quietly sending a
                 * second copy on the next click.
                 */
                Tables\Actions\Action::make('approve_invite')
                    ->label(fn (Lead $record): string => $record->qualified_invite_sent_at !== null ? 'Resend invite' : 'Approve & invite')
                    ->icon('heroicon-o-calendar-days')
                    ->color(fn (Lead $record): string => $record->qualified_invite_sent_at !== null ? 'gray' : 'success')
                    // No address, no button: there is nowhere to send it.
                    ->visible(fn (Lead $record): bool => filled($record->email)
                        && ! in_array($record->status, [LeadStatus::Spam, LeadStatus::Closed], true))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Lead $record): string => $record->qualified_invite_sent_at !== null
                        ? 'Send the invitation again?'
                        : 'Send the booking invitation?')
                    ->modalDescription(fn (Lead $record): string => "This emails {$record->email} a link to the booking calendar and marks the lead Qualified.")
                    ->modalSubmitActionLabel(fn (Lead $record): string => $record->qualified_invite_sent_at !== null ? 'Resend it' : 'Send it')
                    ->action(function (Lead $record): void {
                        try {
                            app(LeadService::class)->sendQualifiedInvite($record);
                        } catch (Throwable $e) {
                            // Nothing was stamped, so the button is still there to retry.
                            Notification::make()
                                ->title('The invitation was not sent')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Invitation sent')
                            ->body("{$record->displayName()} can now pick a time in your calendar.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    self::statusAction('contacted', LeadStatus::Contacted, 'heroicon-o-paper-airplane', 'Mark contacted'),
                    self::statusAction('qualified', LeadStatus::Qualified, 'heroicon-o-check-badge', 'Mark qualified'),
                    self::statusAction('closed', LeadStatus::Closed, 'heroicon-o-archive-box', 'Close lead'),
                    self::statusAction('spam', LeadStatus::Spam, 'heroicon-o-shield-exclamation', 'Mark as spam'),
                ])
                    ->label('Change status')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->button(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    self::exportAction(),

                    Tables\Actions\BulkAction::make('bulk_status')
                        ->label('Change status')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options(LeadStatus::class)
                                ->required()
                                ->native(false),
                        ])
                        ->action(function ($records, array $data): void {
                            $records->each->update(['status' => $data['status']]);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Status updated'),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No leads yet')
            ->emptyStateDescription('Submissions from the contact form and the 60-second audit appear here.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    /* ================================================================== *
     * Actions
     * ================================================================== */

    /** A single-click status transition for the row action group. */
    private static function statusAction(
        string $name,
        LeadStatus $status,
        string $icon,
        string $label,
    ): Tables\Actions\Action {
        return Tables\Actions\Action::make("mark_{$name}")
            ->label($label)
            ->icon($icon)
            ->color(match ($status) {
                LeadStatus::Spam => 'danger',
                LeadStatus::Qualified => 'success',
                default => 'gray',
            })
            // Hide once the lead already holds that status, so the menu only ever offers
            // transitions that would actually change something.
            ->visible(fn (Lead $record): bool => $record->status !== $status)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription(fn (Lead $record): string => "Move {$record->displayName()} to \"{$status->getLabel()}\"?")
            ->action(function (Lead $record) use ($status): void {
                $record->update(['status' => $status]);

                Notification::make()
                    ->title("Marked as {$status->getLabel()}")
                    ->success()
                    ->send();
            });
    }

    /**
     * Bulk CSV export.
     *
     * Exports the selected records. LeadsExport streams via FromQuery, so selecting a
     * large batch does not hydrate every model into memory.
     */
    private static function exportAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('export_csv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function ($records) {
                $ids = $records->pluck('id')->all();

                return Excel::download(
                    new LeadsExport($ids),
                    'the-shark-web-leads-'.now()->format('Y-m-d-His').'.csv',
                    ExcelWriter::CSV,
                    ['Content-Type' => 'text/csv'],
                );
            })
            ->deselectRecordsAfterCompletion();
    }

    /* ================================================================== *
     * Pages & query
     * ================================================================== */

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Without removing the soft-delete scope, TrashedFilter has nothing to reveal.
        return parent::getEloquentQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }
}

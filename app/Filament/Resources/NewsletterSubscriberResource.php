<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use App\Services\BrevoNewsletterService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Newsletter subscribers (§Module G).
 *
 * The sync-state columns matter more than they look. A sign-up is stored locally first and
 * pushed to Brevo second, so a Brevo outage leaves rows with `brevo_synced = false` rather
 * than losing the address. The "Not synced" filter is how those get found and retried —
 * there is no queue worker on shared hosting to do it automatically, so the hourly
 * `sharkweb:sync-newsletter` command is the retry and this is the manual override.
 */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Pipeline';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Subscribers';

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): ?string
    {
        $pending = NewsletterSubscriber::query()->pendingSync()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Subscriber')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(150)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('source_page')
                        ->label('Signed up on')
                        ->maxLength(50)
                        ->placeholder('blog'),

                    Forms\Components\Toggle::make('is_verified')
                        ->label('Verified'),

                    Forms\Components\Toggle::make('brevo_synced')
                        ->label('Synced to Brevo')
                        ->helperText('Managed by the sync command — set manually only to force a retry.'),
                ]),

            Forms\Components\Section::make('Timeline')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('subscribed_at')
                        ->seconds(false),

                    Forms\Components\DateTimePicker::make('unsubscribed_at')
                        ->seconds(false)
                        ->helperText('Set this rather than deleting the row — deleting loses the record that they opted out.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('subscribed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('source_page')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('brevo_synced')
                    ->label('Brevo')
                    ->boolean()
                    // IconColumn has no per-state tooltip in Filament v3, so the reason is
                    // expressed as a single tooltip rather than two.
                    ->tooltip(fn (NewsletterSubscriber $record): string => $record->brevo_synced
                        ? 'Synced to Brevo'
                        : 'Pending — retries hourly via sharkweb:sync-newsletter'),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Joined')
                    ->since()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('unsubscribed_at')
                    ->label('Opted out')
                    ->state(fn (NewsletterSubscriber $record): bool => $record->unsubscribed_at !== null)
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\Filter::make('pending_sync')
                    ->label('Not synced to Brevo')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->pendingSync()),

                Tables\Filters\Filter::make('unsubscribed')
                    ->label('Opted out')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('unsubscribed_at')),

                Tables\Filters\Filter::make('active')
                    ->label('Active only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->subscribed()),
            ])
            ->actions([
                Tables\Actions\Action::make('resync')
                    ->label('Sync to Brevo')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (NewsletterSubscriber $record): bool => ! $record->brevo_synced)
                    ->action(function (NewsletterSubscriber $record, BrevoNewsletterService $brevo): void {
                        // Say what is actually wrong. "Brevo rejected the contact" used to be
                        // the message even when nothing had been contacted at all.
                        if (! $brevo->configured()) {
                            Notification::make()
                                ->title('Brevo is not configured')
                                ->body('Add the API key and list id in Settings → Newsletter. Nothing has been sent.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $ok = $brevo->sync($record);

                        Notification::make()
                            ->title($ok ? 'Synced to Brevo' : 'Sync failed')
                            ->body($ok
                                ? null
                                : 'Brevo rejected the contact or could not be reached. Check the webhook log.')
                            ->status($ok ? 'success' : 'danger')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('sync_selected')
                        ->label('Sync to Brevo')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function ($records, BrevoNewsletterService $brevo): void {
                            if (! $brevo->configured()) {
                                Notification::make()
                                    ->title('Brevo is not configured')
                                    ->body('Add the API key and list id in Settings → Newsletter. Nothing has been sent.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $synced = 0;

                            $records->each(function (NewsletterSubscriber $record) use ($brevo, &$synced): void {
                                if ($brevo->sync($record)) {
                                    $synced++;
                                }
                            });

                            Notification::make()
                                ->title("Synced {$synced} of {$records->count()}")
                                ->status($synced === $records->count() ? 'success' : 'warning')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No subscribers yet')
            ->emptyStateDescription('Sign-ups from the Funnel Scorecard and the blog land here.')
            ->emptyStateIcon('heroicon-o-envelope');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
            'create' => Pages\CreateNewsletterSubscriber::route('/create'),
            'edit' => Pages\EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TierResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\Tier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Pick the size of commitment" — the three-tier band on /services.
 *
 * The owner asked for this section to be editable directly, and it is the one content
 * screen small enough to understand at a glance: five display fields, a bullet list, a
 * button. It is a grid rather than a form-with-tabs because there is nothing to hide.
 *
 * WHAT IS NOT HERE: the section's own heading — "Pick the size of commitment", the
 * `# 03` kicker and the supporting paragraph. Those belong to the *page*, so they live on
 * the Services row of **Page copy**. Putting them here would mean they were duplicated
 * on every tier.
 *
 * A FOURTH TIER IS ALLOWED. The grid on the site is three columns, but adding a record
 * is not the panel's business to prevent — the owner may be mid-restructure. What the
 * panel does instead is warn, in the help text, that the design is built for three.
 */
class TierResource extends Resource
{
    protected static ?string $model = Tier::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Services';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Commitment tiers';

    protected static ?string $modelLabel = 'tier';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('The card')
                ->description('Shown in the dark band under the service catalog on /services.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(80)
                        ->placeholder('Growth sprint')
                        ->helperText('The tier\'s own heading.'),

                    Forms\Components\TextInput::make('blurb')
                        ->label('One-line description')
                        ->maxLength(160)
                        ->placeholder('A fixed-scope project, 4–8 weeks.'),

                    Forms\Components\TextInput::make('price')
                        ->required()
                        ->maxLength(64)
                        ->placeholder('from $1.5k')
                        ->helperText('Displayed at 36px. Keep it short — "from $1.5k", not "starting at $1,500 per sprint".'),

                    Forms\Components\TextInput::make('unit')
                        ->maxLength(32)
                        ->placeholder('/sprint')
                        ->helperText('The small monospace suffix beside the price.'),

                    Forms\Components\Repeater::make('points')
                        ->label('What is included')
                        ->simple(
                            Forms\Components\TextInput::make('point')
                                ->hiddenLabel()
                                ->required()
                                ->maxLength(160),
                        )
                        ->addActionLabel('Add a bullet')
                        ->reorderable()
                        ->columnSpanFull()
                        ->helperText('Each line gets an arrow. Two to four reads best.'),

                    Forms\Components\TextInput::make('cta')
                        ->label('Button label')
                        ->required()
                        ->maxLength(64)
                        ->placeholder('PLAN A SPRINT')
                        ->helperText('Monospace and uppercase on the site.'),

                    Forms\Components\TextInput::make('href')
                        ->label('Button link')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/contact/#from=sprint')
                        ->helperText('The existing tiers name the enquiry type in the anchor — #from=sprint, #from=micro, #from=partner — so the brief form can label the submission.'),
                ]),

            Forms\Components\Section::make('Placement')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_popular')
                        ->label('Mark as most popular')
                        ->helperText('Adds the POPULAR tag and the brighter border. Use it on one tier, not two.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Show on the site')
                        ->default(true),

                    Forms\Components\TextInput::make('sort')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lowest first, left to right. The design is built for three.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Tier $record): ?string => $record->blurb),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn (Tier $record): string => trim("{$record->price} {$record->unit}"))
                    ->weight('medium'),

                Tables\Columns\IconColumn::make('is_popular')
                    ->label('Popular')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('points')
                    ->label('Bullets')
                    ->state(fn (Tier $record): string => (string) count($record->points ?? []))
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No tiers yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=tiers` to load the three published models.')
            ->emptyStateIcon('heroicon-o-squares-2x2');
    }

    /**
     * Ordering is drag-and-drop on the list screen, so `sort` is rewritten on every
     * reorder. The default `id` ordering is the tiebreak for records that share a value.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort')->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTier::route('/'),
            'create' => Pages\CreateTier::route('/create'),
            'edit' => Pages\EditTier::route('/{record}/edit'),
        ];
    }
}

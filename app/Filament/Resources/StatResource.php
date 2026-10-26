<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\StatResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\Stat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The five figures under the hero: 12 years, 50+ clients, 250K users.
 *
 * WHY THIS SCREEN EXISTS. These numbers were written into `StatsBar.tsx` and had no way
 * to change without a deploy. They are also the claim a visitor checks first, so a stale
 * one is worse than no number at all — "12 years in operation" reads as neglect once it
 * has been 12 for three years.
 *
 * THE GRID IS FIVE WIDE. The bar on the site is a five-column grid at every breakpoint,
 * so a sixth record will wrap or squash. The panel does not block it — the owner may be
 * mid-restructure — but the help text says so.
 */
class StatResource extends Resource
{
    protected static ?string $model = Stat::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Homepage';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Homepage stats';

    protected static ?string $modelLabel = 'stat';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('The figure')
                ->description('Rendered in the bar directly under the hero on the homepage.')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(40)
                        ->placeholder('clients')
                        ->helperText('The small uppercase label above the number.'),

                    Forms\Components\TextInput::make('value')
                        ->required()
                        ->maxLength(24)
                        ->placeholder('250')
                        ->helperText('The number itself. Text is allowed — "1M", "£4.2m".'),

                    Forms\Components\TextInput::make('suffix')
                        ->maxLength(8)
                        ->placeholder('K')
                        ->helperText('Drawn at half size beside the value: the K in 250K, the + in 50+.'),

                    Forms\Components\TextInput::make('caption')
                        ->maxLength(80)
                        ->placeholder('daily active users')
                        ->columnSpanFull()
                        ->helperText('Hidden on phones, shown from the md breakpoint up.'),
                ]),

            Forms\Components\Section::make('Placement')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('tone')
                        ->options(Tone::LABELS)
                        ->default('ink')
                        ->required()
                        ->helperText('Colours both the label and the number.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Show on the site')
                        ->default(true),

                    Forms\Components\TextInput::make('sort')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lowest first, left to right. The bar is built for five.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Stat $record): ?string => $record->caption),

                Tables\Columns\TextColumn::make('value')
                    ->formatStateUsing(fn (Stat $record): string => $record->value.($record->suffix ?? ''))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tone')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Tone::LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Tone::color($state)),

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
            ->emptyStateHeading('No stats yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=stats` to load the five published figures.')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort')->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStat::route('/'),
            'create' => Pages\CreateStat::route('/create'),
            'edit' => Pages\EditStat::route('/{record}/edit'),
        ];
    }
}

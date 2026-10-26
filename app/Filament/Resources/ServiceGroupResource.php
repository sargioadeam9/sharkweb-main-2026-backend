<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceGroupResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\ServiceGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The four disciplines on /services — "Growth & media", "Web & product", "AI &
 * automation", "Design & CRO".
 *
 * These are the *headings* of the catalog, and the services inside them are managed on
 * the Services screen. The relationship is on this resource as a read-only column rather
 * than an editable repeater, deliberately: a group has no copy of a service beyond its
 * name, and duplicating the service list here would give one service two places its name
 * could be edited from.
 *
 * WHY `tone` MATTERS HERE: the group's tone colours its numbered heading, and each
 * service's own tone is inherited from its group at import. Changing a group's tone does
 * not retroactively recolour its services — that is intentional, because a service page's
 * accent is also used for its stat block and CTA, and a cascade would repaint sections
 * the owner did not ask about.
 */
class ServiceGroupResource extends Resource
{
    protected static ?string $model = ServiceGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Services';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Disciplines';

    protected static ?string $modelLabel = 'discipline';

    protected static ?string $pluralModelLabel = 'Disciplines';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('The discipline')
                ->description('The catalog heading on /services, and the label used on each service page.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(80)
                        ->placeholder('Growth & media')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Forms\Set $set, string $operation): void {
                            // Only on create: an existing key is what the importer and any
                            // bookmarked deep link already match on, so renaming a group must
                            // not silently rewrite it.
                            if ($operation === 'create' && filled($state)) {
                                $set('key', \Illuminate\Support\Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->helperText('A stable identifier used by the importer. Generated from the name, but safe to edit while creating.'),

                    Forms\Components\TextInput::make('num')
                        ->label('Catalog number')
                        ->maxLength(8)
                        ->placeholder('01')
                        ->helperText('The "# 01 · 5 SERVICES" caption. Display copy, not the ordering — use Order below for that.'),

                    Forms\Components\Select::make('tone')
                        ->options(Tone::ACCENTS)
                        ->required()
                        ->default('accent')
                        ->native(false)
                        ->helperText('Colours the heading and the service-page accent bars.'),

                    Forms\Components\Textarea::make('blurb')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(400)
                        ->placeholder('Demand on purpose. Campaign systems built around offer clarity and creative testing — so ad spend buys revenue, not impressions.')
                        ->helperText('Sits to the right of the heading in the group card.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Placement')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('sort')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lowest first. Drag rows on the list screen to reorder.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Show on the site')
                        ->default(true)
                        ->helperText('Hiding a discipline hides its services from the catalog, but each service page keeps its own URL.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('num')
                    ->label('#')
                    ->alignCenter()
                    ->width('60px')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (ServiceGroup $record): ?string => $record->blurb),

                Tables\Columns\TextColumn::make('tone')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Tone::LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Tone::color($state)),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('The services inside this discipline stay on the site, but lose their catalog grouping until reassigned.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No disciplines yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=services` to load the four published groups.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('sort')
            ->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceGroup::route('/'),
            'create' => Pages\CreateServiceGroup::route('/create'),
            'edit' => Pages\EditServiceGroup::route('/{record}/edit'),
        ];
    }
}

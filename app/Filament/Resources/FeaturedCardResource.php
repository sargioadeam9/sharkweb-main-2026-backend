<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FeaturedCardResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\FeaturedCard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "# 02 — FEATURED PROJECTS" deck on the homepage.
 *
 * WHY THIS IS NOT A FLAG ON CASE STUDIES. It was tempting: the homepage shows five
 * projects and `/work` shows five cases, so surely one table with a `featured` column.
 * But they are different sets. The deck shows Crawler, Retail Network, EpinForce, Blaze
 * Reviews and 99designs Contest Winners; `/work` has Aryia Leather, Firstway Global,
 * ZIVO Audio, FishnFruit and WinPro Digital. None of the five featured projects has a
 * published case, which is exactly why every card's button pointed at the bare `/work/`
 * index — there was no page about the project to point at.
 *
 * WHAT THE OWNER CANNOT BREAK. The tilt, the horizontal shift and the z-order that make
 * the cards sit in a pile are derived from the record's position, not stored. Twenty-five
 * fields per card is a design file; five content fields and a tone is a CMS.
 */
class FeaturedCardResource extends Resource
{
    protected static ?string $model = FeaturedCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Homepage';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Featured projects';

    protected static ?string $modelLabel = 'featured project';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Front of the card')
                ->description('What the visitor sees before clicking. Shown in the "# 02 — Featured projects" deck on the homepage.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(80)
                        ->placeholder('Crawler')
                        ->helperText('The project name. Large type on the card.'),

                    Forms\Components\TextInput::make('domain')
                        ->maxLength(60)
                        ->placeholder('RECRUITMENT')
                        ->helperText('The industry tag under the name. Uppercase on the site.'),

                    Forms\Components\TextInput::make('brand')
                        ->maxLength(80)
                        ->default('THESHARKWEB.COM')
                        ->helperText('The small monospace line at the top of the card.'),

                    Forms\Components\TextInput::make('year_label')
                        ->label('Year')
                        ->maxLength(40)
                        ->placeholder('2025 (active)')
                        ->helperText('Free text — "2025 (active)" is a valid value.'),

                    Forms\Components\TextInput::make('stack')
                        ->label('Stack line')
                        ->maxLength(120)
                        ->placeholder('PYTHON · NEST · VUE · MYSQL')
                        ->helperText('Middle dots, not commas. This line is monospace and does not wrap.'),

                    Forms\Components\TextInput::make('result')
                        ->label('Result')
                        ->maxLength(160)
                        ->placeholder('Live fully automated crawler')
                        ->helperText('One line, in the accent colour.'),

                    Forms\Components\Toggle::make('badge')
                        ->label('Show the NDA badge')
                        ->helperText('Adds the small badge to the card front.'),
                ]),

            Forms\Components\Section::make('Back of the card')
                ->description('Revealed when the visitor clicks the card.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('back_title')
                        ->label('Back heading')
                        ->maxLength(160)
                        ->helperText('Usually the same as the result line.'),

                    Forms\Components\TextInput::make('back_brand')
                        ->label('Card number')
                        ->maxLength(24)
                        ->placeholder('# 002')
                        ->helperText('Printed top-left on the back. Leave blank to use the position in the list.'),

                    Forms\Components\Textarea::make('back_desc')
                        ->label('Back paragraph')
                        ->rows(3)
                        ->maxLength(400)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('The card itself')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tone')
                        ->options(Tone::LABELS)
                        ->default('accent')
                        ->required()
                        ->helperText('Colours the brand line, the domain tag, the result and the button together.'),

                    Forms\Components\Select::make('front_bg')
                        ->label('Front background')
                        ->options([
                            'green-soft' => 'Green',
                            'blue-soft' => 'Blue',
                            'yellow-soft' => 'Yellow',
                            'magenta-soft' => 'Magenta',
                            'orange-soft' => 'Orange',
                        ])
                        ->default('blue-soft')
                        ->helperText('Kept separate from the tone because one card deliberately pairs ink with yellow.'),
                ]),

            Forms\Components\Section::make('Card details')
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('thumbs')
                        ->label('Thumbnails')
                        ->schema([
                            Forms\Components\TextInput::make('src')
                                ->label('Image path')
                                ->placeholder('/img/…')
                                ->helperText('Leave blank for an NDA or text thumb.'),
                            Forms\Components\TextInput::make('text')
                                ->label('Text thumb')
                                ->placeholder('NDA')
                                ->helperText('Shown instead of an image.'),
                            Forms\Components\Toggle::make('nda')
                                ->label('NDA lock'),
                            Forms\Components\Toggle::make('metric')
                                ->label('Metric style'),
                        ])
                        ->columns(4)
                        ->itemLabel(fn (array $state): ?string => $state['src'] ?? $state['text'] ?? null)
                        ->addActionLabel('Add a thumbnail')
                        ->reorderable()
                        ->helperText('Three fit the row. Each one is either an image path, a text label or an NDA lock.'),

                    Forms\Components\TagsInput::make('chips')
                        ->label('Tag chips')
                        ->placeholder('PYTHON')
                        ->helperText('The small tags under the stack line on the back.'),

                    Forms\Components\Repeater::make('facts')
                        ->label('Figures')
                        ->schema([
                            Forms\Components\TextInput::make('figure')->required()->placeholder('3.2x'),
                            Forms\Components\Select::make('tone')->options(Tone::LABELS)->default('green'),
                            Forms\Components\TextInput::make('label')->placeholder('MORE ORGANIC TRAFFIC'),
                            Forms\Components\TextInput::make('sub')->placeholder('in 90 days'),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add a figure')
                        ->reorderable(),

                    Forms\Components\Repeater::make('specs')
                        ->label('Spec rows')
                        ->schema([
                            Forms\Components\TextInput::make('label')->placeholder('domain'),
                            Forms\Components\TextInput::make('value')->placeholder('Recruitment'),
                        ])
                        ->columns(2)
                        ->addActionLabel('Add a row')
                        ->reorderable()
                        ->helperText('The two-column table on the back: domain, client, categories.'),
                ]),

            Forms\Components\Section::make('Button')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('cta_text')
                        ->label('Button label')
                        ->maxLength(64)
                        ->default('READ THE CASE STUDY →'),

                    Forms\Components\TextInput::make('cta_href')
                        ->label('Button link')
                        ->maxLength(255)
                        ->default('/work/')
                        ->helperText('Point it at the real case once one is written. Until then /work/ is the honest destination.'),
                ]),

            Forms\Components\Section::make('Placement')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Show on the site')
                        ->default(true),

                    Forms\Components\TextInput::make('sort')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lowest first. This also decides the card\'s tilt and how deep it sits in the pile.'),
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
                    ->description(fn (FeaturedCard $record): ?string => $record->domain),

                Tables\Columns\TextColumn::make('result')
                    ->label('Result')
                    ->limit(40)
                    ->toggleable(),

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
            ->emptyStateHeading('No featured projects yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=featured` to load the five published cards.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort')->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeaturedCard::route('/'),
            'create' => Pages\CreateFeaturedCard::route('/create'),
            'edit' => Pages\EditFeaturedCard::route('/{record}/edit'),
        ];
    }
}

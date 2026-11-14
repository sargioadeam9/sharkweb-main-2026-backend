<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "# 03 — CLIENT REVIEWS" carousel on the homepage. Eighteen records.
 *
 * WHAT IS EDITABLE. The words, the person, the avatar, the Clutch link, and the handful of
 * facts printed on the card beside the quote. What is not: the tilt, the z-order and the
 * four separate tone slots the original data carried. Every review card in the shipped set
 * is flat at z 1 — the carousel shows one at a time — so those are fixed, and the tone
 * fans out from a single choice. See `DeckCard`.
 *
 * `card_name` IS NOT THE REVIEWER. It is the project on the card: "Retail Network",
 * "StudyBooster", "SV Geo". In all eighteen records it differs from the reviewer's name,
 * which is why it is a separate field rather than being copied from `name`.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Homepage';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Client reviews';

    protected static ?string $modelLabel = 'review';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('The review')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull()
                        ->placeholder('Custom Software & Web Dev for Retail Company')
                        ->helperText('The Clutch review headline. Also the heading above the quote.'),

                    Forms\Components\Textarea::make('quote')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('The pull quote, in quotes. Set at 30px on the site, so two sentences is the limit.'),

                    Forms\Components\Textarea::make('body')
                        ->label('Supporting paragraph')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('The longer summary under the quote.'),

                    Forms\Components\TextInput::make('name')
                        ->label('Reviewer name')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('role')
                        ->label('Reviewer role')
                        ->maxLength(120)
                        ->placeholder('VP IT, NEW LOOK VISION GROUP')
                        ->helperText('Uppercase on the site.'),

                    Forms\Components\TextInput::make('avatar')
                        ->label('Avatar path')
                        ->maxLength(255)
                        ->placeholder('/img/clients/name.png')
                        ->helperText('Square, 192px or larger.'),

                    Forms\Components\TextInput::make('review_url')
                        ->label('Clutch link')
                        ->maxLength(255)
                        ->helperText('Where "read the full review" goes.'),

                    Forms\Components\Select::make('role_tone')
                        ->label('Role colour')
                        ->options(Tone::LABELS)
                        ->default('ink')
                        ->helperText('Independent of the card colour — the set pairs a green role label with an ink card.'),
                ]),

            Forms\Components\Section::make('The card beside the quote')
                ->description('Same card design as the featured projects deck, but flat and shown one at a time.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('card_name')
                        ->label('Project name on the card')
                        ->maxLength(80)
                        ->placeholder('Retail Network')
                        ->helperText('The project, not the reviewer.'),

                    Forms\Components\TextInput::make('card_domain')
                        ->label('Industry tag')
                        ->maxLength(60)
                        ->placeholder('RETAIL'),

                    Forms\Components\TextInput::make('card_brand')
                        ->label('Brand line')
                        ->maxLength(80)
                        ->default('THESHARKWEB.COM'),

                    Forms\Components\TextInput::make('card_year_label')
                        ->label('Year')
                        ->maxLength(40)
                        ->placeholder('2024'),

                    Forms\Components\TextInput::make('card_stack')
                        ->label('Stack line')
                        ->maxLength(120)
                        ->placeholder('SHOPIFY · REACT'),

                    Forms\Components\TextInput::make('card_result')
                        ->label('Result')
                        ->maxLength(160),

                    Forms\Components\TextInput::make('card_back_brand')
                        ->label('Card number')
                        ->maxLength(24)
                        ->placeholder('# 003')
                        ->helperText('Printed on the back. Each review has its own.'),

                    Forms\Components\TextInput::make('card_back_title')
                        ->label('Back heading')
                        ->maxLength(160)
                        ->helperText('Falls back to the review title when blank.'),

                    Forms\Components\Textarea::make('card_back_desc')
                        ->label('Back paragraph')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('card_tone')
                        ->label('Card tone')
                        ->options(Tone::LABELS)
                        ->default('accent'),

                    Forms\Components\Select::make('card_front_bg')
                        ->label('Front background')
                        ->options([
                            'green-soft' => 'Green',
                            'blue-soft' => 'Blue',
                            'yellow-soft' => 'Yellow',
                            'magenta-soft' => 'Magenta',
                            'orange-soft' => 'Orange',
                        ])
                        ->default('blue-soft'),

                    Forms\Components\Toggle::make('card_badge')
                        ->label('Show the NDA badge'),

                    Forms\Components\TextInput::make('card_cta_text')
                        ->label('Button label')
                        ->maxLength(64)
                        ->default('READ THE CASE STUDY →'),

                    Forms\Components\TextInput::make('card_cta_href')
                        ->label('Button link')
                        ->maxLength(255)
                        ->default('/work/'),
                ]),

            Forms\Components\Section::make('Card details')
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('card_thumbs')
                        ->label('Thumbnails')
                        ->schema([
                            Forms\Components\TextInput::make('src')->label('Image path'),
                            Forms\Components\TextInput::make('text')->label('Text thumb'),
                            Forms\Components\Toggle::make('nda')->label('NDA lock'),
                            Forms\Components\Toggle::make('metric')->label('Metric style'),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add a thumbnail')
                        ->reorderable(),

                    Forms\Components\TagsInput::make('card_chips')
                        ->label('Tag chips')
                        ->placeholder('SHOPIFY'),

                    Forms\Components\Repeater::make('card_facts')
                        ->label('Figures')
                        ->schema([
                            Forms\Components\TextInput::make('figure')->required(),
                            Forms\Components\Select::make('tone')->options(Tone::LABELS)->default('green'),
                            Forms\Components\TextInput::make('label'),
                            Forms\Components\TextInput::make('sub'),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add a figure')
                        ->reorderable(),

                    Forms\Components\Repeater::make('card_specs')
                        ->label('Spec rows')
                        ->schema([
                            Forms\Components\TextInput::make('label'),
                            Forms\Components\TextInput::make('value'),
                        ])
                        ->columns(2)
                        ->addActionLabel('Add a row')
                        ->reorderable(),
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
                        ->helperText('Lowest first. The carousel shows them in this order.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Reviewer')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Review $record): ?string => $record->role),

                Tables\Columns\TextColumn::make('title')
                    ->label('Headline')
                    ->limit(46)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('card_name')
                    ->label('Card')
                    ->toggleable()
                    ->placeholder('—'),

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
            ->emptyStateHeading('No reviews yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=reviews` to load the eighteen published reviews.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort')->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReview::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}

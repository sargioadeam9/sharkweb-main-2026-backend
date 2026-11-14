<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PageHeaderResource\Pages;
use App\Models\Content\PageHeader;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The copy above and below the content on each route — the hero, the sidebar card and
 * the closing band.
 *
 * WHY IT IS FIRST IN THE GROUP. Every page's `h1`, lead paragraph and outro were
 * hardcoded in their `page.tsx` files, outside the content modules, so making only the
 * data editable would have left the owner unable to change a headline — the first thing
 * anyone wants to change. This screen is where /services, /newsletter, /work and
 * /open-source get their headings.
 *
 * ONE ROW PER ROUTE, and the route is the lookup. New rows are therefore restricted to
 * the four routes `lib/content.ts` asks for: a fifth row would never be read.
 */
class PageHeaderResource extends Resource
{
    protected static ?string $model = PageHeader::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Page copy';

    protected static ?string $modelLabel = 'page';

    protected static ?string $pluralModelLabel = 'Page copy';

    protected static ?string $recordTitleAttribute = 'route';

    /** The only routes the frontend reads a header for. */
    private const ROUTES = [
        'services' => 'Services — /services',
        'newsletter' => 'Newsletter — /newsletter',
        'work' => 'Work — /work',
        'open-source' => 'Open source — /open-source',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Page')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('route')
                        ->options(self::ROUTES)
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->native(false)
                        ->disabledOn('edit')
                        ->helperText('Which page this copy belongs to. One row per page.'),
                ]),

            Forms\Components\Tabs::make('Copy')
                ->columnSpanFull()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Hero')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\TextInput::make('kicker')
                                ->label('Kicker')
                                ->maxLength(120)
                                ->placeholder('# WORK — 5 DOCUMENTED CASES')
                                ->helperText('The small monospace line above the page title.')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('title')
                                ->label('Page title')
                                ->maxLength(120)
                                ->placeholder('/work')
                                ->helperText('/open-source asks for copy that reads "Got a tool in mind?"')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('lead')
                                ->label('Lead paragraph')
                                ->rows(3)
                                ->maxLength(600)
                                ->helperText('Sits under the title. The page title and this paragraph are one block.')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Sidebar card')
                        ->icon('heroicon-o-bars-3')
                        ->schema([
                            Forms\Components\TextInput::make('aside_label')
                                ->label('Card label')
                                ->maxLength(64)
                                ->placeholder('SOURCE')
                                ->helperText('The small caption at the top of the card, to the right of the title.')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('aside_text')
                                ->label('Card text')
                                ->rows(4)
                                ->maxLength(600)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('cta_label')
                                ->label('Card button label')
                                ->maxLength(64)
                                ->placeholder('BROWSE THE ARCHIVE')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('cta_href')
                                ->label('Card button link')
                                ->maxLength(255)
                                ->placeholder('/contact/ or https://github.com/...')
                                ->helperText('A path inside the site, an in-page anchor like #issues, or a full URL.')
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Closing band')
                        ->icon('heroicon-o-arrow-down-on-square')
                        ->schema([
                            Forms\Components\TextInput::make('outro.label')
                                ->label('Label')
                                ->maxLength(96)
                                ->placeholder('# WORK WITH US')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('outro.title')
                                ->label('Heading')
                                ->rows(2)
                                ->maxLength(160)
                                ->placeholder("Measured on\ntheir own accounts.")
                                ->helperText('A line break splits the heading into two lines, exactly as the design does.')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('outro.text')
                                ->label('Supporting paragraph')
                                ->rows(3)
                                ->maxLength(600)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('outro.cta')
                                ->label('Button label')
                                ->maxLength(64)
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('outro.href')
                                ->label('Button link')
                                ->maxLength(255)
                                ->columnSpan(1),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('route')
                    ->label('Page')
                    ->formatStateUsing(fn (string $state): string => self::ROUTES[$state] ?? $state)
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('kicker')
                    ->label('Kicker')
                    ->limit(48)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lead')
                    ->label('Lead')
                    ->limit(56)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('route')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->paginated(false)
            ->emptyStateHeading('No page copy yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=headers` to load the current headings.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPageHeaders::route('/'),
            'create' => Pages\CreatePageHeader::route('/create'),
            'edit' => Pages\EditPageHeader::route('/{record}/edit'),
        ];
    }
}

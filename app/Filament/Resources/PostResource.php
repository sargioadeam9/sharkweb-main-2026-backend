<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * A newsletter issue.
 *
 * THE BLOCK BUILDER. `body` is an ordered list of typed blocks, and `PostBody.tsx`
 * switches on `t` to decide how each one renders. The seven block types are not a design
 * choice made here — they are the union `lib/newsletter.ts` declares, and adding an
 * eighth that the component does not switch on would store fine and render nothing.
 * So the select offers exactly those seven, and each one reveals only the fields it uses.
 *
 * WHY A REPEATER AND NOT A RICH-TEXT EDITOR (the owner's call, 2026-10-24): the existing
 * five articles were authored as HTML inside typed blocks and are ~45 blocks each. A
 * rich-text editor would flatten that structure on first save and change how the pages
 * render, which is exactly what "exactly the same editable" was meant to avoid. The
 * trade-off is a lot of rows to scroll — hence the collapsible rows and the section
 * grouping per block type.
 *
 * SOFT-DELETED, with the Trash filter exposed: an article is something somebody wrote.
 * A mis-click on a list screen must not destroy 45 blocks of work.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?string $modelLabel = 'issue';

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * The seven block shapes `PostBody.tsx` renders, with the field each one needs.
     *
     * Keyed by the `t` value stored in the JSON, because that value IS the contract.
     */
    private const BLOCK_TYPES = [
        'h2' => 'Heading — starts a section, appears in the contents list',
        'h3' => 'Subheading — inside a section',
        'p' => 'Paragraph',
        'quote' => 'Pull quote',
        'ul' => 'Bulleted list',
        'ol' => 'Numbered list',
        'table' => 'Table',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Issue')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    self::metaTab(),
                    self::bodyTab(),
                    self::closingTab(),
                ]),
        ]);
    }

    /* ================================================================== *
     * Tab 1 — how the issue appears in lists
     * ================================================================== */

    private static function metaTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Issue details')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Forms\Components\Section::make('The issue')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Forms\Set $set, string $operation): void {
                                // Only on create — changing an existing slug breaks the URL.
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(128)
                            ->unique(ignoreRecord: true)
                            ->helperText('The URL: /newsletter/{slug}/. Changing it breaks the old link.')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('category')
                            ->required()
                            ->maxLength(64)
                            ->default('Agency')
                            ->datalist(fn (): array => Post::query()
                                ->distinct()
                                ->orderBy('category')
                                ->pluck('category')
                                ->all())
                            ->helperText('Drives the filter chips on /newsletter. Reusing an existing one keeps the filter list short.'),

                        Forms\Components\Select::make('tone')
                            ->options(Tone::ACCENTS)
                            ->required()
                            ->default('accent')
                            ->native(false)
                            ->helperText('The accent on the cover art, the key-takeaways bar and the article links.'),

                        Forms\Components\DatePicker::make('date')
                            ->native(false)
                            ->displayFormat('j M Y')
                            ->helperText('The issue date shown in the byline.'),

                        Forms\Components\TextInput::make('date_label')
                            ->label('Date, as displayed')
                            ->maxLength(64)
                            ->placeholder('October 7, 2026')
                            ->helperText('Optional. Left empty, the date above is formatted for you.'),

                        Forms\Components\TextInput::make('read_time')
                            ->label('Read time')
                            ->numeric()
                            ->default(5)
                            ->suffix('minutes')
                            ->helperText('Shown in the byline and on the cover. An honest number beats a flattering one.'),

                        Forms\Components\TextInput::make('sort')
                            ->label('Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lowest first. The first live issue is the one featured at the top of /newsletter.'),

                        Forms\Components\Textarea::make('excerpt')
                            ->label('Summary')
                            ->rows(3)
                            ->maxLength(400)
                            ->helperText('The two-sentence teaser on the cards and in the page description.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Publishing')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published')
                            ->default(true)
                            ->helperText('Off keeps the issue out of every list and returns a 404 on its own URL.'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Feature at the top')
                            ->helperText('Only one issue should carry this. It becomes the "LATEST ISSUE" block.'),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->native(false)
                            ->helperText('Optional. A future time keeps the issue hidden until then.'),
                    ]),
            ]);
    }

    /* ================================================================== *
     * Tab 2 — the block builder
     * ================================================================== */

    private static function bodyTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Article body')
            ->icon('heroicon-o-bars-3-bottom-left')
            ->schema([
                Forms\Components\Section::make('Key takeaways')
                    ->description('The highlighted box above the article. Two to four reads best; leave empty to omit the box entirely.')
                    ->schema([
                        Forms\Components\Repeater::make('takeaways')
                            ->hiddenLabel()
                            ->simple(
                                Forms\Components\Textarea::make('takeaway')
                                    ->hiddenLabel()
                                    ->required()
                                    ->rows(2)
                                    ->maxLength(300),
                            )
                            ->addActionLabel('Add a takeaway')
                            ->reorderable(),
                    ]),

                Forms\Components\Section::make('Body')
                    ->description('Every heading, paragraph, list, quote and table is its own row. The order here is the order in the article.')
                    ->schema([
                        Forms\Components\Repeater::make('body')
                            ->hiddenLabel()
                            ->addActionLabel('Add a block')
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => self::blockLabel($state))
                            ->schema([
                                Forms\Components\Select::make('t')
                                    ->label('Block type')
                                    ->options(self::BLOCK_TYPES)
                                    ->required()
                                    ->default('p')
                                    ->native(false)
                                    ->live()
                                    ->columnSpanFull(),

                                /*
                                 * One field per block shape, each visible only when its
                                 * type is selected. Every one is also `dehydrated()`, so
                                 * switching a row's type does not blank the other field —
                                 * a mistake that would otherwise only surface as a missing
                                 * heading after a save.
                                 */
                                Forms\Components\Textarea::make('html')
                                    ->label('Text')
                                    ->rows(4)
                                    ->visible(fn (Forms\Get $get): bool => in_array($get('t'), ['h2', 'h3', 'p', 'quote'], true))
                                    ->required(fn (Forms\Get $get): bool => in_array($get('t'), ['h2', 'h3', 'p', 'quote'], true))
                                    ->helperText('Limited HTML is allowed — <em>, <strong>, <a href>. The text is rendered as markup, so a stray tag is visible on the page.')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('id')
                                    ->label('Anchor')
                                    ->maxLength(96)
                                    ->visible(fn (Forms\Get $get): bool => $get('t') === 'h2')
                                    ->helperText('The #fragment this heading is linkable by, and what the contents list links to. Generated from the text if left empty.')
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('items')
                                    ->label('List items')
                                    ->simple(
                                        Forms\Components\Textarea::make('item')
                                            ->hiddenLabel()
                                            ->required()
                                            ->rows(2)
                                            ->maxLength(400),
                                    )
                                    ->addActionLabel('Add an item')
                                    ->reorderable()
                                    ->visible(fn (Forms\Get $get): bool => in_array($get('t'), ['ul', 'ol'], true))
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('head')
                                    ->label('Column headings')
                                    ->simple(
                                        Forms\Components\TextInput::make('cell')
                                            ->hiddenLabel()
                                            ->required()
                                            ->maxLength(80),
                                    )
                                    ->addActionLabel('Add a column')
                                    ->reorderable()
                                    ->visible(fn (Forms\Get $get): bool => $get('t') === 'table')
                                    ->helperText('Leave empty and the first row of the table below is treated as the heading.')
                                    ->columnSpanFull(),

                                Forms\Components\Repeater::make('rows')
                                    ->label('Rows')
                                    ->simple(
                                        Forms\Components\Textarea::make('cells')
                                            ->hiddenLabel()
                                            ->required()
                                            ->rows(1)
                                            ->maxLength(600)
                                            ->helperText('One line per cell, in the same order as the columns.'),
                                    )
                                    ->addActionLabel('Add a row')
                                    ->reorderable()
                                    ->visible(fn (Forms\Get $get): bool => $get('t') === 'table')
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Forms\Components\Section::make('Contents list')
                    ->description('The sidebar navigation on an article. Leave empty and it is built from the H2 headings above.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Repeater::make('toc')
                            ->hiddenLabel()
                            // `->simple()` takes a single field, so a two-column entry has
                            // to go through `->schema()` with a Grid inside it.
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('Label')
                                        ->required()
                                        ->maxLength(160),
                                    Forms\Components\TextInput::make('id')
                                        ->label('Anchor')
                                        ->required()
                                        ->maxLength(96),
                                ]),
                            ])
                            ->addActionLabel('Add a contents entry')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->helperText('Only needed when the automatic list reads wrong — the anchors must match the H2 anchors exactly.'),
                    ]),
            ]);
    }

    /* ================================================================== *
     * Tab 3 — the Q&A under the article
     * ================================================================== */

    private static function closingTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Questions')
            ->icon('heroicon-o-question-mark-circle')
            ->schema([
                Forms\Components\Section::make('Questions and answers')
                    ->description('Rendered under the article. These are the same questions search engines pick up, so a direct answer beats a preamble.')
                    ->schema([
                        Forms\Components\Repeater::make('faq')
                            ->hiddenLabel()
                            ->addActionLabel('Add a question')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                            ->schema([
                                Forms\Components\TextInput::make('q')
                                    ->label('Question')
                                    ->required()
                                    ->maxLength(240)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('a')
                                    ->label('Answer')
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(1200)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * A readable row title for the block builder.
     *
     * Without this every collapsed row reads "Block 1", "Block 2" — which makes a 45-block
     * article impossible to navigate. The label is the block's own opening words, trimmed.
     */
    private static function blockLabel(array $state): ?string
    {
        $type = $state['t'] ?? 'p';
        $names = [
            'h2' => 'H2',
            'h3' => 'H3',
            'p' => '¶',
            'quote' => '“',
            'ul' => '• List',
            'ol' => '1. List',
            'table' => '▦ Table',
        ];

        $prefix = $names[$type] ?? $type;

        $text = match ($type) {
            'ul', 'ol' => count($state['items'] ?? []).' items',
            'table' => count($state['rows'] ?? []).' rows',
            default => \Illuminate\Support\Str::limit(
                trim(html_entity_decode(strip_tags((string) ($state['html'] ?? '')))),
                64,
            ),
        };

        return trim("{$prefix}  {$text}") ?: null;
    }

    /* ================================================================== *
     * Table
     * ================================================================== */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Post $record): ?string => $record->excerpt)
                    ->wrap(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tone')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Tone::LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Tone::color($state)),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('j M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('body')
                    ->label('Blocks')
                    ->state(fn (Post $record): string => (string) count($record->body ?? []))
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('read_time')
                    ->label('Read')
                    ->suffix(' min')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn (): array => Post::query()
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),

                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),

                // Without this a deleted issue is unrecoverable from the panel.
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('view_site')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Post $record): string => rtrim((string) config('sharkweb.frontend_url'), '/')."/newsletter/{$record->slug}/")
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['is_published' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Unpublish')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['is_published' => false]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No issues yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=posts` to load the five published articles.')
            ->emptyStateIcon('heroicon-o-newspaper');
    }

    public static function getEloquentQuery(): Builder
    {
        // Without removing the soft-delete scope, the Trash filter has nothing to reveal.
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->orderBy('sort')
            ->orderByDesc('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPost::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}

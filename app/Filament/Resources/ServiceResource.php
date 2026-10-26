<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\CaseStudy;
use App\Models\Content\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * A service page — /services/meta-ads and its twelve siblings.
 *
 * THE DEEPEST FORM IN THE PANEL, so it is laid out as tabs rather than one long scroll.
 * The owner asked for "exactly the same editable", which means all of: SEO fields,
 * breadcrumb, H1, lead, chips, the stat block's four numbers, three sections holding
 * nineteen items between them, the testimonial, five FAQs and the closing CTA. One
 * undivided form for that is a form nobody finishes, so the tabs are ordered by how
 * often each part is touched — Copy first, then the body, then the two endorse blocks.
 *
 * THE NESTED REPEATERS ARE THE POINT. `sections` holds, per section, a list of items
 * that are one of three shapes. Filament's `->itemLabel()` and a `kind` select drive
 * which fields each item shows, so an editor picks "row / step / case" and the form
 * reshapes itself rather than asking for fields the chosen shape will not render.
 *
 * THE ITEM SHAPES ARE NOT INTERCHANGEABLE — `Section.tsx` switches on `kind` and each
 * branch reads different keys. Getting `kind` wrong does not throw; the row silently
 * renders less. So the select is required and the help text names what each one is for.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Services';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Service pages';

    protected static ?string $modelLabel = 'service page';

    protected static ?string $recordTitleAttribute = 'name';

    /** The three item shapes `Section.tsx` knows how to render. */
    private const ITEM_KINDS = [
        'row' => 'Row — title + text, with a coloured left border',
        'step' => 'Step — numbered, with a ↳ mark on the right',
        'case' => 'Link — a linked row to another service',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Service page')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    self::copyTab(),
                    self::statTab(),
                    self::sectionsTab(),
                    self::endorseTab(),
                ]),
        ]);
    }

    /* ================================================================== *
     * Tab 1 — the header and the search result
     * ================================================================== */

    private static function copyTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Copy & SEO')
            ->icon('heroicon-o-document-text')
            ->schema([
                Forms\Components\Section::make('Search result')
                    ->description('What appears in Google and in a shared link. Independent of the page heading below — the title tag can afford to be longer.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('meta.title')
                            ->label('Meta title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Meta Ads — The Shark Web')
                            ->helperText('Around 60 characters before Google truncates it.')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('meta.description')
                            ->label('Meta description')
                            ->required()
                            ->rows(3)
                            ->maxLength(320)
                            ->helperText('Around 155 characters is what shows. Aim for a reason to click, not a summary.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('group_id')
                            ->label('Discipline')
                            ->relationship('group', 'name')
                            ->required()
                            ->native(false)
                            ->helperText('Which catalog group on /services lists this page.'),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(96)
                            ->unique(ignoreRecord: true)
                            ->helperText('The URL: /services/{slug}/. Changing it breaks the old link unless a redirect is added.')
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Used in the catalog list and as the default H1.'),

                        Forms\Components\TextInput::make('desc')
                            ->label('Catalog description')
                            ->maxLength(200)
                            ->placeholder('Paid social that scales profitably')
                            ->helperText('The one-line description in the catalog list on /services.'),

                        Forms\Components\Select::make('tone')
                            ->options(Tone::ACCENTS)
                            ->required()
                            ->default('accent')
                            ->native(false),

                        Forms\Components\TextInput::make('sort')
                            ->label('Order within the discipline')
                            ->numeric()
                            ->default(0),
                    ]),

                Forms\Components\Section::make('Page header')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('h1')
                            ->label('Page heading')
                            ->required()
                            ->maxLength(120)
                            ->helperText('The large heading at the top of the page.'),

                        Forms\Components\TextInput::make('crumb')
                            ->label('Breadcrumb')
                            ->maxLength(160)
                            ->placeholder('/ SERVICES / META ADS')
                            ->helperText('The first segment before the first " / " is the linked part.'),

                        Forms\Components\TextInput::make('crumb_href')
                            ->label('Breadcrumb link')
                            ->maxLength(128)
                            ->placeholder('/services/')
                            ->helperText('Leave empty to render the breadcrumb as plain text.'),

                        Forms\Components\Repeater::make('chips')
                            ->label('Chips')
                            ->simple(
                                Forms\Components\TextInput::make('chip')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(80),
                            )
                            ->addActionLabel('Add a chip')
                            ->reorderable()
                            ->columnSpanFull()
                            ->helperText('The outlined tags beside the lead. Two to four reads best.'),

                        Forms\Components\Textarea::make('lead')
                            ->label('Lead paragraph')
                            ->required()
                            ->rows(3)
                            ->maxLength(600)
                            ->helperText('Sits beside the heading. Set the terms before the page argues them.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /* ================================================================== *
     * Tab 2 — the dark stat block
     * ================================================================== */

    private static function statTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Stat block')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Forms\Components\Section::make('The dark band under the header')
                    ->description('A big figure on the left, the argument on the right. On every service page this is the first thing that earns attention.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('stat.label')
                            ->label('Label')
                            ->maxLength(80)
                            ->placeholder('# GROWTH & MEDIA'),

                        Forms\Components\Select::make('stat.tone')
                            ->label('Tone')
                            ->options(Tone::ACCENTS)
                            ->default('accent')
                            ->native(false),

                        Forms\Components\TextInput::make('stat.big')
                            ->label('Big figure')
                            ->maxLength(12)
                            ->placeholder('01')
                            ->helperText('Rendered at 200px. Keep it to two or three characters or it will overflow.')
                            ->extraInputAttributes(['style' => 'font-size: 1.5rem;']),

                        Forms\Components\TextInput::make('stat.small')
                            ->label('Figure suffix')
                            ->maxLength(24)
                            ->placeholder('discipline')
                            ->helperText('The smaller word beside the big figure.'),

                        Forms\Components\TextInput::make('stat.title')
                            ->label('Statement')
                            ->maxLength(200)
                            ->placeholder('Meta Ads that buy revenue, not impressions.')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('stat.text')
                            ->label('Supporting text')
                            ->rows(3)
                            ->maxLength(600)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('stat.cta.label')
                            ->label('Button label')
                            ->maxLength(64)
                            ->placeholder('BOOK A FREE GROWTH AUDIT'),

                        Forms\Components\TextInput::make('stat.cta.href')
                            ->label('Button link')
                            ->maxLength(255)
                            ->placeholder('/contact/#from=meta-ads'),

                        Forms\Components\Select::make('stat.cta.tone')
                            ->label('Button tone')
                            ->options(Tone::ACCENTS)
                            ->default('accent')
                            ->native(false),
                    ]),
            ]);
    }

    /* ================================================================== *
     * Tab 3 — the body
     * ================================================================== */

    private static function sectionsTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Sections')
            ->icon('heroicon-o-list-bullet')
            ->schema([
                Forms\Components\Repeater::make('sections')
                    ->label('Page sections')
                    ->addActionLabel('Add a section')
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null)
                        ? trim(($state['num'] ?? '').'  '.$state['title'])
                        : null)
                    ->schema([
                        Forms\Components\Section::make()
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('num')
                                    ->label('Section number')
                                    ->maxLength(12)
                                    ->placeholder('# 01')
                                    ->helperText('Display copy.'),

                                Forms\Components\TextInput::make('title')
                                    ->label('Heading')
                                    ->required()
                                    ->maxLength(120),

                                Forms\Components\Select::make('tone')
                                    ->options(Tone::ACCENTS)
                                    ->placeholder('None')
                                    ->native(false)
                                    ->helperText('Leave empty for a neutral heading.'),

                                Forms\Components\Textarea::make('text')
                                    ->label('Intro text')
                                    ->rows(3)
                                    ->maxLength(600)
                                    ->helperText('Optional. Sits under the heading.')
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('link.href')
                                    ->label('Heading link')
                                    ->maxLength(255)
                                    ->placeholder('/work/'),

                                Forms\Components\TextInput::make('link.label')
                                    ->label('Link label')
                                    ->maxLength(64)
                                    ->placeholder('See the work'),
                            ]),

                        Forms\Components\Repeater::make('items')
                            ->label('Items in this section')
                            ->addActionLabel('Add an item')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null)
                                ? $state['title']
                                : null)
                            ->schema([
                                Forms\Components\Select::make('kind')
                                    ->label('Item type')
                                    ->options(self::ITEM_KINDS)
                                    ->required()
                                    ->default('row')
                                    ->native(false)
                                    ->live()
                                    ->helperText('This decides which of the fields below actually appear on the page.'),

                                Forms\Components\TextInput::make('title')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(160),

                                Forms\Components\TextInput::make('text')
                                    ->label('Text')
                                    ->maxLength(400)
                                    ->required()
                                    ->helperText(fn (Forms\Get $get): string => $get('kind') === 'case'
                                        ? 'For a link, this is the description under the service name.'
                                        : 'The supporting line.'),

                                /*
                                 * The three kind-specific fields are shown one at a time.
                                 * Each is also `dehydrated()`, so the value survives a save
                                 * even while hidden — otherwise switching a row from `step`
                                 * to `row` and back would silently drop the number and mark.
                                 */
                                Forms\Components\TextInput::make('num')
                                    ->label('Step number')
                                    ->maxLength(8)
                                    ->placeholder('01')
                                    ->visible(fn (Forms\Get $get): bool => $get('kind') === 'step')
                                    ->required(fn (Forms\Get $get): bool => $get('kind') === 'step'),

                                Forms\Components\TextInput::make('mark')
                                    ->label('Step mark')
                                    ->maxLength(40)
                                    ->placeholder('↳ OFFER')
                                    ->visible(fn (Forms\Get $get): bool => $get('kind') === 'step')
                                    ->helperText('The small right-aligned label. The ↳ is part of the design, so include it.'),

                                Forms\Components\TextInput::make('href')
                                    ->label('Link')
                                    ->maxLength(255)
                                    ->placeholder('/services/social-media/')
                                    ->visible(fn (Forms\Get $get): bool => $get('kind') === 'case')
                                    ->required(fn (Forms\Get $get): bool => $get('kind') === 'case'),

                                Forms\Components\Select::make('tone')
                                    ->options(Tone::ACCENTS)
                                    ->placeholder('None')
                                    ->native(false)
                                    ->visible(fn (Forms\Get $get): bool => in_array($get('kind'), ['row', 'step'], true))
                                    ->helperText('Colours the item\'s left border and, for a step, its number and mark.'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('sections_hint')
                    ->hiddenLabel()
                    ->content('Most service pages run three sections: "What you get" (rows), "How it runs" (steps) and "Works well with" (links). The order here is the order on the page.'),
            ]);
    }

    /* ================================================================== *
     * Tab 4 — testimonial, article, FAQ, closing CTA
     * ================================================================== */

    private static function endorseTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Proof & closing')
            ->icon('heroicon-o-check-badge')
            ->schema([
                Forms\Components\Section::make('Testimonial')
                    ->description('The band that quotes a case study. The quote, the numbers and the card are pulled from the chosen case, so editing the case updates this band everywhere it appears.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('review_case')
                            ->label('Case study')
                            ->options(fn (): array => CaseStudy::query()
                                ->orderBy('sort')
                                ->pluck('client', 'slug')
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->placeholder('Use the first case that has a quote')
                            ->helperText('Only cases with a quote render a testimonial. The rest fall back to the first one that has one.'),

                        Forms\Components\TextInput::make('review_label')
                            ->label('Band label')
                            ->maxLength(96)
                            ->placeholder('# 04 — TESTIMONIAL'),
                    ]),

                Forms\Components\Section::make('Promo block')
                    ->description('Optional. An image-and-text row promoting an article. Leave the heading empty to hide it from the page.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('article.label')
                            ->label('Label')
                            ->maxLength(96)
                            ->placeholder('# FROM THE FIELD NOTES'),

                        Forms\Components\TextInput::make('article.tone')
                            ->label('Tone')
                            ->maxLength(16)
                            ->placeholder('accent')
                            ->helperText('accent · green · orange · magenta'),

                        Forms\Components\TextInput::make('article.title')
                            ->label('Heading')
                            ->maxLength(200)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('article.text')
                            ->label('Text')
                            ->rows(3)
                            ->maxLength(600)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('article.cta')
                            ->label('Button label')
                            ->maxLength(64),

                        Forms\Components\TextInput::make('article.href')
                            ->label('Link')
                            ->maxLength(255),

                        /*
                         * The upload writes to the `article` media collection the model
                         * registers. Spatie's field rather than a plain FileUpload: the
                         * collection is what `Service::toFrontendArray()` reads, so a
                         * plain upload would put a file on disk that nothing looks for.
                         */
                        Forms\Components\SpatieMediaLibraryFileUpload::make('article_image')
                            ->label('Image')
                            ->collection('article')
                            ->image()
                            ->imageEditor()
                            ->helperText('Optional. Once uploaded this takes over from the path below.')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('article.img')
                            ->label('Image path')
                            ->maxLength(255)
                            ->placeholder('/img/articles/example.jpg')
                            ->helperText('Used when nothing has been uploaded above.')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('article.alt')
                            ->label('Image alt text')
                            ->maxLength(200)
                            ->helperText('Describe the image for someone who cannot see it.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Questions')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('faq.label')
                            ->label('Label')
                            ->maxLength(24)
                            ->placeholder('# 05'),

                        Forms\Components\TextInput::make('faq.title')
                            ->label('Heading')
                            ->maxLength(160)
                            ->placeholder('Meta Ads, answered'),

                        Forms\Components\TextInput::make('faq.tone')
                            ->label('Tone')
                            ->maxLength(16)
                            ->placeholder('accent'),

                        Forms\Components\Repeater::make('faq.items')
                            ->label('Questions')
                            ->addActionLabel('Add a question')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                            ->schema([
                                Forms\Components\TextInput::make('num')
                                    ->label('Number')
                                    ->maxLength(8)
                                    ->placeholder('Q.01'),

                                Forms\Components\TextInput::make('q')
                                    ->label('Question')
                                    ->required()
                                    ->maxLength(240)
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('a')
                                    ->label('Answer')
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(1200)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('id')
                                    ->label('Anchor')
                                    ->maxLength(96)
                                    ->helperText('The #fragment this question is linkable by. Generated from the question if left empty.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('faq.note.pre')
                            ->label('Note — before the link')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('faq.note.label')
                            ->label('Note — link label')
                            ->maxLength(64),

                        Forms\Components\TextInput::make('faq.note.href')
                            ->label('Note — link')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('faq.note.post')
                            ->label('Note — after the link')
                            ->maxLength(64),
                    ]),

                Forms\Components\Section::make('Closing call to action')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('final.label')
                            ->label('Label')
                            ->maxLength(96)
                            ->placeholder('# 06 — META ADS'),

                        Forms\Components\Select::make('final.tone')
                            ->label('Tone')
                            ->options(Tone::ACCENTS)
                            ->default('accent')
                            ->native(false),

                        Forms\Components\Textarea::make('final.title')
                            ->label('Heading')
                            ->rows(2)
                            ->maxLength(200)
                            ->placeholder("Meta Ads.\nStart with a free audit.")
                            ->helperText('A line break splits the heading into two lines, as the design does.')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('final.text')
                            ->label('Supporting text')
                            ->rows(3)
                            ->maxLength(600)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('final.cta')
                            ->label('Button label')
                            ->maxLength(64),

                        Forms\Components\TextInput::make('final.href')
                            ->label('Button link')
                            ->maxLength(255),

                        Forms\Components\Repeater::make('final.links')
                            ->label('Secondary links')
                            // `->simple()` takes a single field; two columns need `->schema()`.
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('Label')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('href')
                                        ->label('Link')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                            ])
                            ->addActionLabel('Add a link')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->columnSpanFull()
                            ->helperText('The small underlined links under the button — "See the work", "book a 30-min call".'),
                    ]),
            ]);
    }

    /* ================================================================== *
     * Table
     * ================================================================== */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Discipline')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Service $record): ?string => $record->desc),

                Tables\Columns\TextColumn::make('slug')
                    ->label('URL')
                    ->prefix('/services/')
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tone')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Tone::LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => Tone::color($state)),

                Tables\Columns\TextColumn::make('sections')
                    ->label('Sections')
                    ->state(fn (Service $record): string => (string) count($record->sections ?? []))
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('faq')
                    ->label('FAQs')
                    ->state(fn (Service $record): string => (string) count($record->faq['items'] ?? []))
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort')
            ->groups([
                Tables\Grouping\Group::make('group.name')
                    ->label('Discipline')
                    ->collapsible(),
            ])
            ->defaultGroup('group.name')
            ->actions([
                Tables\Actions\EditAction::make(),

                // The site's own page, opened in a new tab — checking copy in place is
                // the most common reason to leave this screen.
                Tables\Actions\Action::make('view_site')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Service $record): string => rtrim((string) config('sharkweb.frontend_url'), '/')."/services/{$record->slug}/")
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make()
                    ->modalDescription('The catalog entry and this page both go. Its URL will start returning a 404.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Show on the site')
                        ->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Hide from the site')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No service pages yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=services` to load the thirteen published pages.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }

    /**
     * Generate a `faq.items.*.id` anchor from its question when the editor leaves it blank.
     *
     * Without this a question is unlinkable, and the FAQ block on the page renders an
     * `<a href="#{id}">` with an empty fragment — a link that goes nowhere.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillMissingAnchors(array $data): array
    {
        if (! isset($data['faq']['items']) || ! is_array($data['faq']['items'])) {
            return $data;
        }

        foreach ($data['faq']['items'] as $i => $item) {
            if (blank($item['id'] ?? null) && filled($item['q'] ?? null)) {
                $data['faq']['items'][$i]['id'] = Str::slug($item['q']);
            }
        }

        return $data;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('group')
            ->orderBy('sort')
            ->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListService::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CaseStudyResource\Pages;
use App\Filament\Support\Tone;
use App\Models\Content\CaseStudy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * The documented engagements — the five case pages, the /work board, and the review
 * cards that services and the newsletter draw from.
 *
 * WHY THIS IS ONE SCREEN AND NOT THREE. A case study appears in four places: its own
 * page, the /work board card, the "check the evidence" list on every service page, and
 * the testimonial band on the services index. All four are built from the same row here
 * — none of them has its own copy. That is deliberate: it means a corrected metric is
 * corrected everywhere, and it means this is the only screen an editor needs.
 *
 * WHAT IS DERIVED, AND WHY THERE IS NO FIELD FOR IT:
 *  - `initials`      — from the client name, when left blank.
 *  - the work card's `result` / `noteText` / `stack` — restatements of fields already here.
 *  - the filter chip the case files under on /work — from its services list.
 *  - the prev/next footer and the number badge — from `sort` and `num`.
 * Deriving rather than storing is what stops the board card and the case page from
 * disagreeing after an edit, which is the failure this whole rework is meant to prevent.
 *
 * THE QUOTE IS OPTIONAL AND HAS TO BE ABLE TO BE ABSENT. `quote` is a nullable column and
 * the frontend switches on `null` (the card renders a "NDA" note instead of a testimonial),
 * so storing `{text: "", name: "", role: ""}` would render an empty quotation rather than
 * no quotation. `normaliseQuote()` collapses a blank quote back to null on save.
 */
class CaseStudyResource extends Resource
{
    protected static ?string $model = CaseStudy::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Case studies';

    protected static ?string $modelLabel = 'case study';

    protected static ?string $recordTitleAttribute = 'client';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Case study')
                ->columnSpanFull()
                ->tabs([
                    /* ============================================================
                     | Identity
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('Identity')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Forms\Components\Section::make('Who it is for')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('client')
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder('Aryia Leather Co.')
                                        ->live(onBlur: true)
                                        ->helperText('Appears as the card title and the case page `<h1>`. '),

                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(120)
                                        ->unique(ignoreRecord: true)
                                        ->live(onBlur: true)
                                        ->rules(['alpha_dash'])
                                        ->placeholder('aryia-leather')
                                        ->helperText('The URL segment — /work/aryia-leather/. Changing it retires the old link.')
                                        ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                                            // Only help while the field is untouched. An editor who
                                            // has typed a slug of their own should keep it.
                                            if (filled($state)) {
                                                $set('slug', Str::slug($state));
                                            }
                                        }),

                                    Forms\Components\TextInput::make('vertical')
                                        ->required()
                                        ->maxLength(64)
                                        ->placeholder('eCommerce')
                                        ->helperText('The sector. Rendered uppercase on the board card.'),

                                    Forms\Components\TextInput::make('market')
                                        ->maxLength(64)
                                        ->placeholder('Australia'),

                                    Forms\Components\TextInput::make('num')
                                        ->label('Case number')
                                        ->maxLength(16)
                                        ->placeholder('001')
                                        ->helperText('The monospace index on the card. Leave blank and it is numbered by order.'),

                                    Forms\Components\TextInput::make('initials')
                                        ->maxLength(8)
                                        ->placeholder('AL')
                                        ->helperText('Leave blank and it is derived from the client name.'),
                                ]),

                            Forms\Components\Section::make('Dates')
                                ->columns(3)
                                ->schema([
                                    Forms\Components\TextInput::make('year_start')
                                        ->label('Start year')
                                        ->maxLength(8)
                                        ->placeholder('2024'),

                                    Forms\Components\TextInput::make('year_end')
                                        ->label('End year')
                                        ->maxLength(8)
                                        ->placeholder('2025'),

                                    Forms\Components\TextInput::make('year_label')
                                        ->label('Displayed year')
                                        ->maxLength(32)
                                        ->placeholder('2024 — 2025')
                                        ->helperText('What the card actually shows. Use "ONGOING" for retained work.'),
                                ]),

                            Forms\Components\Section::make('Engagement')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Repeater::make('services')
                                        ->label('Worked on')
                                        ->simple(
                                            Forms\Components\TextInput::make('service')
                                                ->hiddenLabel()
                                                ->required()
                                                ->maxLength(64)
                                                ->placeholder('Meta Ads'),
                                        )
                                        ->addActionLabel('Add a discipline')
                                        ->reorderable()
                                        ->columnSpanFull()
                                        ->helperText('Drives the "STACK" line on the card AND which filter chip the case files under on /work. Naming a service "Automation" or "Funnel" files it under that chip.'),
                                ]),
                        ]),

                    /* ============================================================
                     | The numbers
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('The numbers')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Forms\Components\Section::make('Headline result')
                                ->description('The one number that carries the case. Rendered at display size on the card, the case hero and the prev/next footer.')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('headline.value')
                                        ->label('Number')
                                        ->required()
                                        ->maxLength(32)
                                        ->placeholder('+186%')
                                        ->helperText('Keep it short — it is set at 40px+.'),

                                    Forms\Components\TextInput::make('headline.label')
                                        ->label('What it measures')
                                        ->maxLength(120)
                                        ->placeholder('Revenue growth in 5 months'),
                                ]),

                            Forms\Components\Section::make('Supporting metrics')
                                ->description('The band under the headline. Two to four reads best; the layout is a row.')
                                ->schema([
                                    Forms\Components\Repeater::make('metrics')
                                        ->hiddenLabel()
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('value')
                                                ->label('Number')
                                                ->required()
                                                ->maxLength(32)
                                                ->placeholder('3x'),

                                            Forms\Components\TextInput::make('label')
                                                ->label('What it measures')
                                                ->required()
                                                ->maxLength(120)
                                                ->placeholder('Blended ROAS'),
                                        ])
                                        ->addActionLabel('Add a metric')
                                        ->reorderable()
                                        ->defaultItems(3)
                                        ->itemLabel(fn (array $state): ?string => $state['value'] ?? null),
                                ]),
                        ]),

                    /* ============================================================
                     | Narrative
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('Narrative')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Section::make('Summary')
                                ->schema([
                                    Forms\Components\Textarea::make('summary')
                                        ->label('What we did')
                                        ->required()
                                        ->rows(4)
                                        ->maxLength(600)
                                        ->helperText('Two or three sentences. This is the case page lead and the card tooltip.'),

                                    Forms\Components\TextInput::make('note')
                                        ->label('Footnotes / caveat')
                                        ->maxLength(160)
                                        ->placeholder("Results measured on the client's own ad accounts.")
                                        ->helperText('The small print under the metrics. Leave blank if there is none.'),
                                ]),

                            Forms\Components\Section::make('Phases')
                                ->description('The "leak → rebuild → result" breakdown. A case with no phases renders the shorter documented-result layout instead of being blocked.')
                                ->schema([
                                    Forms\Components\Repeater::make('phases')
                                        ->hiddenLabel()
                                        ->schema([
                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    Forms\Components\TextInput::make('num')
                                                        ->label('Step number')
                                                        ->maxLength(8)
                                                        ->placeholder('01')
                                                        ->helperText('Leave blank and it is numbered by position.'),

                                                    Forms\Components\TextInput::make('kicker')
                                                        ->label('Kicker')
                                                        ->maxLength(48)
                                                        ->placeholder('THE LEAK')
                                                        ->helperText('The small monospace label above the title.'),
                                                ]),

                                            Forms\Components\TextInput::make('title')
                                                ->required()
                                                ->maxLength(160)
                                                ->placeholder('Rising CPA & fragmented creative testing'),

                                            Forms\Components\Textarea::make('text')
                                                ->label('What happened')
                                                ->required()
                                                ->rows(3)
                                                ->maxLength(600),

                                            Forms\Components\Repeater::make('tags')
                                                ->label('Takeaways')
                                                ->simple(
                                                    Forms\Components\TextInput::make('tag')
                                                        ->hiddenLabel()
                                                        ->required()
                                                        ->maxLength(64)
                                                        ->placeholder('High ad fatigue'),
                                                )
                                                ->addActionLabel('Add a takeaway')
                                                ->reorderable()
                                                ->helperText('Rendered as pill tags beneath the phase.'),
                                        ])
                                        ->addActionLabel('Add a phase')
                                        ->reorderable()
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Untitled phase')
                                        ->defaultItems(0),
                                ]),
                        ]),

                    /* ============================================================
                     | Proof & presentation
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('Proof & tone')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->schema([
                            Forms\Components\Section::make('Client quote')
                                ->description('Shown on the case page and reused as a review card on the services index and in the newsletter. Leave every field blank for no quote — the card then shows an "under NDA" note instead.')
                                ->columns(1)
                                ->schema([
                                    Forms\Components\Textarea::make('quote.text')
                                        ->label('Quote')
                                        ->rows(2)
                                        ->maxLength(400)
                                        ->placeholder('~4× ROAS in 90 days, measured on the client\'s own ad accounts.'),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('quote.name')
                                                ->label('Name')
                                                ->maxLength(120)
                                                ->placeholder('James Mitchell'),

                                            Forms\Components\TextInput::make('quote.role')
                                                ->label('Role & company')
                                                ->maxLength(160)
                                                ->placeholder('Founder, Aryia Leather Co.'),
                                        ]),
                                ]),

                            Forms\Components\Section::make('Review band copy')
                                ->description('The two paragraphs that sit under the quote when this case is used as a testimonial on a service page. Leave blank and the case summary is used instead.')
                                ->schema([
                                    Forms\Components\TextInput::make('review_title')
                                        ->label('Review headline')
                                        ->maxLength(200)
                                        ->placeholder('Meta & TikTok growth for a premium leather brand')
                                        ->helperText('One line. Set at 24px in the band.'),

                                    Forms\Components\Textarea::make('review_body')
                                        ->label('Review paragraph')
                                        ->rows(3)
                                        ->maxLength(700)
                                        ->placeholder('Revenue grew 186% in five months while cost per acquisition fell 41%…')
                                        ->helperText('Three or four sentences. This is the body text of the testimonial, not the case summary.'),
                                ]),

                            Forms\Components\Section::make('Presentation')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Select::make('tone')
                                        ->options(Tone::ACCENTS)
                                        ->default('accent')
                                        ->required()
                                        ->native(false)
                                        ->helperText('Colours the case number, the result figure and the accent rule.'),

                                    Forms\Components\Select::make('bg')
                                        ->label('Card background')
                                        ->options(Tone::BACKGROUNDS)
                                        ->default('paper')
                                        ->native(false)
                                        ->helperText('The board card and case header tint.'),
                                ]),

                            Forms\Components\Section::make('Placement')
                                ->columns(3)
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Show on the site')
                                        ->default(true)
                                        ->helperText('Off hides the case page and its card everywhere.'),

                                    Forms\Components\TextInput::make('sort')
                                        ->label('Order')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Lowest first. Also sets the "001" numbering and prev/next.'),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('num')
                    ->label('#')
                    ->badge()
                    ->color(fn (CaseStudy $record): string => Tone::color($record->tone))
                    ->sortable(),

                Tables\Columns\TextColumn::make('client')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (CaseStudy $record): ?string => trim("{$record->vertical} · {$record->market}", ' ·')),

                Tables\Columns\TextColumn::make('headline.value')
                    ->label('Headline')
                    ->weight('medium')
                    ->description(fn (CaseStudy $record): ?string => $record->headline['label'] ?? null)
                    ->color(fn (CaseStudy $record): string => Tone::color($record->tone)),

                Tables\Columns\TextColumn::make('services')
                    ->label('Stack')
                    ->state(fn (CaseStudy $record): string => implode(' · ', $record->services ?? []))
                    ->limit(42)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phases')
                    ->label('Phases')
                    ->state(fn (CaseStudy $record): string => (string) count($record->phases ?? []))
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('quote')
                    ->label('Quote')
                    ->state(fn (CaseStudy $record): bool => filled($record->quote['text'] ?? null))
                    ->boolean()
                    ->trueIcon('heroicon-o-chat-bubble-left-right')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('year_label')
                    ->label('Year')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                Tables\Filters\SelectFilter::make('vertical')
                    ->options(fn (): array => CaseStudy::query()
                        ->distinct()
                        ->orderBy('vertical')
                        ->pluck('vertical', 'vertical')
                        ->all()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Live'),

                // Without this a deleted case is unrecoverable from the panel.
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('view_site')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (CaseStudy $record): string => rtrim((string) config('sharkweb.frontend_url'), '/')."/work/{$record->slug}/")
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Show on the site')
                        ->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Hide from the site')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No case studies yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=cases` to load the five published engagements.')
            ->emptyStateIcon('heroicon-o-trophy');
    }

    /**
     * Turn the repeater output into what the model and frontend expect.
     *
     * Three jobs, none of which the form can do on its own:
     *  - collapse a blank quote to `null`, because the frontend treats "no quote" and
     *    "empty quote" differently;
     *  - number the phases that were left blank, so the "01"/"02" rail is never empty;
     *  - drop the `{service: "…"}` wrapper the simple repeater adds around each stack item.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normaliseCase(array $data): array
    {
        // Stack: simple repeater => flat list of strings.
        $data['services'] = array_values(array_filter(array_map(
            static fn ($item): string => trim(is_array($item) ? (string) ($item['service'] ?? '') : (string) $item),
            $data['services'] ?? [],
        ), static fn (string $v): bool => $v !== ''));

        // Phase numbers, when the editor did not supply them.
        $n = 1;

        foreach ($data['phases'] ?? [] as $i => $phase) {
            if (blank($phase['num'] ?? null)) {
                $data['phases'][$i]['num'] = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            }

            $n++;

            // Takeaway tags: same simple-repeater unwrapping as the stack.
            $data['phases'][$i]['tags'] = array_values(array_filter(array_map(
                static fn ($item): string => trim(is_array($item) ? (string) ($item['tag'] ?? '') : (string) $item),
                $phase['tags'] ?? [],
            ), static fn (string $v): bool => $v !== ''));
        }

        // An all-blank quote is the absence of a quote, not a quote with empty text.
        $quote = $data['quote'] ?? null;

        if (! is_array($quote) || blank($quote['text'] ?? null)) {
            $data['quote'] = null;
        }

        // Metrics with no number are noise in a three-column band.
        $data['metrics'] = array_values(array_filter(
            $data['metrics'] ?? [],
            static fn (array $m): bool => filled($m['value'] ?? null),
        ));

        return $data;
    }

    public static function getEloquentQuery(): Builder
    {
        // Without removing the soft-delete scope, the Trash filter has nothing to reveal.
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->orderBy('sort')
            ->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaseStudy::route('/'),
            'create' => Pages\CreateCaseStudy::route('/create'),
            'edit' => Pages\EditCaseStudy::route('/{record}/edit'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OssProjectResource\Pages;
use App\Models\Content\OssProject;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The open-source tiles on /open-source.
 *
 * THIS IS THE ONE RESOURCE WITH AN IMAGE UPLOAD, so it doubles as the pattern the
 * others follow if they ever gain one. Two things are worth knowing:
 *
 *  1. `img` (a path like `/img/projects/gitiempo/user-app-dashboard-desktop.png`) and an
 *     uploaded screenshot coexist. `OssProject::toFrontendArray()` prefers the upload and
 *     falls back to `img`. That is intentional: the three published tiles already point
 *     at bundled screenshots, and an editor who uploads a replacement should not have to
 *     first delete a path they cannot see.
 *
 *  2. `cell` — the grid's border classes — is NOT editable. It is a function of the
 *     tile's position, and `syncCell()` regenerates it from `sort` on every save. The
 *     field is displayed read-only so the value is not a mystery, but changing it would
 *     only be overwritten.
 *
 * `bar` and `label` are the tile's two accent classes (`bg-green`, `text-green`). They
 * are kept as literal strings rather than derived from a tone key because Tailwind scans
 * source files for complete class names, and the original markup spelled them out.
 */
class OssProjectResource extends Resource
{
    protected static ?string $model = OssProject::class;

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Open source';

    protected static ?string $modelLabel = 'project';

    protected static ?string $recordTitleAttribute = 'name';

    /** The accent pairs the design uses. Value is what the tile stores; label is what it looks like. */
    private const ACCENTS = [
        'bg-green|text-green' => 'Green',
        'bg-accent|text-accent' => 'Blue (accent)',
        'bg-orange|text-orange' => 'Orange',
        'bg-magenta|text-magenta' => 'Magenta',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Project')
                ->columnSpanFull()
                ->tabs([
                    /* ============================================================
                     | The tile
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('The tile')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            Forms\Components\Section::make('Naming')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder('GITiempo'),

                                    Forms\Components\TextInput::make('num')
                                        ->label('Index label')
                                        ->maxLength(16)
                                        ->placeholder('OSS.01')
                                        ->helperText('The monospace label in the top-left corner of the tile.'),

                                    Forms\Components\TextInput::make('tagline')
                                        ->required()
                                        ->maxLength(120)
                                        ->placeholder('TIME TRACKING, NATIVE TO GITHUB')
                                        ->helperText('Set uppercase on the site — type it however you like.')
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('year')
                                        ->required()
                                        ->maxLength(32)
                                        ->placeholder('2026  (active)')
                                        ->helperText('Free text, so "2026  (active)" is allowed.'),
                                ]),

                            Forms\Components\Section::make('Links & licence')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('href')
                                        ->label('Project URL')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('/gitiempo/')
                                        ->helperText('Internal path or full URL. The whole tile links here.'),

                                    Forms\Components\TextInput::make('license')
                                        ->maxLength(64)
                                        ->placeholder('MIT · open source'),

                                    Forms\Components\TextInput::make('stack')
                                        ->label('Built with')
                                        ->maxLength(160)
                                        ->placeholder('ASTRO · VUE · FIREBASE · GITHUB API')
                                        ->helperText('Separate entries with · — it is set in monospace and wraps badly otherwise.')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    /* ============================================================
                     | Image
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('Image')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\Section::make('Screenshot')
                                ->description('Shown at the top of the tile. Roughly 16:10 crops cleanest.')
                                ->schema([
                                    SpatieMediaLibraryFileUpload::make('screenshot')
                                        ->label('Upload a screenshot')
                                        ->collection('default')
                                        ->image()
                                        ->imageEditor()
                                        ->helperText('Takes priority over the bundled path below. Once uploaded, `OssProject::toFrontendArray()` serves this file and its WebP derivatives.')
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('img')
                                        ->label('Or use a bundled path')
                                        ->maxLength(255)
                                        ->placeholder('/img/projects/gitiempo/user-app-dashboard-desktop.png')
                                        ->helperText('Used when no file is uploaded. The three published tiles still point at these.'),

                                    Forms\Components\TextInput::make('alt')
                                        ->label('Alt text')
                                        ->maxLength(160)
                                        ->placeholder('GITiempo dashboard')
                                        ->helperText('Leave blank and the project name is used, which is usually right.'),
                                ]),
                        ]),

                    /* ============================================================
                     | Presentation
                     * ============================================================ */
                    Forms\Components\Tabs\Tab::make('Presentation')
                        ->icon('heroicon-o-swatch')
                        ->schema([
                            Forms\Components\Section::make('Accent')
                                ->description('The left bar and the small label share one colour.')
                                ->columns(1)
                                ->schema([
                                    Forms\Components\Radio::make('accent_pair')
                                        ->label('Colour')
                                        ->options(self::ACCENTS)
                                        ->inline()
                                        ->default('bg-green|text-green')
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function (Forms\Components\Radio $component, ?OssProject $record): void {
                                            if ($record) {
                                                $component->state("{$record->bar}|{$record->label}");
                                            }
                                        })
                                        ->helperText('Kept as literal Tailwind classes on the record, so the scanner picks them up.'),
                                ]),

                            Forms\Components\Section::make('Placement')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('sort')
                                        ->label('Order')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Lowest first. Also regenerates the grid borders — do not set them by hand.'),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Show on the site')
                                        ->default(true),

                                    Forms\Components\TextInput::make('cell')
                                        ->label('Grid position classes')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpanFull()
                                        ->helperText('Generated from the order. Shown for reference only.'),
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
                    ->label('Id')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (OssProject $record): ?string => $record->tagline),

                Tables\Columns\TextColumn::make('stack')
                    ->label('Built with')
                    ->limit(38)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('year')
                    ->label('Year')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('license')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Live'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (OssProject $record): string => $record->href)
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make(),
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
                ]),
            ])
            ->emptyStateHeading('No projects yet')
            ->emptyStateDescription('Run `php artisan sharkweb:import-content --only=oss` to load the three published tools.')
            ->emptyStateIcon('heroicon-o-code-bracket');
    }

    /**
     * Turn the accent radio back into the two stored class strings.
     *
     * The form shows one `<Radio>` ("Green") because the tile stores two classes
     * (`bg-green` and `text-green`) and asking an editor to keep two fields in sync is a
     * bug waiting to happen. The pair is joined with `|`, which is not a Tailwind class,
     * so it can never collide with a real value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normaliseOss(array $data): array
    {
        $pair = (string) ($data['accent_pair'] ?? '');

        if (str_contains($pair, '|')) {
            [$data['bar'], $data['label']] = explode('|', $pair, 2);
        }

        unset($data['accent_pair']);

        // The screenshot field targets the Spatie `default` collection and never reaches
        // the column list. Dropping it here keeps the model fill from choking on it if
        // Spatie's own sync does not run first.
        unset($data['screenshot']);

        if (blank($data['alt'] ?? null)) {
            $data['alt'] = $data['name'] ?? '';
        }

        return $data;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort')->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOssProject::route('/'),
            'create' => Pages\CreateOssProject::route('/create'),
            'edit' => Pages\EditOssProject::route('/{record}/edit'),
        ];
    }
}

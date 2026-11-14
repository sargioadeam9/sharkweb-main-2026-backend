<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

/**
 * Global site settings (§Module D).
 *
 * Binds directly to the `GeneralSettings` Spatie settings class rather than the
 * spatie-laravel-settings Filament plugin, which is not installed — a plain page with a
 * form is less machinery for the same result, and keeps one less package on shared hosting.
 *
 * Contact details and the availability badge live here rather than in .env because the
 * owner must be able to change a phone number without a deploy.
 */
class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $values = app(GeneralSettings::class)->toArray();

        /*
         * THE CREDENTIALS ARE WRITE-ONLY.
         *
         * Filling them in would print a live token into this page's HTML on every visit. A
         * secret that appears in page source is a secret that ends up in a screenshot, a
         * support ticket, a browser extension's clipboard, or an XSS payload — and the field
         * being `password` type does not help, because the value is still in the markup.
         *
         * Left blank, `dehydrated(filled)` keeps them out of the submitted state, so saving
         * the form does not wipe them. Removing one is the explicit toggle beneath each field.
         */
        unset(
            $values['meta_capi_access_token'],
            $values['tiktok_access_token'],
            $values['resend_api_key'],
            $values['brevo_api_key'],
        );

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Identity')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\TextInput::make('site_name')
                                    ->required()
                                    ->maxLength(120)
                                    ->helperText('The brand name. Used as the Open Graph site name and as the fallback title.')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Forms\Components\TextInput::make('contact_email')
                                    ->email()
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('contact_phone')
                                    ->tel()
                                    ->maxLength(30),
                                Forms\Components\TextInput::make('whatsapp_number')
                                    ->label('WhatsApp number')
                                    ->maxLength(30)
                                    ->helperText('Digits only, including country code — e.g. 8801896444400. Used to build the wa.me link.'),
                                Forms\Components\TextInput::make('contact_location')
                                    ->maxLength(150)
                                    ->placeholder('Serving Clients Worldwide · Remote & On-demand'),

                                Forms\Components\TextInput::make('booking_url')
                                    ->label('Booking page')
                                    ->url()
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('https://cal.com/thesharkweb/30min')
                                    ->helperText('Where "Approve & invite" sends a qualified lead. The lead\'s name and email are appended so the booking arrives identified.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        /*
                         * The founder card's own channels.
                         *
                         * WHY THIS IS NOT THE CONTACT TAB. `contact_email` is the studio inbox;
                         * these reach the person. They were hardcoded in `FounderCard.tsx` and
                         * had already drifted to a different domain, so they belong somewhere
                         * the owner can actually correct.
                         *
                         * THE UPLOAD IS THE POINT. A portrait cannot live in a `.ts` file, and
                         * the previous image was a bundled asset that needed a rebuild to
                         * change. Anything uploaded lands on the `public` disk under
                         * `founder/`, which `founderAvatarUrl()` resolves to `/storage/...`.
                         */
                        Forms\Components\Tabs\Tab::make('Founder')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Forms\Components\Section::make('Direct channels')
                                    ->description('Shown in the founder card on /about. These are the founder\'s own, not the studio inbox on the Contact tab.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('founder_email')
                                            ->label('Email')
                                            ->email()
                                            ->required()
                                            ->maxLength(150),

                                        Forms\Components\TextInput::make('founder_phone')
                                            ->label('Phone')
                                            ->tel()
                                            ->required()
                                            ->maxLength(30)
                                            ->helperText('Stored as typed. The tel: link strips the spaces, so "+880 1896 444400" is fine.'),

                                        Forms\Components\TextInput::make('founder_linkedin_url')
                                            ->label('LinkedIn profile')
                                            ->url()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Portrait')
                                    ->description('The image in the left card on /about. Portrait crop, face at the top of the frame.')
                                    ->schema([
                                        Forms\Components\FileUpload::make('founder_avatar_path')
                                            ->label('Photo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('founder')
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->maxSize(4096)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->helperText('Uploading replaces the bundled portrait. Leave it empty and the current image stays.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Support widget')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Forms\Components\Section::make('The floating widget')
                                    ->description('The contact button in the bottom-right of every page. Its email and WhatsApp links come from the Contact tab — they are not repeated here.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('support_widget_enabled')
                                            ->label('Show the widget')
                                            ->helperText('Off removes it from the whole site.'),

                                        Forms\Components\Toggle::make('support_verified')
                                            ->label('Show the blue tick')
                                            ->helperText('The verified badge beside the name.'),

                                        Forms\Components\TextInput::make('support_status_label')
                                            ->label('Status label')
                                            ->required()
                                            ->maxLength(30)
                                            ->placeholder('Online')
                                            ->helperText('Sits beside the green dot. Worth keeping honest — a permanent "Online" that never answers is worse than no badge.'),

                                        Forms\Components\TextInput::make('support_offer')
                                            ->label('Offer')
                                            ->required()
                                            ->maxLength(80)
                                            ->placeholder('1:1 Free Consultancy'),
                                    ]),

                                Forms\Components\Section::make('Who is answering')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('support_name')
                                            ->label('Name')
                                            ->required()
                                            ->maxLength(80),

                                        Forms\Components\TextInput::make('support_role')
                                            ->label('Role')
                                            ->required()
                                            ->maxLength(80)
                                            ->helperText('The widget\'s own title. The rest of the site uses the documented founder role, so changing it here does not change /about or the article bylines.'),

                                        Forms\Components\Textarea::make('support_intro')
                                            ->label('Opening line')
                                            ->required()
                                            ->rows(2)
                                            ->maxLength(180)
                                            ->helperText('The photo is not set here: the widget uses the Founder tab\'s portrait, so the same face appears on /about and in the widget.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Social')
                            ->icon('heroicon-o-share')
                            ->schema([
                                // Tab has no ->description() in Filament v3, so the
                                // explanation lives in a Section inside the tab.
                                Forms\Components\Section::make('Social profiles')
                                    ->description('Empty links are omitted from the footer and from the Organization JSON-LD sameAs array.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('twitter_url')->label('X / Twitter')->url()->maxLength(255),
                                        Forms\Components\TextInput::make('linkedin_url')->label('LinkedIn')->url()->maxLength(255),
                                        Forms\Components\TextInput::make('instagram_url')->label('Instagram')->url()->maxLength(255),
                                        Forms\Components\TextInput::make('facebook_url')->label('Facebook')->url()->maxLength(255),
                                        Forms\Components\TextInput::make('github_url')->label('GitHub')->url()->maxLength(255),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('SEO defaults')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Forms\Components\TextInput::make('default_meta_title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('default_meta_description')
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(320)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('default_og_image')
                                    ->label('Default social image path')
                                    ->maxLength(255)
                                    ->placeholder('/media/og-default.webp')
                                    ->helperText('Used when a page has no image of its own. Should be 1200×630.'),
                            ]),

                        /*
                         * Analytics and tracking.
                         *
                         * THE TAB WAS MISSING while the settings existed and the API served them,
                         * so the ids could be read by the frontend but never typed in. Everything
                         * here is a value the owner changes without a deploy, which is the whole
                         * reason it is not in .env.
                         *
                         * TIKTOK IS DELIBERATELY ABSENT. The settings exist, but nothing in the
                         * frontend or the backend reads them yet, and a field that accepts an id
                         * and does nothing is worse than no field at all. It comes back the day
                         * the TikTok pixel and its Events API are actually wired.
                         */
                        Forms\Components\Tabs\Tab::make('Analytics')
                            ->icon('heroicon-o-signal')
                            ->schema([
                                Forms\Components\Section::make('Google')
                                    ->description('Leave both blank until a property exists. Nothing loads while they are empty.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('gtm_container_id')
                                            ->label('Tag Manager container')
                                            ->maxLength(24)
                                            ->placeholder('GTM-XXXXXXX')
                                            ->helperText('Takes precedence: when a container is set, the measurement id below is ignored, because a container normally configures GA4 inside itself and loading both counts every pageview twice.'),

                                        Forms\Components\TextInput::make('ga4_measurement_id')
                                            ->label('GA4 measurement id')
                                            ->maxLength(24)
                                            ->placeholder('G-XXXXXXXXXX')
                                            ->helperText('Used only when no container is set.'),
                                    ]),

                                Forms\Components\Section::make('Meta')
                                    ->description('The pixel runs in the browser; the Conversions API reports the same lead from the server.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('meta_pixel_id')
                                            ->label('Pixel id')
                                            ->maxLength(20)
                                            ->placeholder('1234567890123456')
                                            ->rules(['nullable', 'regex:/^\d{6,20}$/'])
                                            ->validationMessages(['regex' => 'A pixel id is 6 to 20 digits, nothing else.'])
                                            ->helperText('Digits only, from Events Manager → Data sources.'),

                                        Forms\Components\TextInput::make('meta_capi_test_event_code')
                                            ->label('Test event code')
                                            ->maxLength(32)
                                            ->placeholder('TEST12345')
                                            ->helperText('Sends events to the Test Events tool instead of the live dataset. MUST be empty in normal operation — leaving one here means real conversions stop counting while the dashboard still looks healthy.'),

                                        Forms\Components\Toggle::make('meta_capi_enabled')
                                            ->label('Send server-side events')
                                            ->helperText('Needs both the pixel id and the token. Leave off until Test Events shows the events arriving.'),

                                        Forms\Components\Toggle::make('meta_capi_clear_token')
                                            ->label('Remove the saved token')
                                            ->visible(fn (): bool => $this->hasToken('meta_capi_access_token'))
                                            ->helperText('Switching this on deletes the stored credential when the page is saved.'),

                                        Forms\Components\TextInput::make('meta_capi_access_token')
                                            ->label('Conversions API token')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(500)
                                            ->columnSpanFull()
                                            // Blank must mean "keep what is saved". Without this the
                                            // form submits an empty value and wipes the credential
                                            // every time anyone saves this page.
                                            ->dehydrated(fn (?string $state): bool => filled($state))
                                            ->placeholder($this->hasToken('meta_capi_access_token') ? 'Saved — leave blank to keep it' : 'Not set')
                                            ->helperText('From Events Manager → Settings → Conversions API → Generate access token. Stored encrypted and never shown again.'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Footer')
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                Forms\Components\Textarea::make('footer_blurb')
                                    ->rows(3)
                                    ->maxLength(300)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('copyright_text')
                                    ->required()
                                    ->maxLength(150)
                                    ->columnSpanFull()
                                    ->helperText('Use © and the year will be kept current by the frontend.'),
                            ]),

                        /*
                         * The nightly KPI email.
                         *
                         * THE TAB WAS DESCRIBED HERE AND NEVER BUILT: the settings existed,
                         * `config/sharkweb.php` had the block, and there was no command, no
                         * schedule entry and no way to type the address. A comment describing
                         * a screen is not a screen.
                         *
                         * The switch and the address live here rather than in .env because both
                         * are things the owner changes on a Tuesday afternoon. The test button
                         * exists so the first time he sees the report is not 23:30 on the night
                         * he turns it on, and it sends to whatever address is currently typed,
                         * saved or not.
                         */
                        Forms\Components\Tabs\Tab::make('Daily report')
                            ->icon('heroicon-o-envelope-open')
                            ->schema([
                                Forms\Components\Section::make('The nightly digest')
                                    ->description('Sent at 23:30 Asia/Dhaka through Resend. Leads, subscribers and traffic for the day, plus anything that failed.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('daily_report_enabled')
                                            ->label('Send it every night')
                                            ->helperText('Off means the scheduled run does nothing. The test button still works.'),

                                        Forms\Components\TextInput::make('daily_report_recipient')
                                            ->label('Send to')
                                            ->email()
                                            ->required()
                                            ->maxLength(150)
                                            ->helperText('One address. The report is internal, so it is not a mailing list.'),
                                    ]),

                                Forms\Components\Section::make('Sending')
                                    ->description('Through Resend. The key is stored encrypted, never rendered back into this page, and never sent to the frontend.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('resend_api_key')
                                            ->label('Resend API key')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(200)
                                            ->columnSpanFull()
                                            // Blank means "keep the saved one". Without this the
                                            // form would wipe the credential every time anyone
                                            // saves any other tab on this page.
                                            ->dehydrated(fn (?string $state): bool => filled($state))
                                            ->placeholder($this->hasToken('resend_api_key') ? 'Saved — leave blank to keep it' : 'Not set — RESEND_API_KEY in .env is used')
                                            ->helperText('From resend.com → API Keys. Takes precedence over .env, so it can be rotated without a deploy.'),

                                        Forms\Components\Toggle::make('resend_clear_token')
                                            ->label('Remove the saved key')
                                            ->visible(fn (): bool => $this->hasToken('resend_api_key'))
                                            ->helperText('Deletes the stored key when the page is saved. If .env still has one, that is used again.')
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Check it now')
                                    ->description('Sends immediately to the address above, whether or not it has been saved and whether or not the nightly switch is on.')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('sendTestReport')
                                                ->label('Send a test email')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->action(function (): void {
                                                    $to = (string) ($this->data['daily_report_recipient'] ?? '');

                                                    $exitCode = Artisan::call('report:daily', [
                                                        '--force' => true,
                                                        '--to' => $to,
                                                    ]);

                                                    $output = trim(Artisan::output());

                                                    Notification::make()
                                                        ->title($exitCode === 0 ? 'Test email sent' : 'The test email failed')
                                                        ->body($output !== '' ? $output : 'No output from the command.')
                                                        ->{$exitCode === 0 ? 'success' : 'danger'}()
                                                        ->send();
                                                }),
                                        ]),
                                    ]),
                            ]),

                        /*
                         * The newsletter list.
                         *
                         * BREVO, NOT RESEND. Brevo owns the marketing list — double opt-in,
                         * unsubscribe compliance and list hygiene — which is a legal
                         * requirement for bulk email and not something to hand-roll. This tab
                         * only holds the credential; the list itself lives in Brevo.
                         *
                         * IT WAS IN `.env` AND EMPTY. Every sign-up was stored locally and
                         * never reached the list, and nothing in the panel said so — the
                         * "Sync to Brevo" button just failed with a message about the webhook
                         * log. Now the key is here, and the button says "not configured" when
                         * it is missing.
                         */
                        Forms\Components\Tabs\Tab::make('Newsletter')
                            ->icon('heroicon-o-at-symbol')
                            ->schema([
                                Forms\Components\Section::make('Brevo')
                                    ->description('Where newsletter sign-ups are pushed. Subscribers are always stored here first, so a Brevo outage never loses an address.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('brevo_api_key')
                                            ->label('Brevo API key')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(200)
                                            ->columnSpanFull()
                                            // Blank keeps the saved key; only the explicit
                                            // toggle below removes it.
                                            ->dehydrated(fn (?string $state): bool => filled($state))
                                            ->placeholder($this->hasToken('brevo_api_key') ? 'Saved — leave blank to keep it' : 'Not set — BREVO_API_KEY in .env is used')
                                            ->helperText('From Brevo → SMTP & API → API Keys. Takes precedence over .env, so it can be rotated without a deploy.'),

                                        Forms\Components\TextInput::make('brevo_list_id')
                                            ->label('List id')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxLength(12)
                                            ->placeholder('3')
                                            ->helperText('The numeric id of the list in Brevo → Contacts → Lists. Both this and the key are needed before anything syncs.'),

                                        Forms\Components\Toggle::make('brevo_clear_token')
                                            ->label('Remove the saved key')
                                            ->visible(fn (): bool => $this->hasToken('brevo_api_key'))
                                            ->helperText('Deletes the stored key when the page is saved.')
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Check it now')
                                    ->description('Pushes every subscriber that has not reached Brevo yet. The same job runs by itself every hour.')
                                    ->schema([
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('syncSubscribers')
                                                ->label('Sync pending subscribers')
                                                ->icon('heroicon-o-arrow-path')
                                                ->action(function (): void {
                                                    $exitCode = Artisan::call('sharkweb:sync-newsletter', ['--limit' => 200]);
                                                    $output = trim(Artisan::output());

                                                    Notification::make()
                                                        ->title($exitCode === 0 ? 'Sync finished' : 'The sync reported failures')
                                                        ->body($output !== '' ? $output : 'No output from the command.')
                                                        ->{$exitCode === 0 ? 'success' : 'danger'}()
                                                        ->send();
                                                }),
                                        ]),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = app(GeneralSettings::class);
        $state = $this->form->getState();

        /*
         * Removing a credential is an explicit act.
         *
         * A blank token field means "leave the saved one alone" — a form that wipes a secret
         * because someone tabbed past the field is worse than having no field at all, and it
         * would take server-side tracking down without a word.
         */
        foreach (['meta_capi', 'tiktok', 'resend', 'brevo'] as $prefix) {
            if ($state[$prefix.'_clear_token'] ?? false) {
                $state[$prefix.'_access_token'] = null;
            }
        }

        /*
         * An empty upload must not blank the portrait.
         *
         * `FileUpload` submits null when the field is left untouched, and
         * `founder_avatar_path` is a non-nullable string — so without this, saving any other
         * tab would either wipe the image or throw on the typed property.
         */
        if (blank($state['founder_avatar_path'] ?? null)) {
            $state['founder_avatar_path'] = $settings->founder_avatar_path;
        }

        foreach ($state as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->{$key} = $value;
            }
        }

        $settings->save();

        // Settings have no model, so nothing would otherwise record that they changed —
        // and "the WhatsApp number changed, who changed it" is exactly the question an
        // activity log exists to answer. Fails soft: a log write must never break a save.
        //
        // `causedBy()`, not `causer()`: activitylog v4 has no `causer()` method, and the
        // fail-soft catch turned that mistake into a silently empty audit trail.
        try {
            activity('settings')
                ->event('settings_updated')
                ->causedBy(auth()->user())
                ->withProperties(['fields' => array_keys($state)])
                ->log('Site settings saved');
        } catch (\Throwable $e) {
            report($e);
        }

        // The clear toggles are actions, not stored values — leaving one switched on would
        // silently delete the token again the next time anything else on this page is saved.
        $this->form->fill(array_merge(
            $settings->toArray(),
            [
                'meta_capi_access_token' => null,
                'tiktok_access_token' => null,
                'resend_api_key' => null,
                'brevo_api_key' => null,
                'meta_capi_clear_token' => false,
                'tiktok_clear_token' => false,
                'resend_clear_token' => false,
                'brevo_clear_token' => false,
            ],
        ));

        Notification::make()
            ->title('Settings saved')
            ->body('Live within five minutes. Settings saves fire no revalidation webhook — the frontend reads them on a 300-second cache.')
            ->success()
            ->send();
    }

    /**
     * Whether a credential is already saved, for the write-only fields' placeholder.
     *
     * Reads the stored setting rather than the form state on purpose: the form deliberately
     * never holds the token, so form state would always say "nothing saved".
     */
    private function hasToken(string $property): bool
    {
        return filled(app(GeneralSettings::class)->{$property});
    }

}

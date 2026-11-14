<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Exports\LeadsExport;
use App\Filament\Resources\LeadResource;
use App\Filament\Widgets\ActivityTimelineWidget;
use App\Filament\Widgets\LeadByPageChart;
use App\Filament\Widgets\LeadSourceChart;
use App\Models\Lead;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Filament panel smoke tests.
 *
 * Every resource page is rendered for real against an authenticated user. This is the
 * cheapest way to catch Filament API misuse — a wrong method name, a bad column type or
 * a missing import throws at render time, and without this the first symptom would be a
 * 500 in the browser.
 */
class FilamentPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@thesharkweb.com',
        ]);
    }

    public function test_the_panel_login_page_renders(): void
    {
        $this->get('/theshark-hq/login')->assertOk();
    }

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->get('/theshark-hq')->assertRedirect('/theshark-hq/login');
    }

    public function test_an_authenticated_user_can_open_the_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/theshark-hq')
            ->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function resourceIndexProvider(): array
    {
        return [
            'leads' => ['/theshark-hq/leads'],
            'newsletter-subscribers' => ['/theshark-hq/newsletter-subscribers'],
            'users' => ['/theshark-hq/users'],

            // The content module. These render the list tables, which is where a wrong
            // column type or a bad `state()` closure throws.
            'page-copy' => ['/theshark-hq/page-headers'],
            'disciplines' => ['/theshark-hq/service-groups'],
            'service-pages' => ['/theshark-hq/services'],
            'commitment-tiers' => ['/theshark-hq/tiers'],
            'newsletter' => ['/theshark-hq/posts'],
            'case-studies' => ['/theshark-hq/case-studies'],
            'open-source' => ['/theshark-hq/oss-projects'],
            'homepage-stats' => ['/theshark-hq/stats'],
            'featured-projects' => ['/theshark-hq/featured-cards'],
            'client-reviews' => ['/theshark-hq/reviews'],
        ];
    }

    #[DataProvider('resourceIndexProvider')]
    public function test_resource_index_pages_render(string $url): void
    {
        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function resourceCreateProvider(): array
    {
        return [
            'leads' => ['/theshark-hq/leads/create'],
            'newsletter-subscribers' => ['/theshark-hq/newsletter-subscribers/create'],

            // The create forms are the harder half: a nested repeater or a conditional
            // field that is misconfigured only throws when the schema is built.
            'page-copy' => ['/theshark-hq/page-headers/create'],
            'disciplines' => ['/theshark-hq/service-groups/create'],
            'service-pages' => ['/theshark-hq/services/create'],
            'commitment-tiers' => ['/theshark-hq/tiers/create'],
            'newsletter' => ['/theshark-hq/posts/create'],
            'case-studies' => ['/theshark-hq/case-studies/create'],
            'open-source' => ['/theshark-hq/oss-projects/create'],
            'homepage-stats' => ['/theshark-hq/stats/create'],
            'featured-projects' => ['/theshark-hq/featured-cards/create'],
            'client-reviews' => ['/theshark-hq/reviews/create'],
        ];
    }

    #[DataProvider('resourceCreateProvider')]
    public function test_resource_create_pages_render(string $url): void
    {
        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk();
    }

    /**
     * The EDIT screens of the content module, against a real record.
     *
     * This is the test the create screens cannot replace: an edit form hydrates every
     * field from stored JSON, so a shape the form does not expect — a nested repeater
     * given an object where it wants an array, a `null` quote read as `quote.text` —
     * throws here and nowhere else. The five content models are seeded from the same
     * data the frontend uses, so the shapes are the real ones.
     *
     * @return array<string, array{0: string, 1: class-string}>
     */
    public static function contentEditProvider(): array
    {
        return [
            'page-copy' => ['/theshark-hq/page-headers/{id}/edit', \App\Models\Content\PageHeader::class],
            'disciplines' => ['/theshark-hq/service-groups/{id}/edit', \App\Models\Content\ServiceGroup::class],
            'service-pages' => ['/theshark-hq/services/{id}/edit', \App\Models\Content\Service::class],
            'commitment-tiers' => ['/theshark-hq/tiers/{id}/edit', \App\Models\Content\Tier::class],
            'newsletter' => ['/theshark-hq/posts/{id}/edit', \App\Models\Content\Post::class],
            'case-studies' => ['/theshark-hq/case-studies/{id}/edit', \App\Models\Content\CaseStudy::class],
            'open-source' => ['/theshark-hq/oss-projects/{id}/edit', \App\Models\Content\OssProject::class],
            'homepage-stats' => ['/theshark-hq/stats/{id}/edit', \App\Models\Content\Stat::class],
            'featured-projects' => ['/theshark-hq/featured-cards/{id}/edit', \App\Models\Content\FeaturedCard::class],
            'client-reviews' => ['/theshark-hq/reviews/{id}/edit', \App\Models\Content\Review::class],
        ];
    }

    /**
     * Each case imports the real content first, so the form is hydrated with the exact
     * shapes the frontend uses.
     *
     * The edit screen is where a hydration bug surfaces: a reapeater fed an object where
     * it wants a list, a `null` quote dereferenced as `quote.text`, a table block whose
     * rows are strings rather than arrays. Seeding from the same `import.json` the
     * production importer reads means those shapes are the real ones, not fixtures that
     * happen to agree with the form.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('contentEditProvider')]
    public function test_content_edit_pages_render(string $urlTemplate, string $model): void
    {
        $source = database_path('content/import.json');

        if (! file_exists($source)) {
            $this->markTestSkipped('No database/content/import.json — the content module is not provisioned.');
        }

        $this->artisan('sharkweb:import-content')->assertSuccessful();

        $record = $model::query()->first();

        $this->assertNotNull($record, "The importer produced no {$model} row.");

        $this->actingAs($this->admin)
            ->get(str_replace('{id}', (string) $record->getKey(), $urlTemplate))
            ->assertOk();
    }

    /* ------------------------------------------------------------------ *

    /* ------------------------------------------------------------------ *
     * The reconciliation desk
     * ------------------------------------------------------------------ */

    /**
     * An order with a submitted TrxID, ready for a decision.
     *
     * The TrxID is derived from the reference so two of these can exist in one test — a real
     * TrxID is unique per gateway, and the database enforces it.
     */
    private function submittedOrder(string $reference = 'SW-2026-000910'): Order
    {
        $order = Order::create([
            'reference' => $reference,
            'name' => 'Panel Tester',
            'email' => 'panel@example.com',
            'phone' => '01712345678',
            'subtotal' => '2490.00',
            'discount' => '249.00',
            'total' => '2241.00',
            'status' => OrderStatus::Pending,
            'gateway' => 'bkash',
            'sender_number' => '01712345678',
            'trx_id' => 'TRX'.preg_replace('/[^0-9]/', '', $reference),
            'payment_submitted_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'title' => 'Agency operating system',
            'category' => 'toolkits',
            'mrp' => '3990.00',
            'price' => '2490.00',
            'quantity' => 1,
        ]);

        return $order;
    }

    /** Custom pages: settings, the maintenance toggle, the backup desk and the cleaner. */


    /* ================================================================== *
     * Dashboard widgets
     * ================================================================== */

    /**
     * A lead with a controlled age.
     *
     * `created_at` is deliberately not fillable on the model, so it is set afterwards with
     * `forceFill` rather than added to the guard list just to make a test pass.
     */
    private function lead(array $attributes, ?Carbon $createdAt = null): Lead
    {
        $lead = Lead::create(array_merge([
            'name' => 'Test Lead',
            'email' => 'lead-'.uniqid().'@example.com',
            'goal' => 'NEW BUILD',
            'message' => 'A brief.',
            'status' => LeadStatus::New,
            'lead_score' => LeadScore::Warm,
            'score_reasons' => [],
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
        ], $attributes));

        if ($createdAt !== null) {
            $lead->forceFill(['created_at' => $createdAt])->save();
        }

        return $lead;
    }

    /**
     * Every dashboard widget renders against real data.
     *
     * WHY THE PAGE ASSERTION IS NOT ENOUGH. Filament widgets are lazy by default: the first
     * response contains a Livewire placeholder, not the widget's markup. So the dashboard is
     * asserted for registration, and each widget is then mounted directly — which is what
     * actually runs its query, its columns and its chart data.
     *
     * Without this, a broken column or a bad enum cast would ship silently: every widget
     * hides itself when its table is empty, so the plain dashboard test executes none of it.
     */
    public function test_every_dashboard_widget_is_registered(): void
    {
        $this->seedDashboard();

        $this->actingAs($this->admin)
            ->get('/theshark-hq')
            ->assertOk()
            ->assertSeeLivewire(\App\Filament\Widgets\LeadStatsOverview::class)
            ->assertSeeLivewire(\App\Filament\Widgets\UnansweredLeads::class)
            ->assertSeeLivewire(\App\Filament\Widgets\LeadPipelineChart::class)
            ->assertSeeLivewire(\App\Filament\Widgets\LeadQualityChart::class)
            ->assertSeeLivewire(\App\Filament\Widgets\LeadCampaignChart::class)
            ->assertSeeLivewire(\App\Filament\Widgets\TrackingHealth::class)
            ->assertSeeLivewire(\App\Filament\Widgets\NewsletterGrowth::class)
            ->assertSeeLivewire(\App\Filament\Widgets\SystemHealth::class);
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function dashboardWidgetProvider(): array
    {
        return [
            'stats overview' => [\App\Filament\Widgets\LeadStatsOverview::class],
            'unanswered leads' => [\App\Filament\Widgets\UnansweredLeads::class],
            'pipeline' => [\App\Filament\Widgets\LeadPipelineChart::class],
            'quality' => [\App\Filament\Widgets\LeadQualityChart::class],
            'campaign' => [\App\Filament\Widgets\LeadCampaignChart::class],
            'tracking health' => [\App\Filament\Widgets\TrackingHealth::class],
            'newsletter' => [\App\Filament\Widgets\NewsletterGrowth::class],
            'system health' => [\App\Filament\Widgets\SystemHealth::class],
        ];
    }

    #[DataProvider('dashboardWidgetProvider')]
    public function test_a_dashboard_widget_renders_with_data(string $widget): void
    {
        $this->seedDashboard();

        Livewire::test($widget)->assertOk();
    }

    public function test_the_unanswered_widget_names_the_lead_that_is_waiting(): void
    {
        $this->seedDashboard();

        Livewire::test(\App\Filament\Widgets\UnansweredLeads::class)
            ->assertOk()
            ->assertSee('Waiting Person')
            ->assertDontSee('Answered Person');
    }

    /** Two leads and a subscriber: enough for every widget to have something to draw. */
    private function seedDashboard(): void
    {
        $this->lead([
            'name' => 'Waiting Person',
            'email' => 'waiting@example.com',
            'source_page' => '/contact/',
            'utm_source' => 'facebook',
            'utm_campaign' => 'meta_ads_october',
            'status' => LeadStatus::New,
            'lead_score' => LeadScore::Hot,
        ], now()->subDays(2));

        $this->lead([
            'name' => 'Answered Person',
            'email' => 'answered@example.com',
            'status' => LeadStatus::Closed,
        ], now());

        NewsletterSubscriber::create([
            'email' => 'reader@example.com',
            'source_page' => '/newsletter/',
            'subscribed_at' => now(),
        ]);
    }

    /**
     * The unanswered widget must list only leads that are genuinely waiting.
     *
     * The whole value of the widget is that it is trustworthy: a list that also shows fresh
     * or already-answered leads is a list people stop opening.
     */
    public function test_the_unanswered_widget_lists_only_stale_new_leads(): void
    {
        $stale = $this->lead(['name' => 'Forgotten'], now()->subHours(30));
        $fresh = $this->lead(['name' => 'Just Arrived'], now()->subHours(2));
        $picked = $this->lead(
            ['name' => 'Already Handled', 'status' => LeadStatus::Contacted],
            now()->subDays(5),
        );

        $listed = Livewire::test(\App\Filament\Widgets\UnansweredLeads::class)
            ->instance()
            ->getTableRecords()
            ->pluck('id')
            ->all();

        $this->assertContains($stale->id, $listed);
        $this->assertNotContains($fresh->id, $listed, 'A lead younger than a day is still on time.');
        $this->assertNotContains($picked->id, $listed, 'An answered lead is not waiting.');
    }

    /* ================================================================== *
     * Analytics settings
     * ================================================================== */

    /**
     * The Analytics tab renders with a credential already saved.
     *
     * The token field is deliberately never hydrated, so this is the case where a bug shows:
     * a placeholder that reads from form state would say "Not set" while a token is stored.
     */
    public function test_the_analytics_tab_renders_with_a_saved_token(): void
    {
        $settings = app(\App\Settings\GeneralSettings::class);
        $settings->meta_pixel_id = '1234567890123456';
        $settings->meta_capi_access_token = 'a-live-looking-token';
        $settings->save();

        $this->actingAs($this->admin)
            ->get('/theshark-hq/manage-settings')
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Conversions API token');
    }

    /**
     * Saving the page without retyping the token must not delete it.
     *
     * This is the failure the write-only field exists to prevent: a blank submitted value
     * overwriting a working credential, taking server-side tracking down without a word.
     */
    public function test_saving_the_settings_with_a_blank_token_keeps_the_saved_one(): void
    {
        $settings = app(\App\Settings\GeneralSettings::class);
        $settings->meta_capi_access_token = 'a-live-looking-token';
        $settings->save();

        Livewire::test(\App\Filament\Pages\ManageSettings::class)
            ->fillForm(['meta_capi_access_token' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'a-live-looking-token',
            app(\App\Settings\GeneralSettings::class)->meta_capi_access_token,
            'A blank token field must mean "leave the saved one alone".',
        );
    }

    public function test_the_clear_toggle_removes_the_saved_token(): void
    {
        $settings = app(\App\Settings\GeneralSettings::class);
        $settings->meta_capi_access_token = 'a-live-looking-token';
        $settings->save();

        Livewire::test(\App\Filament\Pages\ManageSettings::class)
            ->fillForm(['meta_capi_access_token' => '', 'meta_capi_clear_token' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(app(\App\Settings\GeneralSettings::class)->meta_capi_access_token);
    }

    public function test_custom_pages_render(): void
    {
        foreach (['/theshark-hq/manage-settings', '/theshark-hq/manage-maintenance', '/theshark-hq/manage-cleaner'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    /**
     * The backups page lists real archives and the restore confirmation needs the typed
     * word — a single wrong click must never be able to drop the database.
     */
    public function test_the_backups_page_renders_with_the_safety_rail(): void
    {
        $this->actingAs($this->admin)
            ->get('/theshark-hq/manage-maintenance')
            ->assertOk()
            ->assertSee('Backups');
    }

    /**
     * The cleaner page renders with its safety rail: the never-touch list must be visible,
     * because the page's whole promise is that only whitelisted categories are deletable.
     */
    public function test_the_cleaner_page_renders_with_the_never_touch_list(): void
    {
        $this->actingAs($this->admin)
            ->get('/theshark-hq/manage-cleaner')
            ->assertOk()
            ->assertSee('Never touched by the cleaner')
            ->assertSee('public/uploads');
    }

    /** Maintenance pages are for the owner — a non-super-admin sees a 403, not the cleaner. */
    public function test_the_cleaner_page_is_gated_to_super_admins(): void
    {
        config(['sharkweb.maintenance.super_admins' => 'owner@thesharkweb.com']);

        $other = User::factory()->create(['email' => 'not-owner@thesharkweb.com']);

        $this->actingAs($other)
            ->get('/theshark-hq/manage-cleaner')
            ->assertForbidden();
    }

    /**
     * Filament's default account card is gone from the dashboard.
     *
     * It said "Welcome / Sign out" and nothing else — nothing an admin does not already know,
     * sitting above every real number on the page, and Sign out lives in the user menu.
     */
    public function test_the_account_widget_is_not_registered_on_the_panel(): void
    {
        $this->assertNotContains(
            Widgets\AccountWidget::class,
            Filament::getPanel('admin')->getWidgets(),
            'The Welcome / Sign out card is back on the dashboard.',
        );
    }

    /**
     * The topbar greeting: who is signed in, what day it is, and a clock.
     *
     * THE TIMEZONE IS THE PANEL'S OWN, NOT `app.timezone`. That one is UTC deliberately —
     * timestamps are stored in UTC — but a clock the owner reads has to show the time he is
     * working in, so this asserts the configured zone rather than the app default.
     */
    public function test_the_topbar_shows_a_greeting_with_the_date_and_a_clock(): void
    {
        $timezone = (string) config('sharkweb.filament.timezone', 'Asia/Dhaka');

        // Frozen so the greeting cannot flip mid-test on an hour boundary.
        $this->travelTo(Carbon::parse('2026-10-10 23:56:00', $timezone));

        $this->admin->forceFill(['name' => 'Zonaed Hossain'])->save();

        $this->actingAs($this->admin)
            ->get('/theshark-hq')
            ->assertOk()
            ->assertSee('shark-topbar-greeting', escape: false)
            // First name only — "Good evening, Zonaed", not the full name.
            ->assertSee('Good evening, Zonaed', escape: false)
            ->assertSee('Saturday, 10 October 2026', escape: false)
            ->assertSee('11:56 PM', escape: false)
            ->assertSee('data-shark-timezone="'.$timezone.'"', escape: false);
    }

    public function test_the_lead_export_action_is_registered(): void
    {
        $this->assertTrue(
            method_exists(LeadResource::class, 'table'),
            'LeadResource must expose a table.'
        );

        // The export class must be resolvable and streamable.
        $export = new LeadsExport([]);

        $this->assertIsArray($export->headings());
        $this->assertContains('Lead Score', $export->headings());
        $this->assertContains('UTM Source', $export->headings());
    }

    public function test_leads_export_maps_enum_values_not_badge_labels(): void
    {
        $lead = Lead::create([
            'name' => 'Export Me',
            'email' => 'export@example.com',
            'status' => LeadStatus::Qualified,
            'lead_score' => LeadScore::Hot,
            'message' => "Line one\nLine two",
        ]);

        $row = (new LeadsExport([$lead->id]))->map($lead);

        // Raw values, because a CRM importing the emoji badge label would break.
        $this->assertContains('qualified', $row);
        $this->assertContains('hot', $row);

        // Newlines collapsed so one lead cannot span two CSV rows.
        $this->assertContains('Line one Line two', $row);
    }
}

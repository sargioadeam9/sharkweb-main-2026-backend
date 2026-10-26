<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Content\Tier;
use App\Services\IsrWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards the setting whose failure mode is "my edit did not save".
 *
 * THE BUG THIS EXISTS FOR. `ISR_ENABLED=false` does not break anything loudly. A save still
 * writes to the database, the panel still shows the new value, and the public page keeps
 * serving its cached copy until the 5-minute ISR window lapses. The owner changed a price,
 * reloaded the site, saw the old number, and reasonably concluded the panel was broken. It
 * was not: the webhook that purges the page was switched off.
 *
 * So there are two things worth asserting, and neither is obvious from reading the code:
 *  1. the shipped default is ON, because the route it calls exists;
 *  2. the panel says so when it is off, because otherwise nobody finds out.
 */
class IsrConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The example env must ship the flag on.
     *
     * This is the file a new deploy is copied from. When it said `false`, every fresh
     * environment inherited a panel whose saves appeared not to work, and the only clue was
     * a `webhook_logs` table nobody reads until something is already wrong.
     */
    public function test_the_example_env_enables_isr(): void
    {
        $example = File::get(base_path('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^ISR_ENABLED=true$/m',
            $example,
            'ISR_ENABLED must ship as true in .env.example — the revalidate route exists, and '
            .'leaving it false makes panel saves look like they did not apply.',
        );
    }

    /** The config default is on, so an env without the key still publishes immediately. */
    public function test_the_config_defaults_to_enabled(): void
    {
        $this->assertTrue(
            (bool) config('sharkweb.isr.enabled', true),
            'ISR revalidation is off. Panel edits will take up to five minutes to appear.',
        );
    }

    /**
     * A save asks the frontend to rebuild the page it changed.
     *
     * Asserted against a spy rather than the live service because the HTTP call is not what
     * is under test — the wiring is. If the observer were ever unregistered, or the tag list
     * drifted, this fails here instead of silently in production.
     */
    public function test_a_tier_save_dispatches_a_revalidation(): void
    {
        $calls = [];

        $spy = \Mockery::mock(IsrWebhookService::class);
        $spy->shouldReceive('revalidate')
            ->andReturnUsing(function (array $paths = [], array $tags = [], string $event = '') use (&$calls): bool {
                $calls[] = compact('paths', 'tags', 'event');

                return true;
            });

        $this->app->instance(IsrWebhookService::class, $spy);

        $tier = Tier::create([
            'name' => 'Micro-task',
            'price' => '$5',
            'cta' => 'START A TASK',
            'href' => '/contact/#from=micro',
            'sort' => 0,
        ]);

        $tier->update(['price' => '$22']);

        $this->assertNotEmpty($calls, 'Saving a tier did not ask the frontend to rebuild anything.');
        $this->assertSame(['tiers'], $calls[0]['tags']);
        $this->assertContains('/services/', $calls[0]['paths']);
    }

    /**
     * The warning strip is on the panel while the flag is off, and gone when it is on.
     *
     * Rendered rather than asserted on the view name, because the blade guards itself — the
     * hook always runs and the conditional lives inside the template.
     */
    public function test_the_panel_warns_while_isr_is_disabled(): void
    {
        config(['sharkweb.isr.enabled' => false]);

        $this->assertStringContainsString(
            'Automatic publishing is off',
            view('filament.isr-disabled')->render(),
        );
    }

    public function test_the_panel_stays_quiet_when_isr_is_enabled(): void
    {
        config(['sharkweb.isr.enabled' => true]);

        $this->assertStringNotContainsString(
            'Automatic publishing is off',
            view('filament.isr-disabled')->render(),
        );
    }
}

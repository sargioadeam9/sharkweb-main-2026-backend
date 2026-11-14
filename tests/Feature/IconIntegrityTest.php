<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Filament\Support\Contracts\HasIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Icon integrity.
 *
 * WHY THIS EXISTS — a real production bug this catches
 * ----------------------------------------------------
 * `LeadScore::Cold` returned `heroicon-o-snowflake`, and **Heroicons ships no snowflake
 * glyph**. Blade Icons throws `SvgNotFound`, so the whole leads list returned a 500 the
 * moment a cold lead was on screen.
 *
 * The panel tests did not catch it because they render the resource tables **empty** — with
 * no rows, no badge ever resolves an icon. A green suite, and a broken page for anyone with
 * a cold lead in their pipeline.
 *
 * These two tests close that hole from both ends: the first checks every enum icon resolves
 * at all, the second renders a list that actually contains one row per score.
 */
class IconIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every icon referenced by an enum must exist in its icon set.
     *
     * `svg()` throws `SvgNotFound` for a missing icon, so simply resolving it is the
     * assertion. Add new enums to the list when they gain a `getIcon()`.
     *
     * @return array<string, array{0: class-string}>
     */
    public static function iconEnumProvider(): array
    {
        return [
            'LeadScore' => [LeadScore::class],
            'LeadStatus' => [LeadStatus::class],
        ];
    }

    /**
     * @param  class-string  $enum
     */
    #[DataProvider('iconEnumProvider')]
    public function test_every_enum_icon_resolves(string $enum): void
    {
        $this->assertTrue(
            is_subclass_of($enum, HasIcon::class),
            "{$enum} does not implement HasIcon — remove it from the provider.",
        );

        foreach ($enum::cases() as $case) {
            $icon = $case->getIcon();

            try {
                $html = svg($icon)->toHtml();
            } catch (\Throwable $e) {
                $this->fail(
                    "{$enum}::{$case->name} references icon '{$icon}', which does not resolve: "
                    .$e->getMessage()
                );
            }

            $this->assertNotSame('', trim($html), "{$enum}::{$case->name} rendered an empty '{$icon}'.");
        }
    }

    /**
     * The leads list must render with a row for every score.
     *
     * This is the page-level version of the check above, and it is the one that would have
     * caught the snowflake: the list is ordered and paginated, so a score that only appears
     * on a later page is never rendered by an empty-table test.
     */
    public function test_the_leads_list_renders_a_badge_for_every_score(): void
    {
        $admin = User::factory()->create(['email' => 'admin@thesharkweb.com']);

        foreach (LeadScore::cases() as $index => $score) {
            Lead::create([
                'name' => "Score Badge {$score->name}",
                'email' => strtolower($score->name).'@acmecorp.com',
                'goal' => 'Growth Audit',
                'status' => LeadStatus::New,
                'lead_score' => $score,
            ]);
        }

        $this->assertSame(
            count(LeadScore::cases()),
            Lead::query()->count(),
            'Every score must have a row, or this test silently stops covering one of them.',
        );

        $this->actingAs($admin)
            ->get('/theshark-hq/leads')
            ->assertOk();
    }
}

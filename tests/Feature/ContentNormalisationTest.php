<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\CaseStudyResource;
use App\Filament\Resources\OssProjectResource;
use App\Models\Content\Post;
use Tests\TestCase;

/**
 * The repeater → stored-JSON conversions.
 *
 * THIS IS THE PART THAT CANNOT BE SEEN BY LOOKING. A block builder stores what an editor
 * typed; the frontend switches on a union of shapes. Between the two sits these functions,
 * and every failure mode here is silent: a heading with no anchor renders but cannot be
 * linked to, a table whose rows came back as strings renders nothing at all, a blank quote
 * renders as an empty quotation mark rather than as "under NDA".
 *
 * The inputs below are the exact shapes Filament's repeaters produce, including the
 * `{item: "..."}` and `{cells: "..."}` wrappers that `->simple()` adds.
 */
class ContentNormalisationTest extends TestCase
{
    /* ================================================================== *
     * Newsletter block builder
     * ================================================================== */

    public function test_a_heading_gets_an_anchor_derived_from_its_text(): void
    {
        $blocks = Post::normaliseBlocks([
            ['t' => 'h2', 'html' => 'What Core Web Vitals <em>actually</em> measure', 'id' => ''],
        ]);

        $this->assertSame('what-core-web-vitals-actually-measure', $blocks[0]['id']);
        $this->assertSame('What Core Web Vitals <em>actually</em> measure', $blocks[0]['html']);
    }

    /**
     * Two headings with the same text must not share an anchor — the second would be
     * unreachable from the contents list, which is the whole point of having one.
     */
    public function test_duplicate_headings_get_distinct_anchors(): void
    {
        $blocks = Post::normaliseBlocks([
            ['t' => 'h2', 'html' => 'The short answer', 'id' => ''],
            ['t' => 'p', 'html' => 'First.'],
            ['t' => 'h2', 'html' => 'The short answer', 'id' => ''],
        ]);

        $this->assertSame('the-short-answer', $blocks[0]['id']);
        $this->assertSame('the-short-answer-2', $blocks[2]['id']);
    }

    public function test_an_explicit_anchor_is_kept(): void
    {
        $blocks = Post::normaliseBlocks([
            ['t' => 'h2', 'html' => 'Anything', 'id' => 'my-chosen-anchor'],
        ]);

        $this->assertSame('my-chosen-anchor', $blocks[0]['id']);
    }

    public function test_list_items_are_unwrapped_from_the_simple_repeater(): void
    {
        $blocks = Post::normaliseBlocks([
            ['t' => 'ul', 'items' => [['item' => 'One'], ['item' => 'Two'], ['item' => '']]],
        ]);

        $this->assertSame(['One', 'Two'], $blocks[0]['items']);
    }

    /**
     * The table is the one that would silently lose data: `->simple()` stores each row as
     * `{cells: "a\nb"}` and the frontend wants `["a","b"]`. Splitting on newlines rather
     * than commas is what lets a cell contain a comma.
     */
    public function test_table_rows_are_split_back_into_cells(): void
    {
        $blocks = Post::normaliseBlocks([
            [
                't' => 'table',
                'head' => [['cell' => 'Metric'], ['cell' => 'Target']],
                'rows' => [
                    ['cells' => "LCP\nunder 2.5s"],
                    ['cells' => "INP, 95th percentile\nunder 200ms"],
                ],
            ],
        ]);

        $this->assertSame(['Metric', 'Target'], $blocks[0]['head']);
        $this->assertSame(['LCP', 'under 2.5s'], $blocks[0]['rows'][0]);
        // The comma inside the cell survives, which is why the split is on newlines.
        $this->assertSame(['INP, 95th percentile', 'under 200ms'], $blocks[0]['rows'][1]);
    }

    public function test_an_unknown_block_type_degrades_to_a_paragraph(): void
    {
        $blocks = Post::normaliseBlocks([
            ['t' => 'callout', 'html' => 'Something worth keeping.'],
        ]);

        $this->assertSame('p', $blocks[0]['t']);
        $this->assertSame('Something worth keeping.', $blocks[0]['html']);
    }

    public function test_a_null_body_stays_null(): void
    {
        $this->assertNull(Post::normaliseBlocks(null));
    }

    /* ================================================================== *
     * The round trip — fill the form, save it, fill it again
     *
     * This is the sequence an editor actually performs, and the one that would quietly
     * destroy content: stored JSON → repeater → stored JSON. If either direction is
     * asymmetric, pressing Save on an untouched issue rewrites it as empty.
     * ================================================================== */

    /**
     * @return list<array<string, mixed>>
     */
    private function storedBody(): array
    {
        return [
            ['t' => 'h2', 'html' => 'What they measure', 'id' => 'what-they-measure'],
            ['t' => 'p', 'html' => 'Three numbers.'],
            ['t' => 'ul', 'items' => ['LCP', 'INP']],
            ['t' => 'table', 'head' => ['Metric', 'Target'], 'rows' => [['LCP', '2.5s'], ['INP', '200ms']]],
        ];
    }

    public function test_the_form_receives_the_wrappers_its_repeaters_expect(): void
    {
        $form = Post::blocksForForm($this->storedBody());

        $this->assertSame([['item' => 'LCP'], ['item' => 'INP']], $form[2]['items']);
        $this->assertSame([['cell' => 'Metric'], ['cell' => 'Target']], $form[3]['head']);
        $this->assertSame([['cells' => "LCP\n2.5s"], ['cells' => "INP\n200ms"]], $form[3]['rows']);
    }

    /**
     * The whole point: fill then save must return the body unchanged.
     */
    public function test_a_body_survives_a_fill_and_save_cycle_unchanged(): void
    {
        $stored = $this->storedBody();

        $resaved = Post::normaliseBlocks(Post::blocksForForm($stored));

        $this->assertSame($stored, $resaved);
    }

    public function test_filling_twice_is_harmless(): void
    {
        $once = Post::blocksForForm($this->storedBody());
        $twice = Post::blocksForForm($once);

        $this->assertSame($once, $twice);
    }

    public function test_takeaways_survive_a_fill_and_save_cycle_unchanged(): void
    {
        $stored = ['Onboarding follows four stages.', 'Fast feedback keeps a project moving.'];

        $resaved = Post::normaliseTakeaways(Post::takeawaysForForm($stored));

        $this->assertSame($stored, $resaved);
    }

    /* ================================================================== *
     * Case study
     * ================================================================== */

    /**
     * A blank quote is the absence of a quote. Storing `{text: "", name: "", role: ""}`
     * would render an empty quotation, when the card is supposed to show the NDA note.
     */
    public function test_an_all_blank_quote_collapses_to_null(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'quote' => ['text' => '', 'name' => '', 'role' => ''],
        ]);

        $this->assertNull($data['quote']);
    }

    public function test_a_quote_with_text_is_kept(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'quote' => ['text' => 'They shipped.', 'name' => 'Sam', 'role' => 'Founder'],
        ]);

        $this->assertSame('They shipped.', $data['quote']['text']);
    }

    public function test_blank_phase_numbers_are_filled_by_position(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'phases' => [
                ['num' => '', 'title' => 'The leak', 'tags' => []],
                ['num' => '', 'title' => 'The rebuild', 'tags' => []],
            ],
        ]);

        $this->assertSame('01', $data['phases'][0]['num']);
        $this->assertSame('02', $data['phases'][1]['num']);
    }

    public function test_an_explicit_phase_number_is_kept(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'phases' => [['num' => '07', 'title' => 'Odd one', 'tags' => []]],
        ]);

        $this->assertSame('07', $data['phases'][0]['num']);
    }

    public function test_the_stack_is_unwrapped_and_empties_dropped(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'services' => [['service' => 'Meta Ads'], ['service' => ''], ['service' => 'CRO']],
        ]);

        $this->assertSame(['Meta Ads', 'CRO'], $data['services']);
    }

    public function test_phase_takeaways_are_unwrapped(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'phases' => [
                ['num' => '01', 'title' => 'The leak', 'tags' => [['tag' => 'High ad fatigue'], ['tag' => '']]],
            ],
        ]);

        $this->assertSame(['High ad fatigue'], $data['phases'][0]['tags']);
    }

    public function test_a_metric_with_no_number_is_dropped(): void
    {
        $data = CaseStudyResource::normaliseCase([
            'client' => 'A Client',
            'metrics' => [
                ['value' => '3x', 'label' => 'ROAS'],
                ['value' => '', 'label' => 'Orphaned label'],
            ],
        ]);

        $this->assertCount(1, $data['metrics']);
        $this->assertSame('3x', $data['metrics'][0]['value']);
    }

    /* ================================================================== *
     * Open source
     * ================================================================== */

    /**
     * The form shows one radio ("Green") because the tile stores two classes
     * (`bg-green` and `text-green`). Splitting the pair here is what keeps an editor from
     * having to keep two fields in step.
     */
    public function test_the_accent_radio_splits_into_the_two_stored_classes(): void
    {
        $data = OssProjectResource::normaliseOss([
            'name' => 'GITiempo',
            'accent_pair' => 'bg-green|text-green',
        ]);

        $this->assertSame('bg-green', $data['bar']);
        $this->assertSame('text-green', $data['label']);
        $this->assertArrayNotHasKey('accent_pair', $data);
    }

    public function test_a_missing_accent_pair_leaves_the_columns_alone(): void
    {
        $data = OssProjectResource::normaliseOss([
            'name' => 'GITiempo',
            'bar' => 'bg-orange',
            'label' => 'text-orange',
        ]);

        $this->assertSame('bg-orange', $data['bar']);
        $this->assertSame('text-orange', $data['label']);
    }

    public function test_blank_alt_text_falls_back_to_the_project_name(): void
    {
        $data = OssProjectResource::normaliseOss([
            'name' => 'WhenWarEnds',
            'alt' => '',
        ]);

        $this->assertSame('WhenWarEnds', $data['alt']);
    }
}

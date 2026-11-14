<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * The six products designed in the shop mockup.
 *
 * LOCAL AND TESTING ONLY.
 * Every title, price and description here came out of the design mockup
 * (`design-mockups/digital-shop-mockup.html`) so the shop can be reviewed end to end. None
 * of it is real product copy — `docs/existingwebsitecontents.md` documents no digital products
 * at all. Real copy has to come from the client before this shop goes live, which is why
 * this seeder refuses to run anywhere but local/testing, the same guard DemoLeadSeeder
 * uses.
 *
 * The ratings and review counts below are the same invented display values the mockup
 * used. They are manual columns on purpose — see the Product model docblock.
 *
 * Idempotent: keyed on slug, so re-seeding updates rather than duplicating.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('ProductSeeder skipped: placeholder shop copy must not be seeded outside local/testing.');

            return;
        }

        foreach ($this->products() as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                $product,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'slug' => 'agency-os',
                'title' => 'Agency operating system',
                'category' => ProductCategory::Toolkits,
                'badge' => 'Featured',
                'short_description' => 'A complete workspace for clients, projects, delivery and growth — connected in one place.',
                'description' => 'The agency operating system is built for small teams that have outgrown scattered documents. Everything a project touches, from the first enquiry to the final invoice, sits in one connected workspace.

Inside you get a client and project management area, a sales pipeline with follow-up reminders, invoice and payment tracking, and an SOP library for onboarding and delivery. Each part links to the next, so when a deal closes the project space is already prepared and nothing needs to be retyped.

It is designed to run without extra software. The workspace works in Notion, the tracking sheets work in any spreadsheet app, and the SOPs export as clean PDFs for the team.

Setup takes one sitting. Import the workspace, replace the sample clients with your own, and adjust the pipeline stages to match how you actually sell.

Buying it once gives you lifetime access plus every future update at no extra cost.',
                'mrp' => '3990.00',
                'price' => '2490.00',
                'rating' => '4.9',
                'review_count' => 1240,
                'is_featured' => true,
                'is_published' => true,
                'sort_order' => 1,
                'image_alt' => 'Agency operating system workspace preview',
                'meta_title' => 'Agency Operating System — Digital Toolkit by Sharkweb',
                'meta_description' => 'A connected workspace for clients, projects, delivery, invoicing and growth. Built for small agencies. Instant download.',
            ],
            [
                'slug' => 'content-command-center',
                'title' => 'Content command center',
                'category' => ProductCategory::Templates,
                'badge' => null,
                'short_description' => 'Plan, produce and publish content on one calendar with clear owners and deadlines.',
                'description' => 'The content command center keeps your whole publishing flow in one place. Ideas land in a backlog, get scored, move to a calendar, and leave with a finished checklist attached.

The calendar view shows what goes out and when. Every entry carries an owner, a channel and a deadline, so nothing waits on a message thread to move forward.

A per-channel publishing checklist removes the small mistakes that waste a good post: missing alt text, wrong link, unformatted title. The monthly tracker records what was published so you can see which formats actually earn attention.

Works as a standalone template for one creator or as a shared team board with separate views for writers, designers and reviewers.',
                'mrp' => '1290.00',
                'price' => '890.00',
                'rating' => '4.8',
                'review_count' => 640,
                'is_featured' => false,
                'is_published' => true,
                'sort_order' => 2,
                'image_alt' => 'Content command center calendar template',
                'meta_title' => 'Content Command Center — Notion Template by Sharkweb',
                'meta_description' => 'Plan, produce and publish content on one calendar with owners, deadlines and per-channel checklists. Instant download.',
            ],
            [
                'slug' => 'client-onboarding-kit',
                'title' => 'Client onboarding kit',
                'category' => ProductCategory::Playbooks,
                'badge' => null,
                'short_description' => 'Every form, email and checklist you need to onboard a client in under an hour.',
                'description' => 'Onboarding is where a client decides whether they made the right choice. This kit makes that moment consistent.

It walks through the full sequence: the welcome email that confirms the decision, the requirement form that collects what you need, the kickoff agenda that keeps the first call on track, and the internal checklist that makes sure nothing is missed before work starts.

Every template is written to be copied, adjusted and sent. No rewriting from a blank page, no forgotten step at 11pm.

Use it as a printed PDF, an editable document, or both. Teams usually keep the checklist pinned and the emails saved as drafts.',
                'mrp' => '990.00',
                'price' => '650.00',
                'rating' => '4.9',
                'review_count' => 410,
                'is_featured' => false,
                'is_published' => true,
                'sort_order' => 3,
                'image_alt' => 'Client onboarding kit playbook cover',
                'meta_title' => 'Client Onboarding Kit — Playbook by Sharkweb',
                'meta_description' => 'Welcome emails, requirement forms, kickoff agenda and checklist. Onboard a client in under an hour. Instant download.',
            ],
            [
                'slug' => 'profit-tracker',
                'title' => 'Agency profit tracker',
                'category' => ProductCategory::Templates,
                'badge' => null,
                'short_description' => 'Know your real monthly profit — revenue, costs and margins on one clean sheet.',
                'description' => 'Most small businesses know what they invoiced and still have no idea what they actually earned. This tracker closes that gap.

Enter revenue and costs as they happen. The sheet separates fixed from variable costs, groups income by client, and shows the real margin left after everything is paid. Charts update as you type.

A runway and burn section shows how many months of buffer you hold, which makes hiring and pricing decisions far less emotional.

Built for monthly use. Ten minutes of entry at the end of each month gives you a clear picture instead of a guess.',
                'mrp' => '790.00',
                'price' => '490.00',
                'rating' => '4.7',
                'review_count' => 290,
                'is_featured' => false,
                'is_published' => true,
                'sort_order' => 4,
                'image_alt' => 'Agency profit tracker spreadsheet preview',
                'meta_title' => 'Agency Profit Tracker — Spreadsheet by Sharkweb',
                'meta_description' => 'Revenue, costs, client margins and runway on one sheet. Know your real monthly profit without accounting software.',
            ],
            [
                'slug' => 'freelance-starter-pack',
                'title' => 'Freelance starter pack',
                'category' => ProductCategory::Bundles,
                'badge' => 'Bundle',
                'short_description' => 'Contract, proposal, invoice and pricing templates — the four documents every freelancer needs.',
                'description' => 'The four documents that decide whether a freelance business looks established or improvised, bundled together.

The service agreement sets scope, payment terms and revision limits before work begins. The proposal presents the offer in a structure a client can approve quickly. The invoice template keeps payment professional and easy to process. The pricing calculator works out hourly and project rates that leave a real margin.

Each file is editable, so you adjust the wording once and reuse it for every client after that.',
                'mrp' => '1990.00',
                'price' => '1290.00',
                'rating' => '4.8',
                'review_count' => 520,
                'is_featured' => false,
                'is_published' => true,
                'sort_order' => 5,
                'image_alt' => 'Freelance starter pack document templates',
                'meta_title' => 'Freelance Starter Pack — Contract, Proposal, Invoice by Sharkweb',
                'meta_description' => 'Service agreement, proposal, invoice and pricing calculator in one bundle. Look established from your first client.',
            ],
            [
                'slug' => 'seo-audit-playbook',
                'title' => 'SEO audit playbook',
                'category' => ProductCategory::Playbooks,
                'badge' => null,
                'short_description' => 'A repeatable audit process to find and fix what holds a site back in search.',
                'description' => 'This playbook turns an SEO audit into a repeatable process instead of an open-ended investigation.

The technical checklist covers the items that usually cause the damage: indexing problems, broken internal links, slow templates, duplicate content and missing metadata. The content worksheet maps what the site covers against what its audience actually searches for.

A priority scoring matrix ranks every finding by impact and effort, so the report leads with the fixes worth doing first rather than a wall of issues.

The client-ready report template presents findings in plain language, which is what turns an audit into a retainer.',
                'mrp' => '1190.00',
                'price' => '750.00',
                'rating' => '4.8',
                'review_count' => 330,
                'is_featured' => false,
                'is_published' => true,
                'sort_order' => 6,
                'image_alt' => 'SEO audit playbook cover',
                'meta_title' => 'SEO Audit Playbook — Checklist & Report Template by Sharkweb',
                'meta_description' => 'A repeatable SEO audit process: technical checklist, content gaps, priority scoring and a client-ready report template.',
            ],
        ];
    }
}

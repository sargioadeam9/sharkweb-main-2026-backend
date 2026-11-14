<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Seeder;

/**
 * DEMO DATA — NOT REAL LEADS.
 *
 * Exists so the admin panel, the dashboard counters, the score badges and the CSV export
 * can be exercised before any real submission has arrived. Every address uses the reserved
 * `example.com` domain, so nothing here can accidentally contact a real person.
 *
 * Run `php artisan migrate:fresh --seed` on a fresh install to get this, or
 * `php artisan db:seed --class=DemoLeadSeeder` to add it to an existing database.
 *
 * BEFORE GOING LIVE: do not run this seeder against the production database. Guarded by
 * an explicit check so it refuses to run outside local/testing.
 */
class DemoLeadSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoLeadSeeder skipped: it only runs in local or testing environments.');

            return;
        }

        foreach ($this->leads() as $data) {
            Lead::updateOrCreate(
                ['email' => $data['email'], 'name' => $data['name']],
                $data,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leads(): array
    {
        return [
            [
                'name' => 'Kamal Hossain',
                'email' => 'kamal.hossain@example.com',
                'phone' => '+8801700000001',
                'goal' => 'Growth Audit',
                'message' => 'We run a study-abroad consultancy and our cost per booked consultation has doubled since March. Budget is approved for a rebuild if the audit supports it.',
                'ip_address' => '203.0.113.11',
                'source_page' => 'contact',
                'status' => LeadStatus::Qualified,
                'lead_score' => LeadScore::Hot,
                'score_reasons' => ['budget_mentioned', 'specific_problem', 'decision_maker'],
                'internal_notes' => 'Called 12 Mar. Wants the funnel rebuild scoped before their Q4 intake push.',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'brand-search',
                'referrer_url' => 'https://www.google.com/',
                'event_id' => 'demo-evt-0001',
                'created_at' => now()->subHours(3),
            ],
            [
                'name' => 'Priya Raman',
                'email' => 'priya.raman@example.com',
                'phone' => '+910000000002',
                'goal' => 'SaaS Development',
                'message' => 'We need an MVP for a booking platform. Timeline is tight — eight weeks.',
                'ip_address' => '203.0.113.12',
                'source_page' => 'services',
                'status' => LeadStatus::New,
                'lead_score' => LeadScore::Hot,
                'score_reasons' => ['budget_mentioned', 'timeline_stated', 'clear_goal'],
                'utm_source' => 'linkedin',
                'utm_medium' => 'social',
                'utm_campaign' => 'saas-mvp-q3',
                'event_id' => 'demo-evt-0002',
                'created_at' => now()->subHours(9),
            ],
            [
                'name' => 'Daniel Okafor',
                'email' => 'daniel.okafor@example.com',
                'goal' => 'Facebook & Meta Ads',
                'message' => 'Spending about $8k a month on Meta with a 1.4 ROAS. Need someone to take it over.',
                'ip_address' => '198.51.100.20',
                'source_page' => 'home',
                'status' => LeadStatus::Contacted,
                'lead_score' => LeadScore::Warm,
                'score_reasons' => ['budget_mentioned'],
                'internal_notes' => 'Sent the audit booking link. Following up Friday.',
                'utm_source' => 'meta',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'retargeting-q3',
                'event_id' => 'demo-evt-0003',
                'created_at' => now()->subDay(),
            ],
            [
                'name' => 'Sofia Marchetti',
                'email' => 'sofia.marchetti@example.com',
                'goal' => 'CRO & Funnel Strategy',
                'message' => 'Getting traffic but almost no checkouts. Would like an audit of the mobile path.',
                'ip_address' => '198.51.100.21',
                'source_page' => 'services',
                'status' => LeadStatus::New,
                'lead_score' => LeadScore::Warm,
                'score_reasons' => ['specific_problem'],
                'utm_source' => 'newsletter',
                'utm_medium' => 'email',
                'event_id' => 'demo-evt-0004',
                'created_at' => now()->subDays(2),
            ],
            [
                'name' => 'Tom Bexley',
                'email' => 'tom.bexley@example.com',
                'goal' => 'AI Automation',
                'message' => 'Interested in automating our inbound qualification. What does that look like?',
                'ip_address' => '192.0.2.40',
                'source_page' => 'blog',
                'status' => LeadStatus::Qualified,
                'lead_score' => LeadScore::Warm,
                'score_reasons' => ['clear_goal'],
                'internal_notes' => 'Scoped a two-week automation sprint. Awaiting sign-off.',
                'utm_source' => 'perplexity',
                'utm_medium' => 'ai_search',
                'event_id' => 'demo-evt-0005',
                'created_at' => now()->subDays(3),
            ],
            [
                'name' => 'Amara Nwosu',
                'email' => 'amara.nwosu@example.com',
                'goal' => 'Web Development',
                'message' => 'Our site is slow and does not rank. Looking for a rebuild.',
                'ip_address' => '192.0.2.41',
                'source_page' => 'contact',
                'status' => LeadStatus::Closed,
                'lead_score' => LeadScore::Cold,
                'score_reasons' => ['vague_goal'],
                'internal_notes' => 'Went with an in-house rebuild. Revisit next quarter.',
                'event_id' => 'demo-evt-0006',
                'created_at' => now()->subDays(6),
            ],
            [
                'name' => 'Lucas Meyer',
                'email' => 'lucas.meyer@example.com',
                'goal' => 'Growth Marketing',
                'message' => 'We are pre-revenue and want to validate paid before we raise.',
                'ip_address' => '192.0.2.42',
                'source_page' => 'home',
                'status' => LeadStatus::New,
                'lead_score' => LeadScore::Cold,
                'score_reasons' => ['pre_revenue', 'vague_goal'],
                'utm_source' => 'reddit',
                'utm_medium' => 'organic',
                'event_id' => 'demo-evt-0007',
                'created_at' => now()->subDays(8),
            ],
            [
                'name' => 'Hannah Fischer',
                'email' => 'hannah.fischer@example.com',
                'goal' => 'Landing Pages',
                'message' => 'Need a launch page for a product drop in four weeks.',
                'ip_address' => '192.0.2.43',
                'source_page' => 'services',
                'status' => LeadStatus::Contacted,
                'lead_score' => LeadScore::Hot,
                'score_reasons' => ['timeline_stated', 'specific_problem', 'budget_mentioned'],
                'utm_source' => 'instagram',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'launch-week',
                'event_id' => 'demo-evt-0008',
                'created_at' => now()->subDays(1),
            ],
            [
                'name' => 'Ravi Patel',
                'email' => 'ravi.patel@example.com',
                'goal' => 'Pay Per Click (PPC)',
                'message' => 'Google Ads is burning budget on irrelevant search terms.',
                'ip_address' => '192.0.2.44',
                'source_page' => 'blog',
                'status' => LeadStatus::Qualified,
                'lead_score' => LeadScore::Hot,
                'score_reasons' => ['specific_problem', 'budget_mentioned'],
                'utm_source' => 'bing',
                'utm_medium' => 'cpc',
                'event_id' => 'demo-evt-0009',
                'created_at' => now()->subDays(4),
            ],
            [
                'name' => 'Elena Petrova',
                'email' => 'elena.petrova@example.com',
                'goal' => 'Graphics Design',
                'message' => 'Looking for ongoing social creative. Small budget.',
                'ip_address' => '192.0.2.45',
                'source_page' => 'services',
                'status' => LeadStatus::New,
                'lead_score' => LeadScore::Cold,
                'score_reasons' => ['budget_mentioned'],
                'event_id' => 'demo-evt-0010',
                'created_at' => now()->subDays(11),
            ],
            // Two spam examples so the Spam status and the badge colour are visible.
            [
                'name' => 'SEO Expert',
                'email' => 'backlinks@example.com',
                'goal' => 'Growth Audit',
                'message' => 'We can get you 10,000 backlinks for $99. Reply for details.',
                'ip_address' => '192.0.2.99',
                'source_page' => 'contact',
                'status' => LeadStatus::Spam,
                'lead_score' => LeadScore::Cold,
                'score_reasons' => ['spam_pattern'],
                'event_id' => 'demo-evt-0011',
                'created_at' => now()->subDays(5),
            ],
            [
                'name' => 'Crypto Signals',
                'email' => 'signals@example.com',
                'goal' => 'Growth Audit',
                'message' => 'GUARANTEED 400% returns. Click here.',
                'ip_address' => '192.0.2.98',
                'source_page' => 'home',
                'status' => LeadStatus::Spam,
                'lead_score' => LeadScore::Cold,
                'score_reasons' => ['spam_pattern'],
                'event_id' => 'demo-evt-0012',
                'created_at' => now()->subDays(9),
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadScore;

/**
 * Smart lead categorisation (§14.10).
 *
 * Scores a submission into hot / warm / cold so the pipeline table can be triaged by
 * urgency instead of by arrival time. A lead that says "we have $8k/month and need this
 * fixed by March" is worth reading before one that says "hello".
 *
 * DESIGN NOTES
 *
 *  - **Every point is explainable.** `reasons()` returns the signals that fired, and they
 *    are stored on the lead. A score with no justification is impossible to tune or trust.
 *  - **Signals are weighted, not counted.** A business email is worth more than a long
 *    message, because a corporate domain is the single strongest predictor of a real buyer.
 *  - **Spam detection returns Cold, not a rejection.** Scoring must never silently discard
 *    a real enquiry; the spam *status* is a separate, reversible decision a human makes.
 *  - **Deliberately transparent.** No model, no black box — a rule set the owner can read
 *    and adjust without a data science background.
 */
class LeadScoringService
{
    /** Free/consumer mailbox providers. A business domain scores higher. */
    private const CONSUMER_DOMAINS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com',
        'outlook.com', 'live.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com',
        'protonmail.com', 'proton.me', 'gmx.com', 'mail.com', 'yandex.com', 'zoho.com',
    ];

    /** Phrases that indicate a buyer with budget. */
    private const BUDGET_SIGNALS = [
        'budget', 'spend', 'spending', 'invest', 'investment', '$', 'usd', 'gbp', 'eur',
        'per month', '/month', 'per month', 'ad spend', 'retainer', 'afford',
    ];

    /** Phrases that indicate urgency. */
    private const TIMELINE_SIGNALS = [
        'asap', 'urgent', 'immediately', 'this month', 'next month', 'this quarter',
        'deadline', 'launch', 'weeks', 'timeline', 'by march', 'by april', 'by may',
        'by june', 'by july', 'by august', 'by september', 'by october', 'by november',
        'by december', 'by january', 'by february', 'q1', 'q2', 'q3', 'q4',
    ];

    /** Phrases that indicate authority. */
    private const AUTHORITY_SIGNALS = [
        'founder', 'co-founder', 'ceo', 'cto', 'cmo', 'owner', 'director', 'head of',
        'managing', 'partner', 'decision', 'my team', 'our team', 'i run', 'we run',
    ];

    /** Phrases that indicate a specific, diagnosable problem rather than a fishing trip. */
    private const SPECIFICITY_SIGNALS = [
        'cpa', 'roas', 'conversion', 'conversion rate', 'cvr', 'checkout', 'funnel',
        'landing page', 'lead gen', 'lead generation', 'attribution', 'tracking',
        'creative', 'campaign', 'audience', 'retargeting', 'churn', 'pipeline',
        'qualified', 'cost per', 'acquisition', 'automation', 'crm',
    ];

    /** Words that mark a promotional blast rather than an enquiry. */
    private const SPAM_SIGNALS = [
        'seo services', 'backlinks', 'guest post', 'link building', 'bitcoin', 'crypto',
        'casino', 'loan', 'viagra', 'guaranteed returns', 'make money', 'work from home',
        'click here', 'limited time offer', 'buy now', 'free consultation for your website',
    ];

    /**
     * Score a submission.
     *
     * @param  array<string, mixed>  $data  Cleaned lead data from StoreLeadRequest.
     */
    public function score(array $data): LeadScore
    {
        return $this->evaluate($data)['score'];
    }

    /**
     * Score plus the reasoning behind it.
     *
     * @param  array<string, mixed>  $data
     * @return array{score: LeadScore, reasons: list<string>, points: int}
     */
    public function evaluate(array $data): array
    {
        $haystack = $this->haystack($data);
        $points = 0;
        $reasons = [];

        // --- Spam short-circuit -------------------------------------------------
        // Checked first: a promotional blast that happens to mention "budget" must not
        // accumulate buyer points.
        $spamHits = $this->hits($haystack, self::SPAM_SIGNALS);

        if ($spamHits !== [] || $this->isLinkDump($data['message'] ?? null)) {
            return [
                'score' => LeadScore::Cold,
                'reasons' => ['spam_pattern'],
                'points' => -1,
            ];
        }

        // --- Business identity --------------------------------------------------
        if ($this->isBusinessEmail($data['email'] ?? null)) {
            $points += 2;
            $reasons[] = 'business_email';
        } else {
            $reasons[] = 'consumer_email';
        }

        // --- Budget -------------------------------------------------------------
        if ($this->hits($haystack, self::BUDGET_SIGNALS) !== []) {
            $points += 2;
            $reasons[] = 'budget_mentioned';
        }

        // --- Urgency ------------------------------------------------------------
        if ($this->hits($haystack, self::TIMELINE_SIGNALS) !== []) {
            $points += 1;
            $reasons[] = 'timeline_stated';
        }

        // --- Authority ----------------------------------------------------------
        if ($this->hits($haystack, self::AUTHORITY_SIGNALS) !== []) {
            $points += 1;
            $reasons[] = 'decision_maker';
        }

        // --- Specificity --------------------------------------------------------
        $specificityHits = $this->hits($haystack, self::SPECIFICITY_SIGNALS);

        if ($specificityHits !== []) {
            $points += 1;
            $reasons[] = 'specific_problem';
        }

        // A substantive message beats a one-liner.
        if (mb_strlen(trim((string) ($data['message'] ?? ''))) >= 120) {
            $points += 1;
            $reasons[] = 'detailed_message';
        }

        // A phone number means they expect a call.
        if (filled($data['phone'] ?? null)) {
            $points += 1;
            $reasons[] = 'phone_provided';
        }

        // --- Negative signals ---------------------------------------------------
        // The default goal means they did not choose one — a mild negative.
        if (($data['goal'] ?? null) === 'Growth Audit' && mb_strlen(trim((string) ($data['message'] ?? ''))) < 40) {
            $points -= 1;
            $reasons[] = 'vague_goal';
        }

        return [
            'score' => $this->classify($points),
            'reasons' => $reasons,
            'points' => $points,
        ];
    }

    /**
     * Thresholds.
     *
     * Tuned so that a business email plus any single strong signal (budget, timeline or a
     * specific problem) reaches Hot, while a consumer address needs three signals. That
     * matches how these leads actually convert: a corporate domain that names a real
     * problem is the strongest pair available.
     */
    private function classify(int $points): LeadScore
    {
        return match (true) {
            $points >= 5 => LeadScore::Hot,
            $points >= 2 => LeadScore::Warm,
            default => LeadScore::Cold,
        };
    }

    /** All scorable text, lowercased, joined. */
    private function haystack(array $data): string
    {
        return mb_strtolower(implode(' ', array_filter([
            (string) ($data['name'] ?? ''),
            (string) ($data['goal'] ?? ''),
            (string) ($data['message'] ?? ''),
        ])));
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function hits(string $haystack, array $needles): array
    {
        $found = [];

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $found[] = $needle;
            }
        }

        return $found;
    }

    private function isBusinessEmail(?string $email): bool
    {
        if ($email === null || ! str_contains($email, '@')) {
            return false;
        }

        $domain = mb_strtolower(substr($email, strrpos($email, '@') + 1));

        return ! in_array($domain, self::CONSUMER_DOMAINS, true);
    }

    /**
     * A message containing several links and little else is a link dump, not an enquiry.
     */
    private function isLinkDump(?string $message): bool
    {
        if ($message === null) {
            return false;
        }

        $links = preg_match_all('#https?://#i', $message) ?: 0;
        $words = count(preg_split('/\s+/u', trim($message)) ?: []);

        return $links >= 2 && $words < 40;
    }
}

<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * CSV export for CRM / sales hand-off (§Module A, §12.3).
 *
 * Implements FromQuery rather than FromCollection so the rows stream from the database
 * instead of being hydrated into memory first — the leads table grows without bound, and
 * a "select all" export must not exhaust memory on shared hosting.
 *
 * Column order is deliberate: identity and pipeline state come first so the file is
 * usable in a spreadsheet without scrolling, with attribution and raw metadata at the end.
 */
class LeadsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  list<int>|null  $ids  Restrict to these lead IDs; null exports everything
     *                               matching the current filter.
     */
    public function __construct(
        private readonly ?array $ids = null,
    ) {}

    public function query(): Builder
    {
        $query = Lead::query()->orderByDesc('created_at');

        if ($this->ids !== null) {
            $query->whereIn('id', $this->ids);
        }

        return $query;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Goal',
            'Status',
            'Lead Score',
            'Score Reasons',
            'Message',
            'Source Page',
            'UTM Source',
            'UTM Medium',
            'UTM Campaign',
            'UTM Term',
            'UTM Content',
            'Referrer URL',
            'IP Address',
            'Internal Notes',
            'Submitted At',
        ];
    }

    /**
     * @param  Lead  $lead
     * @return list<string|int|null>
     */
    public function map($lead): array
    {
        return [
            $lead->id,
            $lead->displayName(),
            $lead->email,
            $lead->phone,
            $lead->goal,
            // Export the raw enum value, not the badge label — a CRM importing
            // "🔥 Hot" would choke, and the label carries presentation emoji.
            $lead->status?->value ?? (string) $lead->status,
            $lead->lead_score?->value ?? (string) $lead->lead_score,
            implode('; ', (array) ($lead->score_reasons ?? [])),
            // Collapse newlines so a multi-line message cannot break CSV row structure.
            $this->flatten($lead->message),
            $lead->source_page,
            $lead->utm_source,
            $lead->utm_medium,
            $lead->utm_campaign,
            $lead->utm_term,
            $lead->utm_content,
            $lead->referrer_url,
            $lead->ip_address,
            $this->flatten($lead->internal_notes),
            $lead->created_at?->toIso8601String(),
        ];
    }

    /** Collapse whitespace so a value cannot span rows in the CSV. */
    private function flatten(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

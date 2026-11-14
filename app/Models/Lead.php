<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadScore;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An inbound enquiry from the public site (§Module A).
 *
 * Leads are never hard-deleted: `SoftDeletes` plus the Trash tab in Filament means a
 * mis-clicked "delete" is always recoverable, which matters because these rows are the
 * agency's sales pipeline.
 */
class Lead extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'goal',
        'message',
        'ip_address',
        'source_page',
        'status',
        'internal_notes',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer_url',
        'fbc',
        'fbp',
        'lead_score',
        'score_reasons',
        'event_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'lead_score' => LeadScore::class,
            'score_reasons' => 'array',
            // Deliberately NOT in `$fillable`: these are set by the panel action and the
            // reminder command after a successful send, never by anything that accepts
            // request input.
            'qualified_invite_sent_at' => 'datetime',
            'booking_reminder_sent_at' => 'datetime',
            'booked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // score_reasons is a JSON blob the scorer rewrites wholesale; logging it
            // would bloat activity_log without telling anyone anything new.
            ->logOnly(['name', 'email', 'phone', 'goal', 'message', 'status', 'lead_score', 'internal_notes', 'source_page'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /* ------------------------------------------------------------------ *
     * Scopes
     * ------------------------------------------------------------------ */

    /** Leads that still need a human to act — the default triage view. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', LeadStatus::openCases());
    }

    /** Hottest first, then newest — the order a salesperson wants to work in. */
    public function scopePrioritised(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE lead_score WHEN 'hot' THEN 1 WHEN 'warm' THEN 2 ELSE 3 END"
        )->orderByDesc('created_at');
    }

    public function scopeHot(Builder $query): Builder
    {
        return $query->where('lead_score', LeadScore::Hot);
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /** Display name with a graceful fallback for sparse submissions. */
    public function displayName(): string
    {
        return $this->name !== '' && $this->name !== null
            ? $this->name
            : 'Unknown sender';
    }

    /**
     * True when this lead arrived with campaign attribution attached.
     * Used by the dashboard to separate paid traffic from direct/organic.
     */
    public function hasAttribution(): bool
    {
        return filled($this->utm_source) || filled($this->utm_medium) || filled($this->utm_campaign);
    }
}

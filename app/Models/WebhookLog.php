<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Append-only log of outbound webhook dispatches (§14.6).
 *
 * The table has no `updated_at` — a dispatch record is written once and never edited —
 * so UPDATED_AT is disabled while `created_at` stays auto-managed.
 *
 * `Prunable` exists because this table grows without bound: every ISR purge, IndexNow
 * submission, Google notification, Telegram alert and Meta event writes a row. On a
 * shared-hosting disk quota that becomes a real problem within months.
 */
class WebhookLog extends Model
{
    use HasFactory;
    use Prunable;

    /** Append-only: disable the updated_at column while keeping created_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'event',
        'payload',
        'response_status',
        'response_body',
        'duration_ms',
        'attempts',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    /** Dispatches that did not land — anything non-2xx, including transport failures. */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('response_status')
                ->orWhere('response_status', '>=', 300);
        });
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function succeeded(): bool
    {
        return $this->response_status !== null
            && $this->response_status >= 200
            && $this->response_status < 300;
    }

    /**
     * Retention policy for `php artisan model:prune`.
     *
     * Failures are kept three times longer than successes. A successful dispatch has no
     * further diagnostic value after a month; a failure is the kind of thing that gets
     * investigated weeks later when someone notices content is not being reindexed.
     */
    public function prunable(): Builder
    {
        return static::query()->where(function (Builder $query): void {
            // Successful dispatches — 30 days.
            $query->where(function (Builder $successful): void {
                $successful->whereBetween('response_status', [200, 299])
                    ->where('created_at', '<', now()->subDays(30));
            })
                // Failures and transport errors (no status at all) — 90 days.
                ->orWhere(function (Builder $failed): void {
                    $failed->where(function (Builder $notOk): void {
                        $notOk->whereNull('response_status')
                            ->orWhere('response_status', '>=', 300);
                    })->where('created_at', '<', now()->subDays(90));
                });
        });
    }

    /** Write a dispatch record without ever throwing into the caller's request path. */
    public static function record(
        string $event,
        ?array $payload = null,
        ?int $status = null,
        ?string $body = null,
        ?int $durationMs = null,
        int $attempts = 1,
    ): self {
        return static::query()->create([
            'event' => $event,
            'payload' => $payload,
            'response_status' => $status,
            // Keep the column small — response bodies can be large HTML error pages.
            'response_body' => $body !== null ? mb_substr($body, 0, 1000) : null,
            'duration_ms' => $durationMs,
            'attempts' => $attempts,
            'created_at' => now(),
        ]);
    }
}

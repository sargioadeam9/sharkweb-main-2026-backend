<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Newsletter subscriber, synced to Brevo (§Module G). */
class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'source_page',
        'is_verified',
        'brevo_synced',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'brevo_synced' => 'boolean',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /** Currently subscribed: opted in and not since unsubscribed. */
    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }

    /** Subscribers that still need pushing to Brevo. */
    public function scopePendingSync(Builder $query): Builder
    {
        return $query->subscribed()->where('brevo_synced', false);
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Concerns;

/**
 * The hashing rule shared by every server-side conversion sender.
 *
 * WHY THIS IS A TRAIT RATHER THAN TWO SETS OF PRIVATE METHODS. Meta and TikTok both require
 * SHA-256 hashed user data, and both require it hashed the SAME WAY: trim, lowercase, hash.
 * `John@Example.com ` and `john@example.com` have to produce one value or the match rate
 * collapses. Two senders written months apart will drift on that rule unless there is one
 * implementation to drift from — and the failure is invisible, it just quietly lowers the
 * match quality score.
 */
trait HashesUserData
{
    /**
     * Hash a value the way the ad platforms require: trim, lowercase, then SHA-256.
     *
     * Returns null for empty input so the key is dropped from the payload entirely —
     * sending `hash('')` would produce a valid-looking hash that matches nothing.
     */
    protected function hash(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalised = mb_strtolower(trim($value));

        return $normalised === '' ? null : hash('sha256', $normalised);
    }

    protected function firstName(?string $name): ?string
    {
        $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];

        return $parts[0] ?? null;
    }

    protected function lastName(?string $name): ?string
    {
        $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];

        return count($parts) > 1 ? end($parts) : null;
    }
}

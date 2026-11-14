<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an inbound order (§Shop).
 *
 * The frontend validates the same three fields with Zod, but that check happens in the
 * Next.js process — a different machine on a different platform. This is the
 * authoritative one.
 *
 * THE PAYLOAD CARRIES SLUGS AND A BUYER, NOTHING ELSE.
 * There is deliberately no `price`, no `subtotal` and no `total` field. If a caller sends
 * them they are simply not validated and not read: OrderService reads every number from
 * the database. Accepting a client-supplied total is how shops get bought for one taka.
 *
 * Email and phone are both required. Email because these are digital products and the
 * download link has to go somewhere; phone because bKash / Nagad / Rocket reconcile a
 * payment against the number that sent it, and an order with no number cannot be matched
 * to a transaction.
 */
class StoreOrderRequest extends FormRequest
{
    /** The route is behind `api.key`, so authorisation is already settled. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],

            // Bangladeshi numbers arrive as +8801XXXXXXXXX, 01XXXXXXXXX or with spaces.
            // The regex allows digits, spaces, dashes and an optional leading +; the
            // service layer normalises. Rejecting a valid number is worse than accepting
            // a sloppy one here, because the buyer simply cannot complete the order.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-]{5,19}$/'],

            // One slug per product. Digital goods are single-licence, so there is no
            // quantity to validate — OrderService always writes quantity 1.
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string', 'max:180'],

            // The visitor's IP, forwarded by the Next.js action. Validated as an IP so a
            // caller cannot store arbitrary text in an indexed column.
            'ip_address' => ['nullable', 'ip'],

            /*
             * Attribution (§14.1 / §14.4). Optional throughout — most orders come from no
             * campaign at all, and a missing value must stay null rather than becoming an
             * empty string, because "we never captured this" and "we captured a blank" are
             * different facts.
             *
             * Length-capped and free of `url`/`email` style rules on purpose: these arrive
             * from the visitor's own address bar, so the only job here is to make sure a
             * caller cannot park arbitrary text in a column the admin panel renders.
             */
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_term' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],

            // Meta's ad click id. Long in practice — the value is an opaque token — and it is
            // the single most valuable field here: without it Meta cannot tie the Purchase
            // back to the ad that produced it.
            'fbclid' => ['nullable', 'string', 'max:255'],

            // Meta's browser id, from the pixel's own `_fbp` cookie.
            'fbp' => ['nullable', 'string', 'max:100'],

            // Both are PATHS, not URLs. Validated as paths so a caller cannot smuggle a full
            // off-site URL into a column that is later turned into `event_source_url`.
            'landing_path' => ['nullable', 'string', 'max:180', 'starts_with:/'],
            'source_page' => ['nullable', 'string', 'max:180', 'starts_with:/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'name.min' => 'Please enter your full name.',
            'email.required' => 'We need an email address to send your products to.',
            'email.email' => 'That email address does not look valid.',
            'phone.required' => 'A phone number is required for mobile payments.',
            'phone.regex' => 'That phone number does not look valid. Use a format like 01712345678.',
            'items.required' => 'Your order is empty.',
            'items.min' => 'Your order is empty.',
        ];
    }

    /**
     * Cleaned input, ready for the service layer.
     *
     * @return array<string, mixed>
     */
    public function orderData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $this->clean($validated['name'] ?? '') ?? '',
            'email' => strtolower(trim($validated['email'] ?? '')),
            'phone' => $this->normalisePhone($validated['phone'] ?? ''),
            'items' => array_values(array_filter(array_map(
                fn ($slug) => $this->clean((string) $slug),
                $validated['items'] ?? [],
            ))),
            'ip_address' => $validated['ip_address'] ?? $this->ip(),
            ...$this->attribution($validated),
        ];
    }

    /**
     * The attribution block, with every blank collapsed to null.
     *
     * A missing campaign is a fact and has to be stored as NULL; an empty string would claim we
     * recorded one and it was blank. `clean()` already does that collapse, so every value goes
     * through it rather than being trusted straight off the wire.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string|null>
     */
    private function attribution(array $validated): array
    {
        $keys = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'fbp', 'landing_path', 'source_page',
        ];

        $attribution = [];

        foreach ($keys as $key) {
            $attribution[$key] = $this->clean(
                isset($validated[$key]) ? (string) $validated[$key] : null,
            );
        }

        return $attribution;
    }

    /**
     * Strip tags and control characters, collapse runaway whitespace.
     *
     * `strip_tags` rather than `htmlspecialchars` — these values are stored and later
     * rendered in the admin panel, which escapes on output. Escaping here would
     * double-encode them.
     */
    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stripped = strip_tags($value);
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $stripped) ?? $stripped;
        $trimmed = trim($stripped);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalise a phone number to digits, keeping a leading +.
     *
     * Gateway APIs are inconsistent about spaces and dashes, and a number that fails
     * their format check cannot be refunded to. Storing one canonical shape means the
     * sandbox integrations do not each have to sanitise it.
     */
    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';

        return str_starts_with($digits, '+')
            ? '+'.preg_replace('/[^0-9]/', '', substr($digits, 1))
            : $digits;
    }
}

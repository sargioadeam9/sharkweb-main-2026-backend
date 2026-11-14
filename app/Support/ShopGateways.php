<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Send Money receiving numbers, in one place.
 *
 * These are the client's real wallet numbers: buyers send money to them and type the TrxID
 * back into our checkout. A wrong number means money arrives in the wrong wallet, so the
 * values live in config (`sharkweb.shop.gateways`) and this class is the only thing that
 * reads that config — the API, the admin panel and the checkout page all go through here, so
 * they cannot disagree about which number to display.
 *
 * Deliberately not an enum. The `orders.gateway` column also stores `manual` for orders an
 * operator released by hand, and a historical order may name a gateway we have since
 * removed; an enum cast would make those rows unreadable.
 */
final class ShopGateways
{
    /**
     * Every configured gateway.
     *
     * @return array<string, array{label: string, account_type: string, number: string, hotline: string}>
     */
    public static function all(): array
    {
        /** @var array<string, array{label: string, account_type: string, number: string, hotline: string}> $gateways */
        $gateways = config('sharkweb.shop.gateways', []);

        return $gateways;
    }

    /** Whether a key names a gateway we currently accept money through. */
    public static function supports(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * The receiving number for a gateway, or null when it is not configured.
     *
     * Returns null rather than an empty string so a caller cannot accidentally render a
     * blank number and have a buyer send money into the void.
     */
    public static function number(string $key): ?string
    {
        $number = self::all()[$key]['number'] ?? null;

        return filled($number) ? (string) $number : null;
    }

    /**
     * Label => key, for the Filament selects and the checkout chooser.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $key => $gateway) {
            $options[$key] = $gateway['label'];
        }

        return $options;
    }

    /**
     * The shape the frontend consumes.
     *
     * The receiving number is included because the checkout page has to display it — the
     * buyer cannot send money without it. It is not a secret; it is printed on the screen.
     *
     * @return array<int, array{key: string, label: string, accountType: string, number: string, hotline: string}>
     */
    public static function forFrontend(): array
    {
        $gateways = [];

        foreach (self::all() as $key => $gateway) {
            $gateways[] = [
                'key' => (string) $key,
                'label' => (string) ($gateway['label'] ?? ucfirst((string) $key)),
                'accountType' => (string) ($gateway['account_type'] ?? 'Personal'),
                'number' => (string) ($gateway['number'] ?? ''),
                'hotline' => (string) ($gateway['hotline'] ?? ''),
            ];
        }

        return $gateways;
    }

    /** How long the buyer's verification window stays open, in seconds. */
    public static function verificationWindowSeconds(): int
    {
        return max(1, (int) config('sharkweb.shop.verification_window_minutes', 15)) * 60;
    }
}

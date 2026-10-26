<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;

/**
 * Global site settings (§Module D).
 *
 * Serves the Spatie settings class: contact details, social links, SEO defaults, footer
 * and the support block. The frontend renders its header and footer on every page from
 * this one call.
 *
 * The `cta` block that used to ride along is gone (2026-10-23). Nothing rendered it: the
 * frontend's `fetchSettings()` drops it, the contact form's labels are hardcoded, and the
 * Filament screen that edited it went with the rest of the unused panel.
 */
class SettingController
{
    public function __invoke(GeneralSettings $settings): JsonResponse
    {
        return response()->json([
            'settings' => $settings->toFrontendArray(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNewsletterRequest;
use App\Models\NewsletterSubscriber;
use App\Services\BrevoNewsletterService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Newsletter sign-up (§Module G).
 *
 * Persist locally first, then attempt the Brevo sync. The response does not depend on Brevo
 * succeeding — a visitor who gave us their address should not see an error because a third
 * party is having a bad day. A failed sync leaves `brevo_synced = false` for a later retry.
 */
class NewsletterController
{
    public function __construct(
        private readonly BrevoNewsletterService $brevo,
    ) {}

    public function store(StoreNewsletterRequest $request): JsonResponse
    {
        if ($request->isHoneypotTripped()) {
            return $this->accepted();
        }

        try {
            $subscriber = NewsletterSubscriber::updateOrCreate(
                ['email' => $request->normalisedEmail()],
                [
                    'source_page' => $request->validated()['source_page'] ?? null,
                    'subscribed_at' => now(),
                    // Re-subscribing clears a previous unsubscribe.
                    'unsubscribed_at' => null,
                ],
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'submission_failed',
                'message' => 'We could not save your sign-up. Please try again shortly.',
            ], 500);
        }

        $this->brevo->sync($subscriber);

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => "You are on the list. You'll get the next issue in your inbox.",
        ], 201);
    }
}

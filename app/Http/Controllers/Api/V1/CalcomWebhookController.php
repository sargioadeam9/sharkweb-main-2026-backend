<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Lead;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cal.com webhook receiver (A Module A / Pipeline integration).
 *
 * When an invited lead schedules their 30-minute discovery call on Cal.com,
 * Cal.com posts the event here. We match the attendee's email to a lead and
 * stamp `leads.booked_at`.
 *
 * SAFETY & ACCESS:
 *  - Excluded from the frontend API key middleware so Cal.com can reach it.
 *  - Authenticated via shared secret: `X-Cal-Signature-256` HMAC or `X-Calcom-Secret`.
 *  - Fails closed: if CALCOM_WEBHOOK_SECRET is unconfigured, refuses with 503.
 *  - Stamping `booked_at` stops `leads:booking-reminder` from nudging people who already booked.
 */
class CalcomWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('sharkweb.calcom.webhook_secret');

        if ($secret === '') {
            return response()->json([
                'success' => false,
                'error' => 'unconfigured',
                'message' => 'Cal.com webhook secret is not configured.',
            ], 503);
        }

        $signature = (string) ($request->header('X-Cal-Signature-256') ?? $request->header('X-Calcom-Signature') ?? '');
        $directSecret = (string) ($request->header('X-Calcom-Secret') ?? $request->header('X-Webhook-Secret') ?? $request->input('secret') ?? '');
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        $validSignature = $signature !== '' && hash_equals($expectedSignature, $signature);
        $validDirect = $directSecret !== '' && hash_equals($secret, $directSecret);

        if (! $validSignature && ! $validDirect) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_signature',
                'message' => 'The webhook signature or secret is invalid.',
            ], 401);
        }

        $emails = $this->extractAttendeeEmails($request);
        $lead = null;

        foreach (array_unique($emails) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $lead = Lead::whereRaw('LOWER(email) = ?', [$email])
                    ->orderByDesc('id')
                    ->first();

                if ($lead !== null) {
                    break;
                }
            }
        }

        if ($lead !== null) {
            $lead->booked_at = now();
            $lead->save();

            try {
                WebhookLog::record(
                    event: 'calcom.booking',
                    payload: ['lead_id' => $lead->id, 'email' => $lead->email],
                    status: 200,
                    body: 'Lead booked_at updated.',
                    durationMs: 0,
                );
            } catch (\Throwable) {
                // Best-effort audit logging.
            }

            return response()->json([
                'success' => true,
                'matched' => true,
                'lead_id' => $lead->id,
            ], 200);
        }

        try {
            WebhookLog::record(
                event: 'calcom.booking',
                payload: ['emails' => $emails],
                status: 200,
                body: 'No matching lead found for booking.',
                durationMs: 0,
            );
        } catch (\Throwable) {
            // Best-effort audit logging.
        }

        return response()->json([
            'success' => true,
            'matched' => false,
            'message' => 'No matching lead found for booking attendees.',
        ], 200);
    }

    /**
     * @return array<string>
     */
    private function extractAttendeeEmails(Request $request): array
    {
        $emails = [];
        $data = $request->json()->all();
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : $data;

        // 1. Attendees array: attendees[*].email
        if (isset($payload['attendees']) && is_array($payload['attendees'])) {
            foreach ($payload['attendees'] as $attendee) {
                if (is_array($attendee) && ! empty($attendee['email']) && is_string($attendee['email'])) {
                    $emails[] = mb_strtolower(trim($attendee['email']));
                }
            }
        }

        // 2. Booking responses: responses.email.value or responses.email
        if (isset($payload['responses']['email']['value']) && is_string($payload['responses']['email']['value'])) {
            $emails[] = mb_strtolower(trim($payload['responses']['email']['value']));
        } elseif (isset($payload['responses']['email']) && is_string($payload['responses']['email'])) {
            $emails[] = mb_strtolower(trim($payload['responses']['email']));
        }

        // 3. Direct email field in payload
        if (! empty($payload['email']) && is_string($payload['email'])) {
            $emails[] = mb_strtolower(trim($payload['email']));
        }

        return array_filter($emails);
    }
}
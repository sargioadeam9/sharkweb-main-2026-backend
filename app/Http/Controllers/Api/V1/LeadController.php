<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreLeadRequest;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Lead intake (§Module A).
 *
 * As thin as a controller gets: validate (via the FormRequest), delegate, respond. All
 * scoring, alerting and tracking lives in LeadService.
 *
 * THE HONEYPOT RESPONSE IS A LIE, ON PURPOSE.
 * When the hidden `website` field is filled the submission is dropped, but the caller still
 * receives a normal 201 with a success message. Returning 422 or "bot detected" would tell
 * an automated caller exactly which field tripped it, and the next attempt would leave it
 * empty. Silence is the only response that keeps the trap working.
 */
class LeadController
{
    public function __construct(
        private readonly LeadService $leads,
    ) {}

    public function store(StoreLeadRequest $request): JsonResponse
    {
        // Honeypot: acknowledge without persisting. Identical response shape to a real
        // submission so the two are indistinguishable from the outside.
        if ($request->isHoneypotTripped()) {
            return $this->accepted(null);
        }

        try {
            $lead = $this->leads->submit($request->leadData());
        } catch (Throwable $e) {
            report($e);

            // The visitor's message must not be lost silently. The frontend surfaces this
            // message and falls back to the direct email address.
            return response()->json([
                'success' => false,
                'error' => 'submission_failed',
                'message' => 'Something went wrong on our side. Please email info@thesharkweb.com directly — we reply within 24 hours.',
            ], 500);
        }

        return $this->accepted($lead);
    }

    /**
     * The confirmation payload.
     *
     * Only the id and the event id are returned — never the stored record. Echoing the
     * submitted data back would let a caller confirm exactly what was persisted.
     *
     * WHY `event_id` IS RETURNED. It is what lets the browser pixel and the server report
     * the SAME conversion. Meta deduplicates on it: send `Lead` from `fbq()` with this id
     * and send the same id again from the Conversions API, and the two collapse into one
     * event instead of inflating the count. Without it the browser would have to invent its
     * own id, which the server never sees, and every lead would be counted twice the moment
     * server-side tracking is switched on.
     */
    private function accepted(?Lead $lead): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your request was received. We will review your bottleneck and reply within 24 hours.',
            'lead_id' => $lead?->id,
            'event_id' => $lead?->event_id,
        ], 201);
    }
}

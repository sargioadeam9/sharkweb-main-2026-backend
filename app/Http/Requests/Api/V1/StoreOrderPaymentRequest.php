<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\ShopGateways;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The TrxID a buyer submits after sending money.
 *
 * WHAT THIS VALIDATES, AND WHAT IT CANNOT.
 * It checks that the values are well formed: a gateway we accept, a phone number, and a
 * transaction id of the right shape. It cannot check that the transaction exists — that
 * requires the wallet ledger, which is a human with the bKash app open. This request is
 * therefore a well-formed claim, and the order stays `pending` until an operator approves it.
 *
 * WHY THE TRX ID IS SHAPE-CHECKED SO TIGHTLY.
 * A TrxID is an 8–12 character alphanumeric string from an SMS. Accepting free text here
 * would let a buyer paste a paragraph, and the operator would have to read it to discover it
 * is not a transaction id. Rejecting it at the door keeps the reconciliation queue readable.
 */
class StoreOrderPaymentRequest extends FormRequest
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
            'gateway' => [
                'required',
                'string',
                // Checked against config rather than a hard-coded list, so removing a
                // gateway is a config change and cannot leave the validator accepting one
                // the shop no longer displays.
                Rule::in(array_keys(ShopGateways::all())),
            ],

            'sender_number' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-]{5,19}$/'],

            'trx_id' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gateway.required' => 'Please choose how you sent the payment.',
            'gateway.in' => 'We do not accept payments through that gateway.',
            'sender_number.required' => 'Please enter the number you sent the money from.',
            'sender_number.regex' => 'That phone number does not look valid. Use a format like 01712345678.',
            'trx_id.required' => 'Please enter the Transaction ID from your payment SMS.',
            'trx_id.regex' => 'A Transaction ID contains only letters, numbers and dashes.',
            'trx_id.min' => 'That Transaction ID looks too short. Please check your SMS.',
        ];
    }

    /** The gateway key, normalised to lower case so `bKash` and `bkash` agree. */
    public function gateway(): string
    {
        return strtolower(trim((string) $this->validated()['gateway']));
    }

    /** The sender's number, reduced to digits with an optional leading `+`. */
    public function senderNumber(): string
    {
        $digits = preg_replace('/[^0-9+]/', '', (string) $this->validated()['sender_number']) ?? '';

        return str_starts_with($digits, '+')
            ? '+'.preg_replace('/[^0-9]/', '', substr($digits, 1))
            : $digits;
    }

    /** The TrxID, upper-cased so the same transaction cannot be claimed twice by case. */
    public function trxId(): string
    {
        return strtoupper(trim((string) $this->validated()['trx_id']));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/** Validates a newsletter sign-up. */
class StoreNewsletterRequest extends FormRequest
{
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
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            'source_page' => ['nullable', 'string', 'max:50'],
            // Same honeypot as the lead form.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function isHoneypotTripped(): bool
    {
        return filled($this->input('website'));
    }

    public function normalisedEmail(): string
    {
        return Str::lower(trim((string) $this->validated()['email']));
    }
}

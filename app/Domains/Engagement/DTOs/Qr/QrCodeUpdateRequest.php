<?php

namespace App\Domains\Engagement\DTOs\Qr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QrCodeUpdateRequest extends FormRequest
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
            'campaign_id' => ['sometimes', 'nullable', 'integer', 'exists:campaigns,id'],
            'type' => ['sometimes', 'string', Rule::in(config('qr.types', []))],
            'status' => ['sometimes', 'string', Rule::in(['active', 'used', 'expired', 'revoked'])],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'url_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activated_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'expired_at' => ['sometimes', 'nullable', 'date'],
            'scanned_at' => ['sometimes', 'nullable', 'date'],
            'revoked_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}

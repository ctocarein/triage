<?php

namespace App\Domains\Engagement\Presentation\Http\Requests\Qr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QrCodeStoreRequest extends FormRequest
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
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'type' => ['nullable', 'string', Rule::in(config('qr.types', []))],
            'status' => ['nullable', 'string', Rule::in(['active', 'used', 'expired', 'revoked'])],
            'metadata' => ['nullable', 'array'],
            'url_template' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'activated_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
            'scanned_at' => ['nullable', 'date'],
            'revoked_at' => ['nullable', 'date'],
        ];
    }
}

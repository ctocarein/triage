<?php

namespace App\Domains\Engagement\DTOs\Qr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QrCodeIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in(['active', 'used', 'expired', 'revoked'])],
            'type' => ['sometimes', 'string', Rule::in(config('qr.types', []))],
            'campaign_id' => ['sometimes', 'integer', 'exists:campaigns,id'],
            'organization_id' => ['sometimes', 'uuid', 'exists:organizations,id'],
            'code' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

<?php

namespace App\Domains\Ambassador\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReferralRequest extends FormRequest
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
            'channel' => ['required', 'string', 'in:whatsapp,sms,qr,other'],
            'expires_at' => ['required', 'date'],
        ];
    }
}

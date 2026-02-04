<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnlockSecurityGateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->input('method');

        return [
            'method' => ['required', Rule::in(['pin', 'agent', 'biometric'])],
            'secret' => array_filter([
                $method === 'pin' ? 'required' : 'nullable',
                'string',
                $method === 'pin' ? 'min:4' : null,
                $method === 'pin' ? 'max:6' : null,
            ]),
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ];
    }
}

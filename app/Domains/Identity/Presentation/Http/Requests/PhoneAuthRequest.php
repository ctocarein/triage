<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhoneAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone'              => ['required', 'string', 'min:8', 'max:20'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
        ];
    }
}

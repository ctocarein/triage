<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('credential_type');

        return [
            'credential_type' => ['required', Rule::in(['email', 'phone'])],
            'identifier' => array_filter([
                'required',
                'string',
                $type === 'email' ? 'email' : null,
                $type === 'phone' ? 'min:8' : null,
                $type === 'phone' ? 'max:20' : null,
            ]),
            'secret' => array_filter([
                'required',
                'string',
                $type === 'phone' ? 'min:4' : null,
                $type === 'phone' ? 'max:6' : null,
                $type === 'email' ? 'min:6' : null,
            ]),
        ];
    }
}

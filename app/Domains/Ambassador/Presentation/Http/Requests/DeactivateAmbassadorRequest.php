<?php

namespace App\Domains\Ambassador\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateAmbassadorRequest extends FormRequest
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
            'reason' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:paused,exited'],
        ];
    }
}

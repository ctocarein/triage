<?php

namespace App\Domains\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'string', 'max:255'],
            'type'   => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'plan'   => ['sometimes', Rule::in(['Freemium', 'Premium', 'Entreprise'])],
        ];
    }
}

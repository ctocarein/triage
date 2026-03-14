<?php

namespace App\Domains\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'type'   => ['required', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'plan'   => ['sometimes', Rule::in(['Freemium', 'Premium', 'Entreprise'])],
        ];
    }
}

<?php

namespace App\Domains\Profile\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonalProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'language' => ['required', 'string', 'max:255'],
            'education_level' => ['required', 'string', 'in:none,primary,secondary,university'],
            'commune' => ['required', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Domains\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrgManagerOnboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'max:255'],
            'password'    => ['required', 'string', 'min:8'],
            'org_type'    => ['required', 'string', 'in:ONG,Entreprise'],
            'org_name'    => ['required', 'string', 'max:255'],
            'org_country' => ['required', 'string', 'max:100'],
            'org_city'    => ['required', 'string', 'max:100'],
            'org_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'       => 'L\'adresse email est obligatoire.',
            'email.email'          => 'L\'adresse email n\'est pas valide.',
            'password.required'    => 'Le mot de passe est obligatoire.',
            'password.min'         => 'Le mot de passe doit contenir au moins 8 caractères.',
            'org_type.required'    => 'Le type de structure est obligatoire.',
            'org_type.in'          => 'Le type doit être ONG ou Entreprise.',
            'org_name.required'    => 'Le nom de la structure est obligatoire.',
            'org_country.required' => 'Le pays est obligatoire.',
            'org_city.required'    => 'La ville est obligatoire.',
        ];
    }
}

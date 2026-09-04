<?php

namespace App\Domains\Evaluation\DTOs\Patient;

use Illuminate\Foundation\Http\FormRequest;

class EvaluationSessionStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $blocKeys = $this->input('bloc_keys');

        if (is_string($blocKeys)) {
            $blocKeys = array_filter(array_map('trim', explode(',', $blocKeys)));
        } elseif ($blocKeys !== null && ! is_array($blocKeys)) {
            $blocKeys = [$blocKeys];
        }

        $this->merge([
            'bloc_keys' => $blocKeys,
            'referral_code' => $this->input('referral_code')
                ?? $this->query('ref')
                ?? $this->cookie(config('ambassador.referrals.cookie_name', 'ambassador_referral')),
            'client_ip' => $this->ip(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:complete,thematic'],
            'bloc_keys' => ['nullable', 'array', 'required_if:type,thematic'],
            'bloc_keys.*' => ['string'],
            'referral_code' => ['nullable', 'string'],
            'client_ip' => ['nullable', 'ip'],
            'subject_id' => ['nullable', 'uuid', 'exists:identities,id'],
        ];
    }
}

<?php

namespace App\Domains\Evaluation\DTOs\Patient;

use Illuminate\Foundation\Http\FormRequest;

class UploadStoreRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}

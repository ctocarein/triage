<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use App\Domains\Evaluation\Models\QuestionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('group')?->id;

        return [
            'key'        => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('question_groups', 'key')->ignore($id),
            ],
            'label'      => ['required', 'string', 'max:255'],
            'description'=> ['nullable', 'string', 'max:1000'],
            'order'      => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
            'icon'       => ['nullable', 'string', 'max:100'],
            'color'      => ['nullable', 'string', 'max:50'],
            'parent_key' => [
                'nullable', 'string',
                Rule::exists('question_groups', 'key'),
            ],
            'conditions' => ['nullable', 'array'],
        ];
    }
}

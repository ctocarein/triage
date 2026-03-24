<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use App\Domains\Evaluation\Enums\ThematicBlocIntention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThematicBlocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('bloc')?->id;

        return [
            'key'               => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('thematic_blocs', 'key')->ignore($id),
            ],
            'label'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'order'             => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['boolean'],
            'icon'              => ['nullable', 'string', 'max:100'],
            'color'             => ['nullable', 'string', 'max:50'],
            'question_group_id'               => ['nullable', 'integer', 'exists:question_groups,id'],
            'intention'                       => ['nullable', Rule::enum(ThematicBlocIntention::class)],
            'priority'                        => ['nullable', 'integer', 'min:0'],
            'recurrence_policy'               => ['nullable', 'string', 'in:once,periodic,conditional'],
            'trigger_conditions'              => ['nullable', 'array'],
            'trigger_conditions.operator'     => ['required_with:trigger_conditions', 'string', 'in:AND,OR'],
            'trigger_conditions.conditions'   => ['required_with:trigger_conditions', 'array', 'min:1'],
            'trigger_conditions.conditions.*.source'   => ['required', 'string', 'in:answer,score,profile'],
            'trigger_conditions.conditions.*.operator' => ['required', 'string', 'in:=,!=,>,<,>=,<=,in,not_in'],
            'trigger_conditions.conditions.*.value'    => ['present'],
            'visible_if'             => ['nullable', 'array'],
            'visible_if.*.source'    => ['required_with:visible_if', 'string'],
            'visible_if.*.key'       => ['required_with:visible_if', 'string'],
            'visible_if.*.operator'  => ['required_with:visible_if', 'string', 'in:=,!=,>,<,>=,<=,in,not_in,empty,not_empty,contains,not_contains'],
            'visible_if.*.value'     => ['present'],
        ];
    }
}

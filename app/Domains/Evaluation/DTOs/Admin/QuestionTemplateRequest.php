<?php

namespace App\Domains\Evaluation\DTOs\Admin;

use App\Domains\Evaluation\Enums\QuestionType;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\ConditionContractValidator;
use App\Domains\Evaluation\Services\Catalog\RoutingRulesValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('question')?->id;

        return [
            'key'                => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('dynamic_question_templates', 'key')->ignore($id),
            ],
            'text'               => ['required', 'string', 'max:1000'],
            'type'               => ['required', Rule::enum(QuestionType::class)],
            'answer_target'      => ['nullable', Rule::in([
                'personal_profile', 'medical_profile', 'evaluation',
                'followup', 'tracking', 'survey', 'greeting', 'custom', 'consent',
                // 'credential' : la reponse alimente les identifiants de connexion
                // (numero de telephone), qui ne vivent pas dans personal_profiles.
                'credential',
            ])],
            'question_group_id'  => ['nullable', 'integer', 'exists:question_groups,id'],
            'bloc_key'           => ['nullable', 'string', 'max:100'],
            'order'              => ['nullable', 'integer', 'min:0'],
            'is_required'        => ['boolean'],
            'is_entry_point'     => ['boolean'],
            'is_active'          => ['boolean'],
            'single_choice'      => ['boolean'],
            'risk_weight'        => ['nullable', 'integer', 'min:0'],
            'min'                => ['nullable', 'numeric'],
            'max'                => ['nullable', 'numeric'],
            'label'              => ['nullable', 'string', 'max:255'],
            'placeholder'        => ['nullable', 'string', 'max:255'],
            'media_type'         => ['nullable', 'string', Rule::in(['image', 'video', 'audio'])],
            'media_url'          => ['nullable', 'url'],
            'options'            => ['nullable', 'array'],
            'options.*.value'    => ['required_with:options', 'string'],
            'options.*.label'    => ['required_with:options', 'string'],
            'meta'               => ['nullable', 'array'],
            'visible_if'         => ['nullable'],
            'routing_rules'      => ['nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateVisibleIf($validator);
            $this->validateRoutingRules($validator);
            $this->validateOrderUniqueness($validator);
        });
    }

    private function validateVisibleIf($validator): void
    {
        $visibleIf = $this->input('visible_if');
        if ($visibleIf === null) {
            return;
        }

        /** @var ConditionContractValidator $conditionValidator */
        $conditionValidator = app(ConditionContractValidator::class);

        $errors = $conditionValidator->validate($visibleIf, 'visible_if');
        foreach ($errors as $error) {
            $validator->errors()->add('visible_if', $error);
        }

        if ($errors !== []) {
            return; // structure invalide, inutile de valider les refs
        }

        $decoded = is_string($visibleIf) ? json_decode($visibleIf, true) : $visibleIf;
        $refs    = $conditionValidator->extractQuestionRefs($decoded ?? []);

        if ($refs !== []) {
            $id        = $this->route('question')?->id;
            $knownKeys = DynamicQuestionTemplate::when($id, fn ($q) => $q->where('id', '!=', $id))
                ->pluck('key')
                ->all();

            foreach ($refs as $ref) {
                if (!in_array($ref, $knownKeys, true)) {
                    $validator->errors()->add(
                        'visible_if',
                        "La question referencee '{$ref}' n'existe pas dans le catalogue."
                    );
                }
            }
        }
    }

    private function validateRoutingRules($validator): void
    {
        $rules = $this->input('routing_rules');
        if ($rules === null) {
            return;
        }

        $questionKey = $this->input('key', 'unknown');
        $knownKeys   = DynamicQuestionTemplate::pluck('key')->all();
        $knownBlocs  = DynamicQuestionTemplate::whereNotNull('bloc_key')->pluck('bloc_key')->unique()->all();

        $errors = app(RoutingRulesValidator::class)
            ->validateRoutingRules($rules, $knownKeys, $knownBlocs, $questionKey);

        foreach ($errors as $error) {
            $validator->errors()->add('routing_rules', $error);
        }
    }

    private function validateOrderUniqueness($validator): void
    {
        $order   = $this->input('order');
        $blocKey = $this->input('bloc_key');
        $id      = $this->route('question')?->id;

        if ($order === null || $blocKey === null) {
            return;
        }

        $exists = DynamicQuestionTemplate::where('bloc_key', $blocKey)
            ->where('order', $order)
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($exists) {
            $validator->errors()->add('order', "L'ordre {$order} est deja utilise dans le bloc '{$blocKey}'.");
        }
    }
}

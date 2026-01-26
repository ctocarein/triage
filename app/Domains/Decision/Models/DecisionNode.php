<?php

namespace App\Domains\Decision\Models;

use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Model;

class DecisionNode extends Model
{
    protected $fillable = [
        'decision_path_id',
        'node_key',
        'question_id',
        'order',
        'branches',
        'risk_weight',
        'node_type',
        'outcome_message',
        'specialties'
    ];

    protected $casts = [
        'branches' => 'array',
        'specialties' => 'array',
    ];

    public function path()
    {
        return $this->belongsTo(DecisionPath::class, 'decision_path_id');
    }

    public function question()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'question_id');
    }

    public function legacyQuestion()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'dynamic_question_template_id');
    }

    /**
     * Build a payload for either a question node or a terminal node.
     */
    public function getQuestionPayload()
    {
        $questionId = $this->question_id ?? $this->dynamic_question_template_id ?? null;

        if ($questionId) {
            $q = $this->question ?? $this->legacyQuestion;
            if (! $q) {
                return null;
            }

            return [
                'id'          => $q->id,
                'key'         => $q->key,
                'text'        => $q->text,
                'type'        => $q->type,
                'bloc_key'    => $q->bloc_key,
                'answer_target' => $q->answer_target,
                'order'       => $q->order,
                'risk_weight' => $q->risk_weight,
                'min'         => $q->min,
                'max'         => $q->max,
                'media_type'  => $q->media_type,
                'media_url'   => $q->media_url,
                'options'     => $q->options,
                'visible_if'  => $q->visible_if,
                'question_id' => $q->id,
                'node_id'     => $this->id,
            ];
        }

        if ($this->node_type === 'end' || $this->node_type === 'recommendation') {
            return [
                'node_id'        => $this->id,
                'node_type'      => $this->node_type,
                'outcome_message' => $this->outcome_message,
                'risk_weight'    => $this->risk_weight,
                'specialties'    => $this->specialties,
            ];
        }

        return [
            'node_id'   => $this->id,
            'node_type' => $this->node_type,
        ];
    }
}
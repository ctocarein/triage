<?php

namespace App\Models\Decisions;

use Illuminate\Database\Eloquent\Model;

class DecisionNode extends Model
{
    protected $fillable = [
        'decision_path_id', 'node_key', 'dynamic_question_template_id', 'order',
        'branches', 'risk_weight', 'node_type', 'outcome_message', 'specialties'
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
        return $this->belongsTo(DynamicQuestionTemplate::class, 'dynamic_question_template_id');
    }
}
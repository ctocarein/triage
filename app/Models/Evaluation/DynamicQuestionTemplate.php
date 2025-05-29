<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\EvaluationSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DynamicQuestionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'text', 'type', 'answer_target', 'question_group_id',
        'media_type', 'media_url', 'options', 'visible_if',
        'is_required', 'is_entry_point', 'order', 'risk_weight'
    ];

    protected $casts = [
        'visible_if' => 'array',
        'options' => 'array',
        'is_required' => 'boolean',
        'is_entry_point' => 'boolean',
    ];

    // Relations
    public function group() { return $this->belongsTo(QuestionGroup::class); }
    public function answers() { return $this->hasMany(EvaluationAnswer::class); }
    public function sessions() { return $this->belongsToMany(EvaluationSession::class, 'evaluation_answers'); }
}
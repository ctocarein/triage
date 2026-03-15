<?php

namespace App\Domains\Evaluation\Models;

use App\Domains\Evaluation\Enums\QuestionType;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\QuestionGroup;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DynamicQuestionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'text',
        'type',
        'answer_target',
        'question_group_id',
        'media_type',
        'media_url',
        'options',
        'visible_if',
        'is_required',
        'is_entry_point',
        'is_active',
        'order',
        'risk_weight',
        'bloc_key',         // <-- ajouté
        'min',              // <-- ajouté
        'max',              // <-- ajouté
        'meta', // <-- ajouté
        'label',           // <-- ajouté
        'placeholder',
        'routing_rules',
        'single_choice'     // <-- ajouté    
    ];

    protected $casts = [
        'type'         => QuestionType::class,
        'visible_if'   => 'array',
        'options'      => 'array',
        'meta'         => 'array',
        'routing_rules'=> 'array',
        'is_required'  => 'boolean',
        'is_entry_point' => 'boolean',
        'is_active'    => 'boolean',
    ];

    // Relations
    public function group()
    {
        return $this->belongsTo(QuestionGroup::class);
    }
    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class);
    }
    public function sessions()
    {
        return $this->belongsToMany(EvaluationSession::class, 'evaluation_answers');
    }

}

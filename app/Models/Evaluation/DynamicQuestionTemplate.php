<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Evaluation\QuestionGroup;
use App\Models\Medical\PossiblePathology;

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
        'order',
        'risk_weight',
    ];

    protected $casts = [
        'options' => 'array',
        'visible_if' => 'array',
        'is_required' => 'boolean',
        'is_entry_point' => 'boolean',
    ];

    // 🔁 Relation avec le groupe de questions (ex: symptomes_sein)
    public function group()
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    // 🔁 Relation avec les pathologies associées
    public function pathologies()
    {
        return $this->belongsToMany(PossiblePathology::class, 'pathology_symptom');
    }
}



<?php

namespace App\Domains\Evaluation\Models;

use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\QuestionGroup;

use App\Support\ComparesValues;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DynamicQuestionTemplate extends Model
{
    use HasFactory, ComparesValues;

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
        'visible_if' => 'array',
        'options' => 'array',
        'meta' => 'array',
        'routing_rules' => 'array',
        'is_required' => 'boolean',
        'is_entry_point' => 'boolean',
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

    public function isVisible(array $answers, array $profile): bool
    {
        if (!$this->visible_if) return true;

        foreach ($this->visible_if as $condition) {
            $source = $condition['source'] ?? 'question';
            $key = $condition['key'];
            $value = $condition['value'];
            $operator = $condition['operator'] ?? '=';

            $actual = match ($source) {
                'question' => $answers[$key] ?? null,
                'profile.medical' => $profile['medical'][$key] ?? null,
                'profile.personal' => $profile['personal'][$key] ?? null,
                default => null
            };

            if (!$this->compare($actual, $value, $operator)) {
                return false;
            }
        }

        return true;
    }
}

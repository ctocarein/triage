<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_session_id',
        'question_id',
        'dynamic_question_template_id',
        'value',
        'file_ids',
        'is_skipped',
        'order',
    ];

    protected $casts = [
        'value' => 'array',
        'file_ids' => 'array',
        'is_skipped' => 'boolean',
    ];

    // Relations
    public function session()
    {
        return $this->belongsTo(EvaluationSession::class, 'evaluation_session_id');
    }
    public function question()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'question_id');
    }


    // Helpers
    public function isEmpty(): bool
    {
        return $this->is_skipped || empty($this->value);
    }
}


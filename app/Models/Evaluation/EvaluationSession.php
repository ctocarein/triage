<?php

namespace App\Models\Evaluation;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id', 'status', 'context', 'score', 'started_at', 'completed_at'
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score' => 'integer',
    ];

    // Relations
    public function account() { return $this->belongsTo(Account::class); }
    public function answers() { return $this->hasMany(EvaluationAnswer::class); }
    public function report() { return $this->hasOne(Report::class); }
    public function questions() { return $this->belongsToMany(DynamicQuestionTemplate::class, 'evaluation_answers'); }
    public function currentQuestion(){ return $this->belongsTo(DynamicQuestionTemplate::class, 'current_question_id');}

    // Scopes
    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }
}
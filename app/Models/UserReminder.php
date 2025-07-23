<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation\DynamicQuestionTemplate;

class UserReminder extends Model
{
    protected $fillable = [
        'user_id',
        'dynamic_question_template_id',
        'reminded_at',
        'completed_at',
        'status',
    ];

    protected $dates = ['reminded_at', 'completed_at'];

    public function user() {
        return $this->belongsTo(Account::class, 'user_id');
    }

    public function reminder() {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'dynamic_question_template_id');
    }
}

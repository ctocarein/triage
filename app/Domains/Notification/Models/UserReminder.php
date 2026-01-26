<?php

namespace App\Domains\Notification\Models;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;

class UserReminder extends Model
{
    protected $fillable = [
        'identity_id',
        'question_id',
        'reminded_at',
        'completed_at',
        'status',
    ];

    protected $dates = ['reminded_at', 'completed_at'];

    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }

    public function reminder()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'question_id');
    }
}


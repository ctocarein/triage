<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FactMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_key',
        'answer_value',
        'operator',
        'fact_key',
        'is_active',
    ];

    protected $casts = [
        'answer_value' => 'array',
        'is_active' => 'boolean',
    ];

    public function fact()
    {
        return $this->belongsTo(Fact::class, 'fact_key', 'key');
    }
}
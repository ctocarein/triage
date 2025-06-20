<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThematicBloc extends Model
{
    protected $table = 'thematic_blocs';

    protected $fillable = [
        'key',
        'label',
        'color',
        'icon',
        'description',
        'is_active',
        'order',
        'question_group_id',
    ];

    public function questionGroup()
    {
        return $this->belongsTo(QuestionGroup::class);
    }

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }
}

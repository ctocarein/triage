<?php

namespace App\Domains\Evaluation\Models;


use Illuminate\Database\Eloquent\Model;
use App\Domains\Evaluation\Models\QuestionGroup;

class ThematicBloc extends Model
{
    protected $table = 'thematic_blocs';

    protected $fillable = [
        'key',
        'label',
        'color',
        'icon',
        'description',
        'visible_if',
        'is_active',
        'order',
        'question_group_id',
    ];

    protected $casts = [
        'visible_if' => 'array',
        'is_active'  => 'boolean',
    ];

    public function questionGroup()
    {
        return $this->belongsTo(QuestionGroup::class);
    }

    /**
     * Questions dont bloc_key correspond à la clé de ce bloc.
     * Relation via colonne string (pas de FK) — requête sur bloc_key.
     */
    public function questions()
    {
        return $this->hasMany(DynamicQuestionTemplate::class, 'bloc_key', 'key');
    }

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }
}

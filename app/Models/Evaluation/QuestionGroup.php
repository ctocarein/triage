<?php

namespace App\Models\Evaluation;

use App\Models\ThematicBloc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuestionGroup extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label','order','conditions','is_active', 'icon', 'color', 'description','parent_key'];

    // Relations
    public function questions() { return $this->hasMany(DynamicQuestionTemplate::class); }

    // Helpers
    public function getDisplayNameAttribute(): string {
        return $this->label ?? str_replace('_', ' ', $this->key);
    }

    public function thematicBlocs()
    {
        return $this->hasMany(ThematicBloc::class, 'question_group_id');
    }
}
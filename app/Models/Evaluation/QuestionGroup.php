<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuestionGroup extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label','order','conditions','is_active', 'icon', 'color', 'description'];

    // Relations
    public function questions() { return $this->hasMany(DynamicQuestionTemplate::class); }

    // Helpers
    public function getDisplayNameAttribute(): string {
        return $this->label ?? str_replace('_', ' ', $this->key);
    }
}
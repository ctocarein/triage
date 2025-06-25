<?php

namespace App\Models\Decisions;

use Illuminate\Database\Eloquent\Model;

class DecisionPath extends Model
{
    protected $fillable = [
        'key', 'title', 'description', 'category', 'tree',
        'source_guideline', 'version', 'is_active'
    ];

    protected $casts = [
        'tree' => 'array',
        'is_active' => 'boolean',
    ];

    public function nodes()
    {
        return $this->hasMany(DecisionNode::class)->orderBy('order');
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}

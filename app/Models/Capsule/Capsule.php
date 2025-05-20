<?php

namespace App\Models\Capsule;

use Illuminate\Database\Eloquent\Model;

class Capsule extends Model
{
       protected $fillable = [
        'title', 'slug', 'description',
        'target_age_min', 'target_age_max',
        'audience_profile', 'target_pathology',
        'target_stage', 'language',
        'sponsored_by', 'sponsor_logo_url', 'sponsor_priority',
        'max_displays', 'is_active', 'published_at',
    ];

    protected $casts = [
        'target_pathology' => 'array',
        'sponsor_priority' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(CapsuleItem::class)->orderBy('position');
    }
}

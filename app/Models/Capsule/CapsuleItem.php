<?php

namespace App\Models\Capsule;

use Illuminate\Database\Eloquent\Model;

class CapsuleItem extends Model
{
     protected $fillable = [
        'capsule_id',
        'position',
        'type',
        'bg_color',
        'content_text',
        'media_url',
        'cta_label',
        'cta_link',
        'sponsored_content',
        'sponsor_message',
        'sponsored_link_type',
        'sponsored_action_value',
        'duration_seconds',
        'audience_tags',
        'risk_level',
        'quiz_data',
    ];

    protected $casts = [
        'sponsored_content' => 'boolean',
        'audience_tags' => 'array',
        'quiz_data' => 'array',
        'duration_seconds' => 'integer',
    ];

    public function capsule()
    {
        return $this->belongsTo(Capsule::class);
    }
}

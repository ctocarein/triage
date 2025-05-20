<?php

namespace App\Models\CapsuleItem;

use Illuminate\Database\Eloquent\Model;

class CapsuleItem extends Model
{
      protected $fillable = [
        'capsule_id', 'position',
        'type', 'content_text', 'media_url',
        'cta_label', 'cta_link',
        'sponsored_content', 'sponsor_message',
        'sponsored_link_type', 'sponsored_action_value',
        'duration_seconds', 'audience_tags', 'risk_level'
    ];

    protected $casts = [
        'sponsored_content' => 'boolean',
        'audience_tags' => 'array',
    ];

    public function capsule()
    {
        return $this->belongsTo(Capsule::class);
    }
}

<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_session_id',
        'facts',
        'risk',
        'flags',
        'versions',
        'confidence_score',
        'snapshot_hash',
    ];

    protected $casts = [
        'facts' => 'array',
        'risk' => 'array',
        'flags' => 'array',
        'versions' => 'array',
        'confidence_score' => 'float',
    ];

    public function session()
    {
        return $this->belongsTo(EvaluationSession::class, 'evaluation_session_id');
    }
}

<?php

namespace App\Domains\Engagement\Models;

use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanEvent extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'qr_code_id',
        'identity_id',
        'scanned_at',
        'location',
        'device_info',
        'ip_address',
        'evaluation_session_id',
        'result',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'scanned_at' => 'datetime',
        'location' => 'array',
    ];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }

    public function evaluationSession(): BelongsTo
    {
        return $this->belongsTo(EvaluationSession::class);
    }
}

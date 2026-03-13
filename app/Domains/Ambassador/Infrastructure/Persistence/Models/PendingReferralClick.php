<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingReferralClick extends Model
{
    use HasUuids;

    protected $table = 'pending_referral_clicks';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'referral_id',
        'click_token',
        'device_fingerprint',
        'ip_address',
        'device_model',
        'user_agent',
        'expires_at',
        'claimed_at',
        'claimed_by_user_id',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'claimed_at'  => 'datetime',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    // -------------------------------------------------------------------------
    // Helpers statiques
    // -------------------------------------------------------------------------

    /**
     * Calcule l'empreinte device : SHA-256(ip|device_model).
     * Utilisée côté serveur au clic ET côté app pour le fallback fingerprint.
     */
    public static function computeFingerprint(string $ip, string $deviceModel): string
    {
        return hash('sha256', $ip . '|' . strtolower(trim($deviceModel)));
    }

    /**
     * Extrait un nom de modèle simplifié depuis le User-Agent.
     * Ex. "SM-A536B" depuis "...Samsung SM-A536B Build/..."
     * Retourne 'unknown' si rien n'est trouvé.
     */
    public static function extractDeviceModel(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        // Android : Build/<model> ou ; <model> Build/
        if (preg_match('/;\s*([^;)]+?)\s+Build\//i', $userAgent, $m)) {
            return trim($m[1]);
        }

        // iPhone / iPad : iPhone OS / CPU OS
        if (preg_match('/(iPhone|iPad|iPod)/i', $userAgent, $m)) {
            return $m[1];
        }

        return 'unknown';
    }
}

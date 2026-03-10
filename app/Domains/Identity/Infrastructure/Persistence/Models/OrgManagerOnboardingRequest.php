<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgManagerOnboardingRequest extends Model
{
    protected $fillable = [
        'identity_id',
        'organization_id',
        'email_verification_token',
        'email_verification_sent_at',
        'attempt_number',
        'status',
        'rejected_reason_code',
        'rejected_reason_detail',
        'admin_notified_at',
        'last_reminder_at',
        'reminder_count',
    ];

    protected $casts = [
        'email_verification_sent_at' => 'datetime',
        'admin_notified_at'          => 'datetime',
        'last_reminder_at'           => 'datetime',
        'attempt_number'             => 'integer',
        'reminder_count'             => 'integer',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function scopePendingValidation(Builder $query): Builder
    {
        return $query->where('status', 'pending_validation');
    }

    public function scopeEmailUnverified(Builder $query): Builder
    {
        return $query->where('status', 'email_unverified');
    }

    /** Demandes pending_validation non encore rappelées ou rappelées il y a plus de N heures. */
    public function scopeNeedsAdminReminder(Builder $query, int $hoursThreshold): Builder
    {
        return $query
            ->where('status', 'pending_validation')
            ->where(function (Builder $q) use ($hoursThreshold) {
                $q->whereNull('last_reminder_at')
                  ->orWhere('last_reminder_at', '<=', now()->subHours($hoursThreshold));
            });
    }

    public function isEmailUnverified(): bool
    {
        return $this->status === 'email_unverified';
    }

    public function isPendingValidation(): bool
    {
        return $this->status === 'pending_validation';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}

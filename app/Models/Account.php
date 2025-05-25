<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone',
        'pin_code',
        'phone_uuid',

        'type',
        'is_anonymous',
        'is_anonymous_at',
        'status',
        'is_active',
        'needs_assistance',

        'health_facility_id',
        'user_id',
        'created_by',
        'last_login_at',
    ];

    protected $hidden = [
        'pin_code',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_active' => 'boolean',
        'needs_assistance' => 'boolean',
        'last_login_at' => 'datetime',
        'is_anonymous_at' => 'datetime',
    ];

    // 🔁 Relations

    public function personalProfile()
    {
        return $this->hasOne(PersonalProfile::class);
    }

    public function medicalProfile()
    {
        return $this->hasOne(MedicalProfile::class);
    }


    public function healthFacility()
    {
        return $this->belongsTo(HealthFacility::class);
    }

    public function creator()
    {
        return $this->belongsTo(Account::class, 'created_by');
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluationSessions()
    {
        return $this->hasMany(Evaluation\EvaluationSession::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function recommandations()
    {
        return $this->hasMany(Recommandation::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // 🔎 Helpers

    public function isMidwife(): bool
    {
        return $this->type === 'midwife';
    }

    public function isAmbassador(): bool
    {
        return $this->type === 'ambassador';
    }

    public function isSubscriber(): bool
    {
        return $this->type === 'subscriber';
    }

    public function isAnonymous(): bool
    {
        return $this->is_anonymous === true;
    }

    public function isStaff(): bool
    {
        return in_array($this->type, ['midwife', 'ambassador']);
    }

    public function hasAccess(): bool
    {
        return $this->is_active && $this->status === 'active';
    }
}

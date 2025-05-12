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
        'marraine_status',
        'is_active',
        'is_anonymous',
        'is_anonymous_at',
        'needs_assistance',
        'marraine_id',
        'health_facility_id',
        'user_id',
        'last_login_at',
    ];

    protected $hidden = [
        'pin_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_anonymous' => 'boolean',
        'is_anonymous_at' => 'datetime',
        'needs_assistance' => 'boolean',
        'last_login_at' => 'datetime',
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
    
    public function marraine()
    {
        return $this->belongsTo(Account::class, 'marraine_id');
    }

    public function filleules()
    {
        return $this->hasMany(Account::class, 'marraine_id');
    }

    public function healthFacility()
    {
        return $this->belongsTo(HealthFacility::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
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

    public function isMarraine(): bool
    {
        return $this->marraine_status === 'active';
    }

    public function isSubscriber(): bool
    {
        return $this->type === 'subscriber';
    }

    public function isStaff(): bool
    {
        return $this->type === 'staff';
    }

    public function hasAccess(): bool
    {
        return $this->is_active;
    }
}

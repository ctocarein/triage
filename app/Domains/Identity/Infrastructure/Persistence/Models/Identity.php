<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Notification\Models\UserReminder;
use App\Domains\Profile\Infrastructure\Persistence\Models\MedicalProfile;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use App\Domains\Profile\Infrastructure\Persistence\Models\ProfessionalProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Identity extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kind',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Identity $identity): void {
            if (empty($identity->id)) {
                $identity->id = (string) Str::uuid();
            }
        });
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function securityGate(): HasOne
    {
        return $this->hasOne(IdentitySecurityGate::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(IdentityDevice::class);
    }

    public function pinAttempts(): HasMany
    {
        return $this->hasMany(IdentityPinAttempt::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(IdentitySecurityEvent::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'identity_id');
    }

    public function evaluationSessions(): HasMany
    {
        return $this->hasMany(EvaluationSession::class, 'identity_id');
    }

    public function personalProfile(): HasOne
    {
        return $this->hasOne(PersonalProfile::class, 'identity_id');
    }

    public function medicalProfile(): HasOne
    {
        return $this->hasOne(MedicalProfile::class, 'identity_id');
    }

    public function professionalProfile(): HasOne
    {
        return $this->hasOne(ProfessionalProfile::class, 'identity_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(UserReminder::class, 'identity_id');
    }

    public function hasMembershipRole(string $role, ?string $organizationId = null, ?string $status = 'active'): bool
    {
        $query = $this->memberships()->where('role', $role);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->exists();
    }
}

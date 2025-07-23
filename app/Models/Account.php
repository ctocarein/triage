<?php

namespace App\Models;

use App\Models\UserReminder;
use App\Models\MedicalProfile;
use App\Models\PersonalProfile;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Models\Evaluation\EvaluationSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'phone', 'pin_code', 'phone_uuid',
        'type', 'is_anonymous', 'is_anonymous_at', 'status', 'is_active', 'needs_assistance',
        'health_facility_id', 'user_id', 'created_by', 'last_login_at'
    ];

    protected $hidden = ['pin_code'];
    
    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_active' => 'boolean',
        'needs_assistance' => 'boolean',
        'last_login_at' => 'datetime',
        'is_anonymous_at' => 'datetime',
    ];

    // Relations
    public function personalProfile() { return $this->hasOne(PersonalProfile::class); }
    public function medicalProfile() { return $this->hasOne(MedicalProfile::class); }
    // public function healthFacility() { return $this->belongsTo(HealthFacility::class); }
    public function creator() { return $this->belongsTo(self::class, 'created_by'); }
    public function adminUser() { return $this->belongsTo(User::class, 'user_id'); }
    public function evaluationSessions() { return $this->hasMany(EvaluationSession::class); }
    public function activeEvaluationSession(){ return $this->evaluationSessions()->inProgress()->latest()->first();}
    public function reports() { return $this->hasMany(Report::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
    public function visits() { return $this->hasMany(Visit::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function notifications() { return $this->hasMany(Notification::class); }

    // Scopes
    public function scopeActive($query) {
        return $query->where('is_active', true)->where('status', 'active');
    }

    // Helpers
    public function isType(string $type): bool {
        return $this->type === $type;
    }

     /**
     * Vérifie si l'utilisateur a déjà complété au moins une évaluation complète.
     */
    public function hasCompletedFullEvaluation(): bool
    {
        return $this->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Récupère la session complète en cours (si elle existe).
     */
    public function getInProgressFullEvaluation(): ?EvaluationSession
    {
        return $this->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'in_progress')
            ->latest()
            ->first();
    }

    /**
     * Vérifie si une session thématique peut être lancée.
     */
    public function canStartThematicEvaluation(): bool
    {
        return $this->hasCompletedFullEvaluation() && !$this->getInProgressFullEvaluation();
    }

    /**
     * Retourne la dernière évaluation complète terminée (ou null si aucune).
     */
    public function lastCompletedFullEvaluation(): ?EvaluationSession
    {
        return $this->evaluationSessions()
            ->where('type', 'complete')
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();
    }

    /**
     * Doit-on suggérer une nouvelle évaluation complète ?
     * Par exemple, tous les 6 mois.
     */
    public function shouldSuggestFullEvaluation(int $months = 6): bool
    {
        $last = $this->lastCompletedFullEvaluation();
        if (!$last) return true;
        return now()->diffInMonths($last->completed_at) >= $months;
    }

    /**
     * Retourne les rappels de l'utilisateur.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reminders()
    {
        return $this->hasMany(UserReminder::class, 'user_id');
    }
}
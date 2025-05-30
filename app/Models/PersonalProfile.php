<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class PersonalProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'personal_profiles';

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'birth_date',
        'language',
        'education_level',
        'gender',
        'commune',
        'cmu', // Couverture Maladie Universelle
        'height', // Taille
        'occupation', // Occupation
        // Ajoute d’autres champs si nécessaire
    ];

    protected $dates = [
        'birth_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Accesseur dynamique pour calculer l'âge à partir de birth_date.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        return Carbon::parse($this->birth_date)->age;
    }

    /**
     * Relation avec le compte utilisateur.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}

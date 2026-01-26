<?php

namespace App\Domains\Profile\Models;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'cmu_number',
        'identity_id',
        'has_breast_cancer',
        'has_ovarian_cancer',
        'has_cervical_cancer',
        'has_other_cancer',
        'height',
        'weight',
        'blood_type',
        'allergies',
        'is_pregnant',
        'breastfeeding',
        'menopause_status',
        'family_cancer_history',
        'last_screening_date',
        'screening_type',
        'first_sexual_intercourse_age',
        'number_of_partners',
        'number_of_pregnancies',
        'number_of_abortions',
        'number_of_children',
        'exposed_to_smoke',
        'exposure_type',
        'notes',
        'allergies',
    ];

    protected $casts = [
        'is_pregnant' => 'boolean',
        'breastfeeding' => 'boolean',
        'family_cancer_history' => 'boolean',
        'exposed_to_smoke' => 'boolean',
        'last_screening_date' => 'date',
        'cmu_number' => 'string',
    ];

    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }


    /**
     * Normalize and set the menopause status attribute.
     *
     * The database expects one of the values: 'pre', 'post', or 'unknown'.
     * Incoming data might be boolean or translated strings such as 'Oui'/'Non'.
     * This mutator converts common truthy values to 'post' and falsy values to
     * 'pre'.
     */
    public function setMenopauseStatusAttribute($value): void
    {
        $normalized = $value;

        if (is_bool($value)) {
            $normalized = $value ? 'post' : 'pre';
        } elseif (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['oui', 'yes', 'true', '1'])) {
                $normalized = 'post';
            } elseif (in_array($lower, ['non', 'no', 'false', '0'])) {
                $normalized = 'pre';
            } elseif (in_array($lower, ['pre', 'post', 'unknown'])) {
                $normalized = $lower;
            }
        }

        $this->attributes['menopause_status'] = $normalized;
    }
}

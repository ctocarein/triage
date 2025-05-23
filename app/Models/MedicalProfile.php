<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'cmu_number',
        'account_id',
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
    ];

    protected $casts = [
        'is_pregnant' => 'boolean',
        'breastfeeding' => 'boolean',
        'family_cancer_history' => 'boolean',
        'exposed_to_smoke' => 'boolean',
        'last_screening_date' => 'date',
        'cmu_number' => 'string',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

}

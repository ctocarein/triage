<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonalProfile extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'language',
        'education_level',
        'commune',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}

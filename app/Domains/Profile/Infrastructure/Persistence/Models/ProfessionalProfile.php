<?php

namespace App\Domains\Profile\Infrastructure\Persistence\Models;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalProfile extends Model
{
    use HasFactory;

    protected $table = 'professional_profiles';

    protected $fillable = [
        'identity_id',
        'organization_id',
        'full_name',
        'job_title',
        'address',
    ];

    protected $casts = [
        'identity_id' => 'string',
        'organization_id' => 'string',
    ];

    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }
}

<?php

namespace App\Domains\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'iso2',
        'iso3',
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}

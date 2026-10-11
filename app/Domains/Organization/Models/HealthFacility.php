<?php

namespace App\Domains\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un etablissement de sante ou orienter une femme -- avec, surtout, la
 * presence ou non d'un mammographe.
 *
 * `is_sample` signale une ligne d'exemple, posee en attendant le referentiel
 * reel ; l'API la renvoie telle quelle pour que l'application l'affiche comme
 * telle.
 */
class HealthFacility extends Model
{
    protected $fillable = [
        'name',
        'kind',
        'health_district_id',
        'municipality',
        'address',
        'phone',
        'latitude',
        'longitude',
        'has_mammography',
        'is_sample',
    ];

    protected $casts = [
        'latitude'        => 'float',
        'longitude'       => 'float',
        'has_mammography' => 'boolean',
        'is_sample'       => 'boolean',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(HealthDistrict::class, 'health_district_id');
    }
}

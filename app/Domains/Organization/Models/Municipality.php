<?php

namespace App\Domains\Organization\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A municipality of the reference list, offered as an answer to the residence
 * question.
 *
 * `is_selectable` withdraws a row from the answer list without deleting it:
 * Abidjan stays in the table -- it carries answers already given, and it is
 * the natural parent should an agglomeration level be needed -- but it is not
 * offered, because its own municipalities are in the same list.
 */
class Municipality extends Model
{
    protected $fillable = [
        'name',
        'is_selectable',
    ];

    protected $casts = [
        'is_selectable' => 'boolean',
    ];
}

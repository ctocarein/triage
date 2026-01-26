<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'identity_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }
}

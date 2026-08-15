<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banner';

    protected $primaryKey = 'id_banner';

    protected $fillable = [
        'titulo',
        'subtitulo',
        'imagen_url',
        'public_id',
        'enlace',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}

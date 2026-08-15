<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeaderText extends Model
{
    protected $table = 'header_text';

    protected $primaryKey = 'id_header_text';

    protected $fillable = [
        'texto',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden'  => 'integer',
    ];
}

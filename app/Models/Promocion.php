<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promocion';

    protected $primaryKey = 'id_promocion';

    protected $fillable = [
        'titulo',
        'subtitulo',
        'imagen_url',
        'public_id',
        'enlace',
        'tipo_layout',
        'tipo_promocion',
        'ambito',
        'ambito_id',
        'descuento_tipo',
        'descuento_valor',
        'combo_id_combo',
        'orden',
        'activo',
        'clicks',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'orden'           => 'integer',
        'clicks'          => 'integer',
        'descuento_valor' => 'decimal:2',
    ];

    public function combo()
    {
        return $this->belongsTo(Combo::class, 'combo_id_combo');
    }
}

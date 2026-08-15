<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortadaCategoria extends Model
{
    protected $table = 'portada_categoria';

    protected $primaryKey = 'id_portada_categoria';

    protected $fillable = [
        'categoria_id_categoria',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id_categoria');
    }
}

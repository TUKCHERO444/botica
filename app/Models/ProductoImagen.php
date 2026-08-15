<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoImagen extends Model
{
    protected $table = 'producto_imagen';

    protected $primaryKey = 'id_producto_imagen';

    protected $fillable = [
        'producto_id_producto',
        'url',
        'public_id',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto');
    }
}

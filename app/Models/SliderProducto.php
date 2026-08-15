<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SliderProducto extends Model
{
    protected $table = 'slider_producto';

    protected $primaryKey = 'id_slider_producto';

    protected $fillable = [
        'slider_tipo',
        'producto_id_producto',
        'orden',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto');
    }
}

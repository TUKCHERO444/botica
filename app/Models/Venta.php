<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'venta';

    protected $primaryKey = 'id_venta';

    public $timestamps = true;

    protected $fillable = [
        'correlativo',
        'total',
        'cliente_id_cliente',
        'user_id',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id_cliente', 'id_cliente');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'detalle_venta', 'venta_id_venta', 'producto_id_producto')
            ->withPivot('sub_total', 'cantidad');
    }

    public function lotesConsumidos()
    {
        return $this->hasMany(DetalleVentaLote::class, 'venta_id_venta', 'id_venta');
    }
}

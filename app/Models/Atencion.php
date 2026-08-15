<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atencion extends Model
{
    protected $table = 'atencion';

    protected $primaryKey = 'id_atencion';

    public $timestamps = true;

    protected $fillable = [
        'observacio',
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
        return $this->belongsToMany(Producto::class, 'atencion_producto', 'atencion_id_atencion', 'producto_id_producto')
            ->withPivot('sub_total', 'cantidad');
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'atencion_servicio', 'atencion_id_atencion', 'servicio_id_servicio')
            ->withPivot('subtotal');
    }

    public function lotesConsumidos()
    {
        return $this->hasMany(AtencionProductoLote::class, 'atencion_id_atencion', 'id_atencion');
    }
}

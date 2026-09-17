<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    protected $table = 'ingreso';

    protected $primaryKey = 'id_ingreso';

    public $timestamps = false;

    protected $fillable = [
        'correlativo',
        'proveedor_id_proveedor',
        'fecha',
        'user_id',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id_proveedor', 'id_proveedor');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'detalle_ingreso', 'ingreso_id_ingreso', 'producto_id_producto')
            ->withPivot('cantidad');
    }
}

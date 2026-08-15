<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrito extends Model
{
    protected $table = 'carrito';

    protected $primaryKey = 'id_carrito';

    protected $fillable = [
        'cliente_id_cliente',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id_cliente', 'id_cliente');
    }

    public function items()
    {
        return $this->hasMany(CarritoItem::class, 'carrito_id_carrito', 'id_carrito');
    }
}

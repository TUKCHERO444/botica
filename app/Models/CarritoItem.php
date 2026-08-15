<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarritoItem extends Model
{
    protected $table = 'carrito_item';

    protected $primaryKey = 'id_carrito_item';

    protected $fillable = [
        'carrito_id_carrito',
        'item_type',
        'item_id',
        'cantidad',
    ];

    public function carrito()
    {
        return $this->belongsTo(Carrito::class, 'carrito_id_carrito', 'id_carrito');
    }

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function getSubtotalAttribute(): float
    {
        $precio = match ($this->item_type) {
            'producto' => $this->item?->precio_venta ?? 0,
            'combo'    => $this->item?->precio_sugerido ?? 0,
            default    => 0,
        };
        return $precio * $this->cantidad;
    }

    public function getNombreAttribute(): ?string
    {
        return $this->item?->nombre;
    }

    public function getPrecioUnitarioAttribute(): float
    {
        return match ($this->item_type) {
            'producto' => $this->item?->precio_venta ?? 0,
            'combo'    => $this->item?->precio_sugerido ?? 0,
            default    => 0,
        };
    }

    public function getFotoUrlAttribute(): ?string
    {
        if ($this->item_type === 'producto') {
            return $this->item?->foto_url;
        }
        return $this->item?->imagen_url;
    }
}

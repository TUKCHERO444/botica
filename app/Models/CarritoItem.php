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
        'producto_presentacion_id',
        'cantidad',
        'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
        ];
    }

    public function carrito()
    {
        return $this->belongsTo(Carrito::class, 'carrito_id_carrito', 'id_carrito');
    }

    public function item()
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function productoPresentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id', 'id_producto_presentacion');
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->precio_unitario * (float) $this->cantidad;
    }

    public function getNombreAttribute(): ?string
    {
        if ($this->item_type === 'producto' && $this->productoPresentacion) {
            return $this->productoPresentacion->producto?->nombre
                .' — '.$this->productoPresentacion->nombre_completo;
        }

        return $this->item?->nombre;
    }

    public function getPrecioUnitarioAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        if ($this->item_type === 'producto') {
            return (float) ($this->productoPresentacion?->precio_venta
                ?? $this->item?->precio_venta
                ?? 0);
        }

        return (float) ($this->item?->precio_sugerido ?? 0);
    }

    public function getFotoUrlAttribute(): ?string
    {
        if ($this->item_type === 'producto') {
            return $this->item?->foto_url;
        }

        return $this->item?->imagen_url;
    }
}

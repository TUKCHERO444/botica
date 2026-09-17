<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'producto';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'estado',
        'inventario',
        'stock',
        'categoria_id_categoria',
        'marca_id_marca',
    ];

    protected $with = ['imagenes'];

    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class, 'producto_id_producto')
            ->orderBy('orden');
    }

    public function getFotoUrlAttribute(): ?string
    {
        $primera = $this->imagenes->first();
        if (! $primera?->url) {
            return null;
        }
        if (str_starts_with($primera->url, 'http')) {
            return $primera->url;
        }

        return asset('storage/'.$primera->url);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id_categoria', 'id_categoria');
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'marca_id_marca', 'id_marca');
    }

    public function atenciones()
    {
        return $this->belongsToMany(Atencion::class, 'atencion_producto', 'producto_id_producto', 'atencion_id_atencion')
            ->withPivot('sub_total', 'cantidad');
    }

    public function ingresos()
    {
        return $this->belongsToMany(Ingreso::class, 'detalle_ingreso', 'producto_id_producto', 'ingreso_id_ingreso')
            ->withPivot('cantidad');
    }

    public function ventas()
    {
        return $this->belongsToMany(Venta::class, 'detalle_venta', 'producto_id_producto', 'venta_id_venta')
            ->withPivot('sub_total', 'cantidad');
    }

    public function scopeVisible($query)
    {
        return $query->where('estado', 1);
    }
}

<?php

namespace App\Models;

use App\Helpers\CantidadHelper;
use Illuminate\Database\Eloquent\Model;

class ProductoPresentacion extends Model
{
    protected $table = 'producto_presentacion';

    protected $primaryKey = 'id_producto_presentacion';

    protected $fillable = [
        'producto_id_producto',
        'presentacion_id',
        'nombre',
        'factor',
        'precio_venta',
        'paso',
        'es_base',
        'activo',
        'codigo_barras',
        'orden',
    ];

    protected $casts = [
        'factor' => 'decimal:3',
        'precio_venta' => 'decimal:4',
        'paso' => 'decimal:3',
        'es_base' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto', 'id_producto');
    }

    public function presentacion()
    {
        return $this->belongsTo(Presentacion::class, 'presentacion_id', 'id_presentacion');
    }

    public function getNombreCompletoAttribute(): string
    {
        if ($this->nombre) {
            return $this->nombre;
        }

        $base = $this->presentacion?->nombre ?? 'Presentación';

        if ((float) $this->factor > 1) {
            return $base.' x '.CantidadHelper::formatear($this->factor);
        }

        return $base;
    }

    public function getEtiquetaAttribute(): string
    {
        $nombre = $this->nombre_completo;
        $unidad = $this->producto?->unidadBase?->codigo;

        return $unidad ? "{$nombre} ({$unidad})" : $nombre;
    }

    public function getFactorInputAttribute(): string
    {
        return CantidadHelper::formatear($this->factor);
    }

    public function getPrecioVentaInputAttribute(): string
    {
        return number_format((float) $this->precio_venta, 2, '.', '');
    }

    public function getPasoInputAttribute(): string
    {
        return CantidadHelper::formatear($this->paso);
    }
}

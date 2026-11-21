<?php

namespace App\Models;

use App\Helpers\CantidadHelper;
use App\Helpers\PresentacionStockHelper;
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
        'stock_minimo',
        'stock_maximo',
        'unidad_base_id',
        'categoria_id_categoria',
        'marca_id_marca',
    ];

    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:4',
            'precio_venta' => 'decimal:4',
            'inventario' => 'decimal:3',
            'stock' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'stock_maximo' => 'decimal:3',
            'estado' => 'integer',
        ];
    }

    protected $with = ['imagenes'];

    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class, 'producto_id_producto')
            ->orderBy('orden');
    }

    public function unidadBase()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_base_id', 'id_unidad_medida');
    }

    public function presentaciones()
    {
        return $this->hasMany(ProductoPresentacion::class, 'producto_id_producto', 'id_producto')
            ->orderBy('orden')
            ->orderBy('id_producto_presentacion');
    }

    public function presentacionBase()
    {
        return $this->hasOne(ProductoPresentacion::class, 'producto_id_producto', 'id_producto')
            ->where('es_base', true);
    }

    public function lotes()
    {
        return $this->hasMany(ProductoLote::class, 'producto_id', 'id_producto')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id');
    }

    public function lotesDisponibles()
    {
        return $this->lotes()->where('estado', 'disponible')->where('cantidad_disponible_base', '>', 0);
    }

    /**
     * Lotes que FEFO puede consumir ahora: disponibles, con cantidad y no vencidos.
     * Es la misma condición que aplica InventarioService::consumir().
     */
    public function lotesVendibles()
    {
        return $this->lotes()
            ->where('estado', 'disponible')
            ->where('cantidad_disponible_base', '>', 0)
            ->whereDate('fecha_vencimiento', '>=', today()->toDateString());
    }

    /**
     * Productos con algo que vender hoy.
     *
     * Si el producto todavía no tiene lotes (datos legados o fixtures), se usa
     * `stock` como antes. Si ya tiene lotes, manda el stock vendible por FEFO.
     */
    public function scopeConStockVendible($query)
    {
        return $query->where('stock', '>', 0)
            ->where(function ($query) {
                $query->whereDoesntHave('lotes')
                    ->orWhereHas('lotesVendibles');
            });
    }

    /**
     * Subconsulta de stock vendible por producto, para filtros y agregados SQL.
     * No aplica el respaldo a `stock`: se usa en consultas que exigen historial
     * de lotes (productos ya cargados por inventario inicial o compras).
     */
    private static function stockVendibleQuery()
    {
        return ProductoLote::query()
            ->selectRaw('COALESCE(SUM(cantidad_disponible_base), 0)')
            ->whereColumn('producto_id', 'producto.id_producto')
            ->where('estado', 'disponible')
            ->where('cantidad_disponible_base', '>', 0)
            ->whereDate('fecha_vencimiento', '>=', today()->toDateString());
    }

    public function scopeConStockVendibleCalculado($query)
    {
        return $query->addSelect('producto.*')
            ->selectSub(self::stockVendibleQuery(), 'stock_vendible_calc');
    }

    public function scopeConAlertaStock($query, string $alerta)
    {
        if ($alerta === 'bajo') {
            $sub = self::stockVendibleQuery();
            $sql = '('.$sub->toSql().')';
            $bindings = $sub->getBindings();

            return $query->where('stock_minimo', '>', 0)
                ->whereHas('lotes')
                ->whereRaw("{$sql} > 0 AND {$sql} <= producto.stock_minimo", [...$bindings, ...$bindings]);
        }

        if ($alerta === 'agotado') {
            $sub = self::stockVendibleQuery();

            return $query->whereHas('lotes')
                ->whereRaw('('.$sub->toSql().') <= 0', $sub->getBindings());
        }

        if ($alerta === 'inventario') {
            $sub = self::stockVendibleQuery();

            return $query->whereHas('lotes')
                ->whereRaw('('.$sub->toSql().') <= producto.stock_minimo', $sub->getBindings());
        }

        if ($alerta === 'por_vencer') {
            return $query->whereHas('lotes', fn ($lotes) => $lotes
                ->where('estado', 'disponible')
                ->where('cantidad_disponible_base', '>', 0)
                ->whereDate('fecha_vencimiento', '>=', today()->toDateString())
                ->whereDate('fecha_vencimiento', '<=', today()->addDays(30)->toDateString()));
        }

        return $query;
    }

    public function stockVendible(): string
    {
        if (! $this->relationLoaded('lotesVendibles')) {
            if ($this->lotes()->doesntExist()) {
                return (string) $this->stock;
            }

            return (string) $this->lotesVendibles()->sum('cantidad_disponible_base');
        }

        $suma = (string) $this->lotesVendibles->sum('cantidad_disponible_base');

        if (CantidadHelper::comparar($suma, 0) > 0) {
            return $suma;
        }

        // Sin lotes registrados se conserva el comportamiento anterior.
        return $this->lotes()->doesntExist() ? (string) $this->stock : '0.000';
    }

    public function estaBajoMinimo(string|int|float|null $stock = null): bool
    {
        if (CantidadHelper::comparar($this->stock_minimo ?? 0, 0) <= 0) {
            return false;
        }

        $vendible = $stock !== null ? (string) $stock : $this->stockVendible();

        return CantidadHelper::comparar($vendible, $this->stock_minimo) <= 0;
    }

    public function proximoLoteVendible(): ?ProductoLote
    {
        if ($this->relationLoaded('lotesVendibles')) {
            return $this->lotesVendibles->first();
        }

        return $this->lotesVendibles()->first();
    }

    /**
     * Datos de alerta reutilizables por el buscador de ventas, atenciones y listados.
     */
    public function resumenInventario(): array
    {
        $vendible = $this->stockVendible();
        $lote = $this->proximoLoteVendible();
        $dias = $lote?->dias_para_vencer;
        $desglose = PresentacionStockHelper::desglosar($this, $vendible);

        $alertaStock = null;
        if (CantidadHelper::comparar($vendible, 0) <= 0) {
            $alertaStock = 'Sin stock vendible';
        } elseif ($this->estaBajoMinimo($vendible)) {
            $alertaStock = 'Bajo mínimo';
        }

        $alertaVencimiento = null;
        if ($dias !== null && $dias <= 30) {
            $alertaVencimiento = $dias <= 0 ? 'Vence hoy' : "Vence en {$dias} días";
        }

        return [
            'stock_vendible' => (float) $vendible,
            'stock_vendible_formateado' => CantidadHelper::formatear($vendible).' '.($this->unidadBase?->codigo ?? ''),
            'stock_desglose' => $desglose['desglose'],
            'stock_equivalencias' => $desglose['por_presentacion'],
            'stock_minimo' => (float) $this->stock_minimo,
            'stock_minimo_formateado' => $this->stock_minimo_formateado,
            'dias_para_vencer' => $dias,
            'alerta_stock' => $alertaStock,
            'alerta_vencimiento' => $alertaVencimiento,
        ];
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
            ->withPivot(
                'id_atencion_producto',
                'producto_presentacion_id',
                'sub_total',
                'cantidad',
                'factor',
                'cantidad_base',
                'precio_unitario'
            );
    }

    public function ingresos()
    {
        return $this->belongsToMany(Ingreso::class, 'detalle_ingreso', 'producto_id_producto', 'ingreso_id_ingreso')
            ->withPivot(
                'id_detalle_ingreso',
                'producto_presentacion_id',
                'cantidad',
                'factor',
                'cantidad_base'
            );
    }

    public function ventas()
    {
        return $this->belongsToMany(Venta::class, 'detalle_venta', 'producto_id_producto', 'venta_id_venta')
            ->withPivot(
                'id_detalle_venta',
                'producto_presentacion_id',
                'sub_total',
                'cantidad',
                'factor',
                'cantidad_base',
                'precio_unitario'
            );
    }

    public function getPrecioDesdeAttribute(): string
    {
        $precios = $this->relationLoaded('presentaciones')
            ? $this->presentaciones->where('activo', true)->pluck('precio_venta')
            : $this->presentaciones()->where('activo', true)->pluck('precio_venta');

        if ($precios->isEmpty()) {
            return (string) $this->precio_venta;
        }

        return (string) $precios->min();
    }

    public function getStockFormateadoAttribute(): string
    {
        return CantidadHelper::formatear($this->stock).' '.($this->unidadBase?->codigo ?? '');
    }

    public function getStockMinimoFormateadoAttribute(): string
    {
        return CantidadHelper::formatear($this->stock_minimo).' '.($this->unidadBase?->codigo ?? '');
    }

    public function getPrecioCompraInputAttribute(): string
    {
        return $this->precio_compra === null
            ? ''
            : number_format((float) $this->precio_compra, 2, '.', '');
    }

    public function getPrecioVentaInputAttribute(): string
    {
        return number_format((float) $this->precio_venta, 2, '.', '');
    }

    public function getStockInputAttribute(): string
    {
        return CantidadHelper::formatear($this->stock);
    }

    public function getStockMinimoInputAttribute(): string
    {
        return CantidadHelper::formatear($this->stock_minimo);
    }

    public function getStockMaximoInputAttribute(): string
    {
        return $this->stock_maximo === null
            ? ''
            : CantidadHelper::formatear($this->stock_maximo);
    }

    public function getStockConUnidadAttribute(): string
    {
        $cantidad = CantidadHelper::formatear($this->stock);
        $nombre = mb_strtolower($this->unidadBase?->nombre ?? 'unidad');

        if ((float) $this->stock === 1.0) {
            return "{$cantidad} {$nombre}";
        }

        $plural = match ($nombre) {
            'unidad' => 'unidades',
            'cápsula' => 'cápsulas',
            'mililitro' => 'mililitros',
            'gramo' => 'gramos',
            'tableta' => 'tabletas',
            default => $nombre.'s',
        };

        return "{$cantidad} {$plural}";
    }

    public function scopeVisible($query)
    {
        return $query->where('estado', 1);
    }
}

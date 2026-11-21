<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_RECIBIDA = 'recibida';

    public const ESTADO_ANULADA = 'anulada';

    public const TIPOS_DOCUMENTO = [
        'factura' => 'Factura',
        'boleta' => 'Boleta',
        'ticket' => 'Ticket',
        'nota_venta' => 'Nota de venta',
        'guia_remision' => 'Guía de remisión',
        'otro' => 'Otro',
    ];

    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'yape' => 'Yape',
        'tarjeta' => 'Tarjeta',
        'otro' => 'Otro',
    ];

    protected $table = 'ingreso';

    protected $primaryKey = 'id_ingreso';

    protected $fillable = [
        'correlativo',
        'estado',
        'proveedor_id_proveedor',
        'fecha',
        'tipo_documento',
        'numero_documento',
        'subtotal',
        'total',
        'observaciones',
        'fecha_recepcion',
        'recepcion_user_id',
        'pagado_desde_caja',
        'metodo_pago',
        'referencia_pago',
        'caja_id',
        'egreso_caja_id',
        'anulada_at',
        'motivo_anulacion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_recepcion' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'pagado_desde_caja' => 'boolean',
            'anulada_at' => 'datetime',
        ];
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id_proveedor', 'id_proveedor');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recepcionUser()
    {
        return $this->belongsTo(User::class, 'recepcion_user_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id', 'id_caja');
    }

    public function egresoMovimiento()
    {
        return $this->belongsTo(CajaMovimiento::class, 'egreso_caja_id', 'id_movimiento');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleIngreso::class, 'ingreso_id_ingreso', 'id_ingreso')
            ->orderBy('id_detalle_ingreso');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'detalle_ingreso', 'ingreso_id_ingreso', 'producto_id_producto')
            ->withPivot(
                'id_detalle_ingreso',
                'producto_presentacion_id',
                'cantidad',
                'factor',
                'cantidad_base',
                'costo_unitario',
                'producto_lote_id',
                'lote_codigo',
                'fecha_vencimiento',
                'costo_unitario_base',
                'costo_total'
            );
    }

    public function scopeBorrador($query)
    {
        return $query->where('estado', self::ESTADO_BORRADOR);
    }

    public function scopeRecibida($query)
    {
        return $query->where('estado', self::ESTADO_RECIBIDA);
    }

    public function scopeAnulada($query)
    {
        return $query->where('estado', self::ESTADO_ANULADA);
    }

    public function estaEnBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function estaRecibida(): bool
    {
        return $this->estado === self::ESTADO_RECIBIDA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_BORRADOR => 'Borrador',
            self::ESTADO_RECIBIDA => 'Recibida',
            self::ESTADO_ANULADA => 'Anulada',
            default => ucfirst((string) $this->estado),
        };
    }

    public function getEstadoClaseAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_BORRADOR => 'bg-amber-100 text-amber-700',
            self::ESTADO_RECIBIDA => 'bg-emerald-100 text-emerald-700',
            self::ESTADO_ANULADA => 'bg-red-100 text-red-700',
            default => 'bg-stone-100 text-stone-700',
        };
    }

    public function getTipoDocumentoLabelAttribute(): ?string
    {
        return $this->tipo_documento
            ? (self::TIPOS_DOCUMENTO[$this->tipo_documento] ?? ucfirst($this->tipo_documento))
            : null;
    }

    public function getMetodoPagoLabelAttribute(): ?string
    {
        return $this->metodo_pago
            ? (self::METODOS_PAGO[$this->metodo_pago] ?? ucfirst($this->metodo_pago))
            : null;
    }
}

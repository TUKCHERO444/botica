<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionProveedor extends Model
{
    public const ESTADO_REGISTRADA = 'registrada';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'devolucion_proveedor';

    protected $primaryKey = 'id_devolucion_proveedor';

    protected $fillable = [
        'correlativo',
        'ingreso_id_ingreso',
        'proveedor_id_proveedor',
        'fecha',
        'motivo',
        'estado',
        'total',
        'reembolsado_a_caja',
        'metodo_pago',
        'referencia_pago',
        'caja_id',
        'caja_movimiento_id',
        'user_id',
        'anulada_at',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
            'reembolsado_a_caja' => 'boolean',
            'anulada_at' => 'datetime',
        ];
    }

    public function ingreso()
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id_ingreso', 'id_ingreso');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id_proveedor', 'id_proveedor');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id', 'id_caja');
    }

    public function movimiento()
    {
        return $this->belongsTo(CajaMovimiento::class, 'caja_movimiento_id', 'id_movimiento');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleDevolucionProveedor::class, 'devolucion_proveedor_id', 'id_devolucion_proveedor')
            ->orderBy('id_detalle_devolucion_proveedor');
    }

    public function estaRegistrada(): bool
    {
        return $this->estado === self::ESTADO_REGISTRADA;
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_REGISTRADA => 'Registrada',
            self::ESTADO_ANULADA => 'Anulada',
            default => ucfirst((string) $this->estado),
        };
    }

    public function getEstadoClaseAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_REGISTRADA => 'bg-emerald-100 text-emerald-700',
            self::ESTADO_ANULADA => 'bg-red-100 text-red-700',
            default => 'bg-stone-100 text-stone-700',
        };
    }

    public function getMetodoPagoLabelAttribute(): ?string
    {
        return $this->metodo_pago
            ? (Ingreso::METODOS_PAGO[$this->metodo_pago] ?? ucfirst($this->metodo_pago))
            : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_EGRESO = 'egreso';

    public const CATEGORIA_VENTA = 'venta';

    public const CATEGORIA_ATENCION = 'atencion';

    public const CATEGORIA_ANULACION_VENTA = 'anulacion_venta';

    public const CATEGORIA_ANULACION_ATENCION = 'anulacion_atencion';

    public const CATEGORIA_AJUSTE_ATENCION = 'ajuste_atencion';

    public const CATEGORIA_COMPRA = 'compra';

    public const CATEGORIA_DEVOLUCION_COMPRA = 'devolucion_compra';

    public const CATEGORIA_DEVOLUCION_CLIENTE = 'devolucion_cliente';

    public const CATEGORIA_APORTE = 'aporte';

    public const CATEGORIA_COBRO_CREDITO = 'cobro_credito';

    public const CATEGORIA_AJUSTE_POSITIVO = 'ajuste_positivo';

    public const CATEGORIA_AJUSTE_NEGATIVO = 'ajuste_negativo';

    public const CATEGORIA_RETIRO_BANCO = 'retiro_banco';

    public const CATEGORIA_RETIRO_PROPIETARIO = 'retiro_propietario';

    public const CATEGORIA_GASTO_LUZ = 'gasto_luz';

    public const CATEGORIA_GASTO_AGUA = 'gasto_agua';

    public const CATEGORIA_GASTO_INTERNET = 'gasto_internet';

    public const CATEGORIA_GASTO_ALQUILER = 'gasto_alquiler';

    public const CATEGORIA_GASTO_LIMPIEZA = 'gasto_limpieza';

    public const CATEGORIA_GASTO_MOVILIDAD = 'gasto_movilidad';

    public const CATEGORIA_GASTO_UTILES = 'gasto_utiles';

    public const CATEGORIA_GASTO_MANTENIMIENTO = 'gasto_mantenimiento';

    public const CATEGORIA_GASTO_OTRO = 'gasto_otro';

    public const CATEGORIA_OTRO_INGRESO = 'otro_ingreso';

    public const CATEGORIA_OTRO_EGRESO = 'otro_egreso';

    public const NATURALEZA_OPERATIVO = 'operativo';

    public const NATURALEZA_TRANSFERENCIA = 'transferencia';

    public const NATURALEZA_AJUSTE = 'ajuste';

    public const NATURALEZA_REVERSION = 'reversion';

    public const CATEGORIAS = [
        self::CATEGORIA_VENTA => 'Venta',
        self::CATEGORIA_ATENCION => 'Atención',
        self::CATEGORIA_ANULACION_VENTA => 'Anulación de venta',
        self::CATEGORIA_ANULACION_ATENCION => 'Anulación de atención',
        self::CATEGORIA_AJUSTE_ATENCION => 'Ajuste de atención',
        self::CATEGORIA_COMPRA => 'Compra',
        self::CATEGORIA_DEVOLUCION_COMPRA => 'Devolución de proveedor',
        self::CATEGORIA_DEVOLUCION_CLIENTE => 'Devolución a cliente',
        self::CATEGORIA_APORTE => 'Aporte de sencillo',
        self::CATEGORIA_COBRO_CREDITO => 'Cobro de crédito',
        self::CATEGORIA_AJUSTE_POSITIVO => 'Ajuste positivo',
        self::CATEGORIA_AJUSTE_NEGATIVO => 'Ajuste negativo',
        self::CATEGORIA_RETIRO_BANCO => 'Retiro a banco',
        self::CATEGORIA_RETIRO_PROPIETARIO => 'Retiro del propietario',
        self::CATEGORIA_GASTO_LUZ => 'Luz',
        self::CATEGORIA_GASTO_AGUA => 'Agua',
        self::CATEGORIA_GASTO_INTERNET => 'Internet',
        self::CATEGORIA_GASTO_ALQUILER => 'Alquiler',
        self::CATEGORIA_GASTO_LIMPIEZA => 'Limpieza',
        self::CATEGORIA_GASTO_MOVILIDAD => 'Movilidad',
        self::CATEGORIA_GASTO_UTILES => 'Útiles de oficina',
        self::CATEGORIA_GASTO_MANTENIMIENTO => 'Mantenimiento',
        self::CATEGORIA_GASTO_OTRO => 'Otro gasto',
        self::CATEGORIA_OTRO_INGRESO => 'Otro ingreso',
        self::CATEGORIA_OTRO_EGRESO => 'Otro egreso',
    ];

    public const CATEGORIAS_INGRESO_MANUAL = [
        self::CATEGORIA_APORTE => 'Aporte de sencillo',
        self::CATEGORIA_COBRO_CREDITO => 'Cobro de crédito',
        self::CATEGORIA_AJUSTE_POSITIVO => 'Ajuste positivo',
        self::CATEGORIA_OTRO_INGRESO => 'Otro ingreso',
    ];

    public const CATEGORIAS_EGRESO_MANUAL = [
        self::CATEGORIA_GASTO_LUZ => 'Luz',
        self::CATEGORIA_GASTO_AGUA => 'Agua',
        self::CATEGORIA_GASTO_INTERNET => 'Internet',
        self::CATEGORIA_GASTO_ALQUILER => 'Alquiler',
        self::CATEGORIA_GASTO_LIMPIEZA => 'Limpieza',
        self::CATEGORIA_GASTO_MOVILIDAD => 'Movilidad',
        self::CATEGORIA_GASTO_UTILES => 'Útiles de oficina',
        self::CATEGORIA_GASTO_MANTENIMIENTO => 'Mantenimiento',
        self::CATEGORIA_GASTO_OTRO => 'Otro gasto',
        self::CATEGORIA_RETIRO_BANCO => 'Retiro a banco',
        self::CATEGORIA_RETIRO_PROPIETARIO => 'Retiro del propietario',
        self::CATEGORIA_AJUSTE_NEGATIVO => 'Ajuste negativo',
        self::CATEGORIA_OTRO_EGRESO => 'Otro egreso',
    ];

    public const CATEGORIAS_TRANSFERENCIA = [
        self::CATEGORIA_APORTE,
        self::CATEGORIA_RETIRO_BANCO,
        self::CATEGORIA_RETIRO_PROPIETARIO,
    ];

    public const CATEGORIAS_AJUSTE = [
        self::CATEGORIA_AJUSTE_POSITIVO,
        self::CATEGORIA_AJUSTE_NEGATIVO,
    ];

    protected $table = 'caja_movimiento';

    protected $primaryKey = 'id_movimiento';

    public $timestamps = false;

    protected $fillable = [
        'caja_id_caja',
        'tipo',
        'monto',
        'descripcion',
        'referencia_tipo',
        'referencia_id',
        'metodo_pago_id',
        'afecta_efectivo',
        'categoria',
        'naturaleza',
        'movimiento_origen_id',
        'caja_origen_id',
        'comprobante',
        'user_id',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Los movimientos de caja son inmutables. Registre un movimiento compensatorio.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Los movimientos de caja no se eliminan. Registre un movimiento compensatorio.');
        });
    }

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'afecta_efectivo' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id_caja', 'id_caja');
    }

    public function cajaOrigen()
    {
        return $this->belongsTo(Caja::class, 'caja_origen_id', 'id_caja');
    }

    public function movimientoOrigen()
    {
        return $this->belongsTo(self::class, 'movimiento_origen_id', 'id_movimiento');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id', 'id_metodo_pago');
    }

    public function getCategoriaLabelAttribute(): string
    {
        return self::labelCategoria($this->categoria);
    }

    public function getMetodoLabelAttribute(): string
    {
        return $this->metodoPago?->nombre
            ?? ($this->afecta_efectivo ? 'Efectivo' : 'Sin método');
    }

    public function getDescripcionVisibleAttribute(): string
    {
        if (filled($this->descripcion)) {
            return $this->descripcion;
        }

        if ($this->referencia_tipo === 'venta' && $this->referencia_id) {
            return 'Venta #'.$this->referencia_id;
        }

        if ($this->referencia_tipo === 'atencion' && $this->referencia_id) {
            return 'Atención #'.$this->referencia_id;
        }

        return $this->categoria_label;
    }

    public static function labelCategoria(?string $categoria): string
    {
        if (blank($categoria)) {
            return 'Sin categoría';
        }

        return self::CATEGORIAS[$categoria] ?? ucfirst(str_replace('_', ' ', $categoria));
    }

    public static function naturalezaParaCategoria(?string $categoria): string
    {
        if (in_array($categoria, self::CATEGORIAS_TRANSFERENCIA, true)) {
            return self::NATURALEZA_TRANSFERENCIA;
        }

        if (in_array($categoria, self::CATEGORIAS_AJUSTE, true)) {
            return self::NATURALEZA_AJUSTE;
        }

        return self::NATURALEZA_OPERATIVO;
    }

    public function esTransferencia(): bool
    {
        return $this->naturaleza === self::NATURALEZA_TRANSFERENCIA;
    }
}

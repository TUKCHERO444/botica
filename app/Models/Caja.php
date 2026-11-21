<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    public const MOTIVOS_DIFERENCIA = [
        'faltante' => 'Faltante de efectivo',
        'sobrante' => 'Sobrante de efectivo',
        'error_vuelto' => 'Error de vuelto',
        'gasto_no_registrado' => 'Gasto o retiro no registrado',
        'otro' => 'Otro',
    ];

    protected $table = 'caja';

    protected $primaryKey = 'id_caja';

    protected $fillable = [
        'monto_inicial',
        'total_ingresos',
        'total_ingresos_efectivo',
        'total_ingresos_digital',
        'total_egresos',
        'total_egresos_efectivo',
        'total_egresos_digital',
        'monto_final',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',
        'motivo_diferencia',
        'arqueo_detalle',
        'observacion_apertura',
        'observacion_cierre',
        'estado',
        'abierta_unica',
        'apertura_user_id',
        'cierre_user_id',
        'arqueo_user_id',
        'apertura_at',
        'cierre_at',
        'arqueo_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Caja $caja) {
            if ($caja->estado === null) {
                $caja->estado = 'abierta';
            }

            // Índice único: solo puede existir una fila con 1 (caja abierta).
            $caja->abierta_unica = $caja->estado === 'abierta' ? 1 : null;
        });
    }

    protected function casts(): array
    {
        return [
            'monto_inicial' => 'decimal:2',
            'total_ingresos' => 'decimal:2',
            'total_ingresos_efectivo' => 'decimal:2',
            'total_ingresos_digital' => 'decimal:2',
            'total_egresos' => 'decimal:2',
            'total_egresos_efectivo' => 'decimal:2',
            'total_egresos_digital' => 'decimal:2',
            'monto_final' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'arqueo_detalle' => 'array',
            'apertura_at' => 'datetime',
            'cierre_at' => 'datetime',
            'arqueo_at' => 'datetime',
            'abierta_unica' => 'integer',
        ];
    }

    public function aperturaUser()
    {
        return $this->belongsTo(User::class, 'apertura_user_id');
    }

    public function cierreUser()
    {
        return $this->belongsTo(User::class, 'cierre_user_id');
    }

    public function arqueoUser()
    {
        return $this->belongsTo(User::class, 'arqueo_user_id');
    }

    public function movimientos()
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_id_caja', 'id_caja');
    }

    public function scopeAbierta($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeCerrada($query)
    {
        return $query->where('estado', 'cerrada');
    }

    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    public function getMontoActualAttribute(): float
    {
        return (float) ($this->monto_inicial + $this->total_ingresos - $this->total_egresos);
    }

    public function getEfectivoEsperadoCalculadoAttribute(): float
    {
        if ($this->efectivo_esperado !== null) {
            return (float) $this->efectivo_esperado;
        }

        return (float) ($this->monto_inicial + $this->total_ingresos_efectivo - $this->total_egresos_efectivo);
    }

    public function getDiferenciaRealAttribute(): float
    {
        if ($this->diferencia !== null) {
            return (float) $this->diferencia;
        }

        return (float) ($this->efectivo_contado - $this->getEfectivoEsperadoCalculadoAttribute());
    }

    public function getMotivoDiferenciaLabelAttribute(): string
    {
        if (blank($this->motivo_diferencia)) {
            return 'Sin motivo registrado';
        }

        return self::MOTIVOS_DIFERENCIA[$this->motivo_diferencia]
            ?? ucfirst(str_replace('_', ' ', $this->motivo_diferencia));
    }
}

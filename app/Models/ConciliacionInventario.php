<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConciliacionInventario extends Model
{
    public const ESTADO_OK = 'ok';

    public const ESTADO_CON_DIFERENCIAS = 'con_diferencias';

    public const ORIGEN_COMANDO = 'comando';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_PROGRAMADA = 'programada';

    protected $table = 'conciliacion_inventario';

    protected $primaryKey = 'id_conciliacion_inventario';

    protected $fillable = [
        'fecha',
        'user_id',
        'origen',
        'total_productos',
        'productos_con_diferencia',
        'lotes_inconsistentes',
        'diferencia_total',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'diferencia_total' => 'decimal:3',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(
            DetalleConciliacionInventario::class,
            'conciliacion_inventario_id',
            'id_conciliacion_inventario'
        )->orderBy('id_detalle_conciliacion_inventario');
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_OK => 'Sin diferencias',
            self::ESTADO_CON_DIFERENCIAS => 'Con diferencias',
            default => ucfirst((string) $this->estado),
        };
    }

    public function getEstadoClaseAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_OK => 'bg-emerald-100 text-emerald-700',
            self::ESTADO_CON_DIFERENCIAS => 'bg-red-100 text-red-700',
            default => 'bg-stone-100 text-stone-700',
        };
    }

    public function getOrigenLabelAttribute(): string
    {
        return match ($this->origen) {
            self::ORIGEN_COMANDO => 'Comando',
            self::ORIGEN_MANUAL => 'Manual',
            self::ORIGEN_PROGRAMADA => 'Programada',
            default => ucfirst((string) $this->origen),
        };
    }
}

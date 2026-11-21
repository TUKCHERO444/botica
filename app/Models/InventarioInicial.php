<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioInicial extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADO = 'confirmado';

    protected $table = 'inventario_inicial';

    protected $primaryKey = 'id_inventario_inicial';

    protected $fillable = [
        'correlativo',
        'fecha',
        'user_id',
        'estado',
        'observaciones',
        'archivo_nombre',
        'archivo_hash',
        'total_lineas',
        'total_costo',
        'confirmado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'confirmado_at' => 'datetime',
            'total_costo' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(
            DetalleInventarioInicial::class,
            'inventario_inicial_id',
            'id_inventario_inicial'
        )->orderBy('id_detalle_inventario_inicial');
    }

    public function estaConfirmado(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADO;
    }

    public function getEstadoLabelAttribute(): string
    {
        return $this->estaConfirmado() ? 'Confirmado' : 'Borrador';
    }

    public function getEstadoClaseAttribute(): string
    {
        return $this->estaConfirmado()
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-amber-100 text-amber-700';
    }
}

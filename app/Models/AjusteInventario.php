<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteInventario extends Model
{
    public const TIPOS = [
        'conteo_fisico' => 'Conteo físico',
        'dano' => 'Daño',
        'vencimiento' => 'Vencimiento',
        'consumo_interno' => 'Consumo interno',
        'ajuste_positivo' => 'Ajuste positivo',
        'otro' => 'Otro',
    ];

    public const TIPOS_SALIDA = ['dano', 'vencimiento', 'consumo_interno'];

    protected $table = 'ajuste_inventario';

    protected $primaryKey = 'id_ajuste_inventario';

    protected $fillable = [
        'correlativo',
        'tipo',
        'fecha',
        'motivo',
        'observaciones',
        'total',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleAjusteInventario::class, 'ajuste_inventario_id', 'id_ajuste_inventario')
            ->orderBy('id_detalle_ajuste_inventario');
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? ucfirst((string) $this->tipo);
    }

    public function esEntradaPorTipo(): bool
    {
        return $this->tipo === 'ajuste_positivo';
    }

    public function esSalidaPorTipo(): bool
    {
        return in_array($this->tipo, self::TIPOS_SALIDA, true);
    }
}

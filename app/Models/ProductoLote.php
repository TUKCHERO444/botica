<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoLote extends Model
{
    protected $table = 'producto_lote';

    protected $fillable = [
        'correlativo',
        'producto_id',
        'codigo_lote',
        'fecha_vencimiento',
        'estado',
        'cantidad_inicial_base',
        'cantidad_disponible_base',
        'es_inicial',
    ];

    protected static function booted(): void
    {
        static::created(function (ProductoLote $lote) {
            if (blank($lote->correlativo)) {
                $lote->forceFill([
                    'correlativo' => sprintf('LOTE-%08d', $lote->id),
                ])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_inicial_base' => 'decimal:3',
            'cantidad_disponible_base' => 'decimal:3',
            'es_inicial' => 'boolean',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function capas()
    {
        return $this->hasMany(LoteIngreso::class, 'producto_lote_id');
    }

    public function consumos()
    {
        return $this->hasMany(LoteConsumo::class, 'producto_lote_id');
    }

    public function scopeDisponible($query)
    {
        return $query->where('estado', 'disponible')
            ->where('cantidad_disponible_base', '>', 0)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString());
    }

    public function getDiasParaVencerAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->startOfDay(), false);
    }

    public function getEstadoVisualAttribute(): string
    {
        if ($this->estado !== 'disponible') {
            return $this->estado;
        }

        $dias = $this->dias_para_vencer;

        return match (true) {
            $dias < 0 => 'vencido',
            $dias <= 7 => 'critico',
            $dias <= 30 => 'proximo',
            default => 'vigente',
        };
    }
}

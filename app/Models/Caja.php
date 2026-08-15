<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'caja';

    protected $primaryKey = 'id_caja';

    protected $fillable = [
        'monto_inicial',
        'total_ingresos',
        'total_egresos',
        'monto_final',
        'estado',
        'apertura_user_id',
        'cierre_user_id',
        'apertura_at',
        'cierre_at',
    ];

    protected function casts(): array
    {
        return [
            'monto_inicial' => 'decimal:2',
            'total_ingresos' => 'decimal:2',
            'total_egresos' => 'decimal:2',
            'monto_final' => 'decimal:2',
            'apertura_at' => 'datetime',
            'cierre_at' => 'datetime',
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

    public function movimientos()
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_id_caja', 'id_caja');
    }

    public function scopeAbierta($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function getMontoActualAttribute(): float
    {
        return (float)($this->monto_inicial + $this->total_ingresos - $this->total_egresos);
    }
}

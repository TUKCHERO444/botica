<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
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
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caja_id_caja', 'id_caja');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

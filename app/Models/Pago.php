<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    public const TIPO_VENTA = 'Venta';

    public const TIPO_ATENCION = 'Atencion';

    protected $table = 'pago';

    protected $primaryKey = 'id_pago';

    public $timestamps = true;

    protected $fillable = [
        'referencia_tipo',
        'referencia_id',
        'metodo_pago_id',
        'monto',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id', 'id_metodo_pago');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodo_pago';

    protected $primaryKey = 'id_metodo_pago';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'afecta_efectivo',
    ];

    protected static function booted(): void
    {
        static::saving(function (MetodoPago $metodo) {
            if ($metodo->afecta_efectivo === null) {
                // Por defecto solo el efectivo entra al cajón; el resto es digital.
                $metodo->afecta_efectivo = str_contains(mb_strtolower((string) $metodo->nombre), 'efectivo');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'afecta_efectivo' => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function scopeAfectaEfectivo($query)
    {
        return $query->where('afecta_efectivo', 1);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'metodo_pago_id', 'id_metodo_pago');
    }
}

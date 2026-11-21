<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedor';

    protected $primaryKey = 'id_proveedor';

    public $timestamps = false;

    protected $fillable = [
        'ruc',
        'razon_social',
        'condicion',
        'estado',
        'direccion',
        'ubigeo',
        'distrito',
        'provincia',
        'departamento',
        'lead_time',
    ];

    protected function casts(): array
    {
        return [
            'lead_time' => 'integer',
        ];
    }

    public function ingresos()
    {
        return $this->hasMany(Ingreso::class, 'proveedor_id_proveedor', 'id_proveedor');
    }
}

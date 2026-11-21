<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presentacion extends Model
{
    protected $table = 'presentacion';

    protected $primaryKey = 'id_presentacion';

    protected $fillable = [
        'nombre',
        'abreviatura',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function unidadesMedida()
    {
        return $this->hasMany(UnidadMedida::class, 'presentacion_base_id', 'id_presentacion');
    }

    public function productoPresentaciones()
    {
        return $this->hasMany(ProductoPresentacion::class, 'presentacion_id', 'id_presentacion');
    }
}

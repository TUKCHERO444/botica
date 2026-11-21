<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidad_medida';

    protected $primaryKey = 'id_unidad_medida';

    protected $fillable = [
        'codigo',
        'nombre',
        'permite_fraccion',
        'presentacion_base_id',
        'activo',
    ];

    protected $casts = [
        'permite_fraccion' => 'boolean',
        'activo' => 'boolean',
    ];

    public function presentacionBase()
    {
        return $this->belongsTo(Presentacion::class, 'presentacion_base_id', 'id_presentacion');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'unidad_base_id', 'id_unidad_medida');
    }
}

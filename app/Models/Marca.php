<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $table = 'marca';

    protected $primaryKey = 'id_marca';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'cantidad',
        'estado',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'marca_id_marca', 'id_marca');
    }
}

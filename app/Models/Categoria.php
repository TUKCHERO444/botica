<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categoria';

    protected $primaryKey = 'id_categoria';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'cantidad',
        'estado',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'categoria_id_categoria', 'id_categoria');
    }
}

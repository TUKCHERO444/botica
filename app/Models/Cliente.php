<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait;

    protected $table = 'cliente';

    protected $primaryKey = 'id_cliente';

    public $timestamps = true;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'telefono',
        'direccion',
        'fecha_nacimiento',
        'password',
        'registro_web',
        'ultimo_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'registro_web' => 'boolean',
            'ultimo_login' => 'datetime',
            'fecha_nacimiento' => 'date',
        ];
    }

    public function atenciones()
    {
        return $this->hasMany(Atencion::class, 'cliente_id_cliente', 'id_cliente');
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cliente_id_cliente', 'id_cliente');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    public function esGenerico(): bool
    {
        return $this->dni === '00000001'
            || mb_strtoupper(trim((string) $this->nombre)) === 'SIN IDENTIFICAR';
    }

    public function nombreParaTicket(): string
    {
        if ($this->esGenerico()) {
            return 'CLIENTE GENERAL';
        }

        return $this->nombre_completo !== '' ? $this->nombre_completo : 'CLIENTE GENERAL';
    }

    public function dniParaTicket(): string
    {
        return $this->esGenerico() || blank($this->dni) ? '---' : $this->dni;
    }

    public function carrito()
    {
        return $this->hasOne(Carrito::class, 'cliente_id_cliente', 'id_cliente');
    }
}

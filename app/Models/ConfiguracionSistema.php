<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class ConfiguracionSistema extends Model
{
    protected $table = 'configuracion_sistema';

    protected $primaryKey = 'id_configuracion';

    public $timestamps = true;

    protected $fillable = [
        'nombre_negocio',
    ];

    protected function casts(): array
    {
        return [
            'nombre_negocio' => 'string',
        ];
    }

    /**
     * Devuelve la configuración existente o una instancia con el valor por defecto.
     */
    public static function actual(): self
    {
        return static::query()->first() ?? new static([
            'nombre_negocio' => config('ticket.negocio', 'Botica'),
        ]);
    }

    /**
     * Nombre visible del negocio, con respaldo en la configuración del ticket.
     */
    public static function nombreNegocio(): string
    {
        try {
            $nombre = static::query()->value('nombre_negocio');
        } catch (QueryException) {
            $nombre = null;
        }

        return filled($nombre) ? $nombre : config('ticket.negocio', 'Botica');
    }

    public static function guardarNombre(string $nombre): self
    {
        $configuracion = static::query()->first() ?? new static;
        $configuracion->nombre_negocio = trim($nombre);
        $configuracion->save();

        return $configuracion;
    }
}

<?php

namespace App\Support;

use App\Exceptions\JsonPe\ConsumoApiBloqueadoException;

/**
 * Bloquea el consumo de APIs externas desde comandos de base de datos.
 *
 * Regla del proyecto: los seeders y las migraciones deben trabajar solo con
 * datos locales. Consumir APIs externas en esos procesos agota tokens y hace
 * que `migrate`/`db:seed` dependan de un servicio de terceros. Las APIs solo
 * se consumen desde peticiones HTTP de la aplicación (controladores/servicios).
 */
class ApiExternaGuard
{
    private const COMANDOS_BLOQUEADOS = [
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'migrate:status',
        'db:seed',
        'db:wipe',
        'db:monitor',
        'schema:dump',
    ];

    public static function debeBloquear(?string $comando = null): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $comando ??= self::comandoActual();

        return $comando !== null && in_array($comando, self::COMANDOS_BLOQUEADOS, true);
    }

    /**
     * @throws ConsumoApiBloqueadoException
     */
    public static function verificar(string $servicio): void
    {
        if (! self::debeBloquear()) {
            return;
        }

        throw new ConsumoApiBloqueadoException(sprintf(
            'El comando "%s" no puede consumir la API de %s. Los seeders y migraciones deben usar datos locales.',
            self::comandoActual() ?? 'consola',
            $servicio
        ));
    }

    private static function comandoActual(): ?string
    {
        foreach ($_SERVER['argv'] ?? [] as $indice => $argumento) {
            if ($indice === 0 || ! is_string($argumento) || $argumento === '') {
                continue;
            }

            if (str_starts_with($argumento, '-')) {
                continue;
            }

            return $argumento;
        }

        return null;
    }
}

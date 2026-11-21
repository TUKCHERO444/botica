<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Regla del proyecto: migraciones, seeders y factories no consumen APIs externas.
 */
class SeedersSinConsumoApiTest extends TestCase
{
    private const PATRONES_PROHIBIDOS = [
        'Http::',
        'ReniecService',
        'SunatService',
        'JsonPeClient',
        'curl_init',
        'GuzzleHttp',
        "file_get_contents('http",
        'file_get_contents("http',
    ];

    public function test_migraciones_y_seeders_no_consumen_apis_externas(): void
    {
        $archivos = array_merge(
            File::allFiles(database_path('migrations')),
            File::allFiles(database_path('seeders')),
            File::allFiles(database_path('factories')),
        );

        $this->assertNotEmpty($archivos, 'No se encontraron migraciones, seeders ni factories para revisar.');

        foreach ($archivos as $archivo) {
            $contenido = File::get($archivo->getPathname());

            foreach (self::PATRONES_PROHIBIDOS as $patron) {
                $this->assertStringNotContainsString(
                    $patron,
                    $contenido,
                    sprintf(
                        '%s contiene "%s". Las migraciones, seeders y factories deben usar solo datos locales.',
                        str_replace(base_path().DIRECTORY_SEPARATOR, '', $archivo->getPathname()),
                        $patron
                    )
                );
            }
        }
    }
}

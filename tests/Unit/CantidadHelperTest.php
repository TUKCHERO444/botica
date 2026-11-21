<?php

namespace Tests\Unit;

use App\Helpers\CantidadHelper;
use PHPUnit\Framework\TestCase;

class CantidadHelperTest extends TestCase
{
    public function test_convierte_cantidad_a_unidad_base(): void
    {
        $this->assertSame('105.000', CantidadHelper::aBase('1.05', 100));
        $this->assertSame('0.500', CantidadHelper::aBase('0.5', 1));
        $this->assertSame('10.000', CantidadHelper::aBase(1, 10));
    }

    public function test_compara_y_suma_cantidades_decimales(): void
    {
        $this->assertSame(0, CantidadHelper::comparar('250.000', '250'));
        $this->assertSame(1, CantidadHelper::comparar('250.001', '250'));
        $this->assertSame('249.500', CantidadHelper::restar('250', '0.5'));
        $this->assertSame('250.500', CantidadHelper::sumar('250', '0.5'));
    }

    public function test_valida_multiplos_del_paso(): void
    {
        $this->assertTrue(CantidadHelper::esMultiplo('1.5', '0.5'));
        $this->assertTrue(CantidadHelper::esMultiplo('2', '0.5'));
        $this->assertFalse(CantidadHelper::esMultiplo('1.25', '0.5'));
    }

    public function test_formatea_sin_ceros_sobrantes(): void
    {
        $this->assertSame('100', CantidadHelper::formatear('100.000'));
        $this->assertSame('0.5', CantidadHelper::formatear('0.500'));
        $this->assertSame('0', CantidadHelper::formatear(null));
    }

    public function test_redondea_totales_a_dos_decimales(): void
    {
        $this->assertSame('54.00', CantidadHelper::redondear('54', 2));
        $this->assertSame('0.40', CantidadHelper::redondear('0.4', 2));
    }
}

<?php

namespace Tests\Unit;

use App\Services\AnalisisDemandaService;
use PHPUnit\Framework\TestCase;

class AnalisisDemandaServiceTest extends TestCase
{
    public function test_calcula_demanda_seguridad_faltantes_y_cobertura(): void
    {
        $resultado = (new AnalisisDemandaService)->proyeccion(10, 10, 7, .20, 4);

        $this->assertSame(9, $resultado['objetivo']);
        $this->assertSame(5, $resultado['faltantes']);
        $this->assertSame(4.0, $resultado['cobertura']);
        $this->assertSame('bajo', $resultado['estado']);
    }

    public function test_no_divide_por_cero_ni_recomienda_negativos(): void
    {
        $resultado = (new AnalisisDemandaService)->proyeccion(0, 30, 30, .20, 20);

        $this->assertNull($resultado['cobertura']);
        $this->assertSame(0, $resultado['faltantes']);
        $this->assertSame('sin_demanda', $resultado['estado']);
    }

    public function test_clasifica_agotado_con_demanda(): void
    {
        $resultado = (new AnalisisDemandaService)->proyeccion(1, 7, 7, .20, 0);

        $this->assertSame('agotado', $resultado['estado']);
    }
}

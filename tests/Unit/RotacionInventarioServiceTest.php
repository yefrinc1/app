<?php

namespace Tests\Unit;

use App\Services\RotacionInventarioService;
use PHPUnit\Framework\TestCase;

class RotacionInventarioServiceTest extends TestCase
{
    public function test_clasifica_rotacion_segun_cobertura(): void
    {
        $servicio = new RotacionInventarioService;

        $this->assertSame('alta', $servicio->estado(10, 20, 60));
        $this->assertSame('media', $servicio->estado(10, 45, 60));
        $this->assertSame('lenta', $servicio->estado(10, 90, 60));
        $this->assertSame('detenida', $servicio->estado(10, 150, 60));
    }

    public function test_distingue_inventario_nuevo_sin_ventas_del_detenido(): void
    {
        $servicio = new RotacionInventarioService;

        $this->assertSame('nuevo', $servicio->estado(0, null, 15));
        $this->assertSame('detenida', $servicio->estado(0, null, 31));
    }
}

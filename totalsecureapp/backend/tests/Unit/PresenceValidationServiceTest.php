<?php

namespace Tests\Unit;

use App\Services\PresenceValidationService;
use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PresenceValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PresenceValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PresenceValidationService();
    }

    /** @test */
    public function calcula_distancia_entre_dos_puntos_correctamente()
    {
        $distancia = $this->service->calcularDistancia(
            -0.1807, -78.4678,
            -0.1816, -78.4678
        );

        $this->assertGreaterThan(90, $distancia);
        $this->assertLessThan(110, $distancia);
    }

    /** @test */
    public function punto_dentro_de_geocerca_retorna_true()
    {
        $resultado = $this->service->dentroDeGeocerca(
            -0.1807, -78.4678,
            -0.1807, -78.4678,
            100
        );

        $this->assertTrue($resultado);
    }

    /** @test */
    public function punto_fuera_de_geocerca_retorna_false()
    {
        $resultado = $this->service->dentroDeGeocerca(
            -0.1807, -78.4678,
            -0.1850, -78.4678,
            100
        );

        $this->assertFalse($resultado);
    }

    /** @test */
    public function qr_invalido_retorna_error()
    {
        $marcador = $this->service->descifrarQR('codigo_invalido', 1);

        $this->assertNull($marcador);
    }
}
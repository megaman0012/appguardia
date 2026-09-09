<?php

namespace Tests\Unit;

use App\Filament\Pages\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Session;
use Tests\TestCase;

/**
 * Quien puede tocar la configuracion de correo y WhatsApp.
 *
 * ⚠️ **No es una preferencia de menu.** Quien edita el SMTP puede redirigir los
 * correos de restablecimiento de contraseña de 880 personas a un servidor
 * propio, y quedarse con las cuentas. Un Supervisor no tiene por que llegar.
 *
 * Y ocultar el enlace del menu **no alcanza**: la pagina se alcanza escribiendo
 * `/admin/configuracion`. Por eso se prueba `canAccess()`, que es lo que
 * Filament consulta al servir la ruta, y no solo la navegacion.
 */
class ConfiguracionSoloAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('perfilesQueNoDeben')]
    public function un_perfil_que_no_es_administrador_no_entra(string $perfil): void
    {
        Session::put('usuPF', $perfil);

        $this->assertFalse(Configuracion::canAccess(), "«{$perfil}» no debería poder abrir la configuración.");
        $this->assertFalse(Configuracion::shouldRegisterNavigation(), "«{$perfil}» no debería ver el enlace.");
    }

    /** @return array<string, array{string}> */
    public static function perfilesQueNoDeben(): array
    {
        return [
            'Supervisor'      => ['Supervisor'],
            'Vigilante'       => ['Vigilante'],
            'Cliente'         => ['Cliente'],
            'Lider Operativo' => ['Lider Operativo'],
            'Consola'         => ['Consola'],
            'sin perfil'      => [''],
        ];
    }

    #[Test]
    public function el_administrador_entra(): void
    {
        Session::put('usuPF', 'Administrador');

        $this->assertTrue(Configuracion::canAccess());
        $this->assertTrue(Configuracion::shouldRegisterNavigation());
    }

    #[Test]
    public function el_administrador_general_tambien(): void
    {
        Session::put('usuPF', 'Administrador General');

        $this->assertTrue(Configuracion::canAccess());
    }
}

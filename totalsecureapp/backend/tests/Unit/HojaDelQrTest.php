<?php

namespace Tests\Unit;

use App\generalTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Tests\TestCase;

/**
 * Que la hoja imprimible del marcador salga CON el codigo QR.
 *
 * ⚠️ Salia sin el. El PDF traia el formato, el recuadro y el texto, y el codigo
 * no aparecia: la parte que hace util a la hoja.
 *
 * La causa no estaba en la generacion del QR --que siempre funciono-- sino en
 * dompdf. La plantilla embebe el codigo como `data:image/png;base64,...`, y
 * **desde dompdf 2.0 el esquema `data:` pasa por `allowed_protocols`**, donde no
 * estaba declarado. dompdf descartaba la imagen EN SILENCIO: sin error, sin
 * aviso en el log, sin nada. Lo destapo la migracion a Laravel 13, que trajo
 * dompdf 3.
 *
 * El test compara contra la configuracion anterior en vez de contar imagenes
 * contra un numero fijo: asi sigue diciendo la verdad aunque alguien agregue un
 * logo a la plantilla.
 */
class HojaDelQrTest extends TestCase
{
    use generalTrait;

    private function hoja(): string
    {
        $marcador = (object) ['im_numero' => 1, 'im_descripcion' => 'Puerta 1', 'im_tipo' => 'QR'];
        $local    = (object) ['ins_descripcion' => 'Local de prueba'];

        return PDF::loadView('administracion::qrcode.pointcontrol', [
            'qrcode' => $this->generateQrCode('PRUEBA_123'),
            'marc'   => $marcador,
            'inst'   => $local,
        ])->output();
    }

    private function imagenesEn(string $pdf): int
    {
        return substr_count($pdf, '/Subtype /Image');
    }

    public function test_el_qr_se_genera_como_data_uri(): void
    {
        $this->assertStringStartsWith('data:image/png;base64,', $this->generateQrCode('X'));
    }

    public function test_la_hoja_incluye_el_codigo_y_no_solo_el_formato(): void
    {
        $conDataUri = $this->imagenesEn($this->hoja());

        // La configuracion tal como estaba: sin `data://` declarado.
        config(['dompdf.options.allowed_protocols' => [
            'file://'  => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ]]);

        $sinDataUri = $this->imagenesEn($this->hoja());

        $this->assertGreaterThan(
            $sinDataUri,
            $conDataUri,
            'El PDF tiene las mismas imagenes con y sin `data://`: el QR se esta descartando.'
        );
    }

    /**
     * Declarar `data://` no puede haber reabierto la carga remota, que es la
     * razon por la que `enable_remote` esta apagado.
     */
    public function test_la_carga_remota_sigue_apagada(): void
    {
        $this->assertFalse(config('dompdf.options.enable_remote'));
    }
}

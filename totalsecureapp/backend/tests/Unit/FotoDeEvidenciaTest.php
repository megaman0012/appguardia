<?php

namespace Tests\Unit;

use App\Support\FotoDeEvidencia;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Que la foto de una evidencia se encuentre aunque la sincronizacion llegue tarde.
 *
 * ⚠️ `storeFiles()` armaba la carpeta con la hora del SERVIDOR y los modelos la
 * reconstruian con la fecha del HECHO, que manda el dispositivo. Mientras las
 * dos caen el mismo dia todo funciona; **una marcacion de las 23:50 que la
 * tablet sincroniza a las 00:10 dejaba la foto en una carpeta y el registro
 * apuntando a otra**, y la foto no se veia en ninguna parte -- sin error, sin
 * log, sin nada que revisar.
 *
 * Medido en produccion el 2026-09-15: el desperfecto **no habia llegado a
 * afectar ninguna** de las 45.341 fotos. El arreglo es para que no pueda pasar,
 * no para reparar un destrozo.
 */
class FotoDeEvidenciaTest extends TestCase
{
    private array $creados = [];

    protected function tearDown(): void
    {
        foreach ($this->creados as $ruta) {
            File::delete($ruta);
        }

        parent::tearDown();
    }

    private function ponerFoto(string $relativa): void
    {
        $absoluta = public_path('images/' . $relativa);
        File::ensureDirectoryExists(dirname($absoluta));
        File::put($absoluta, 'prueba');
        $this->creados[] = $absoluta;
    }

    /** La forma nueva: la ruta viene completa y no hay nada que reconstruir. */
    public function test_una_ruta_relativa_se_usa_tal_cual(): void
    {
        $this->ponerFoto('novedad/2026/09/15/foto_nueva.jpg');

        $this->assertSame(
            'novedad/2026/09/15/foto_nueva.jpg',
            FotoDeEvidencia::rutaRelativa('novedad/2026/09/15/foto_nueva.jpg', 'novedad', '2026-09-15 10:00:00')
        );
    }

    /** La forma vieja: solo el nombre, y la carpeta sale de la fecha. */
    public function test_solo_el_nombre_se_reconstruye_con_la_fecha(): void
    {
        $this->ponerFoto('rondas/2026/09/15/vieja.jpg');

        $this->assertSame(
            'rondas/2026/09/15/vieja.jpg',
            FotoDeEvidencia::rutaRelativa('vieja.jpg', 'rondas', '2026-09-15 10:00:00')
        );
    }

    /**
     * EL CASO DEL DESPERFECTO: el hecho es del dia 15 a las 23:50 y la foto
     * quedo guardada en la carpeta del 16, porque el servidor la recibio pasada
     * la medianoche.
     */
    public function test_encuentra_la_foto_que_quedo_en_el_dia_siguiente(): void
    {
        $this->ponerFoto('novedad/2026/09/16/desfasada.jpg');

        $this->assertSame(
            'novedad/2026/09/16/desfasada.jpg',
            FotoDeEvidencia::rutaRelativa('desfasada.jpg', 'novedad', '2026-09-15 23:50:00')
        );
    }

    public function test_encuentra_la_foto_que_quedo_en_el_dia_anterior(): void
    {
        $this->ponerFoto('novedad/2026/09/14/desfasada2.jpg');

        $this->assertSame(
            'novedad/2026/09/14/desfasada2.jpg',
            FotoDeEvidencia::rutaRelativa('desfasada2.jpg', 'novedad', '2026-09-15 00:10:00')
        );
    }

    /**
     * El margen es corto a proposito: dos fotos de dias distintos pueden
     * llamarse igual, porque el nombre solo lleva el usuario y una marca de
     * tiempo. Buscar mas lejos seria empezar a adivinar.
     */
    public function test_no_busca_indefinidamente(): void
    {
        $this->ponerFoto('novedad/2026/09/01/lejana.jpg');

        $this->assertNull(
            FotoDeEvidencia::rutaRelativa('lejana.jpg', 'novedad', '2026-09-15 10:00:00')
        );
    }

    public function test_sin_foto_no_devuelve_nada(): void
    {
        $this->assertNull(FotoDeEvidencia::rutaRelativa('', 'novedad', '2026-09-15'));
        $this->assertNull(FotoDeEvidencia::rutaRelativa(null, 'novedad', '2026-09-15'));
    }

    public function test_una_foto_que_no_esta_en_disco_no_devuelve_ruta(): void
    {
        $this->assertNull(
            FotoDeEvidencia::rutaRelativa('no_existe.jpg', 'novedad', '2026-09-15 10:00:00')
        );
    }

    /** Una fecha ilegible no puede reventar el listado entero. */
    public function test_una_fecha_invalida_no_rompe(): void
    {
        $this->assertNull(FotoDeEvidencia::rutaRelativa('x.jpg', 'novedad', 'no es una fecha'));
        $this->assertNull(FotoDeEvidencia::rutaRelativa('x.jpg', 'novedad', null));
    }

    public function test_la_url_apunta_a_images(): void
    {
        $this->ponerFoto('biometria/2026/09/15/u.jpg');

        $this->assertStringContainsString(
            'images/biometria/2026/09/15/u.jpg',
            FotoDeEvidencia::url('u.jpg', 'biometria', '2026-09-15 08:00:00')
        );
    }
}

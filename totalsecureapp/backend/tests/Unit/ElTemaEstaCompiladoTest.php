<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * El tema del panel tiene que estar compilado Y versionado.
 *
 * ⚠️ **Esto no es cosmético: es el panel entero.** Al registrar
 * `->viteTheme(...)`, Filament pide el manifiesto de Vite en cada página. Si no
 * está, no salen «páginas sin estilos»: sale un **500 en todas**.
 *
 * Y acá el árbol de trabajo *es* producción, donde desplegar es `git pull`. Si
 * `public/build/` no estuviera versionado, el panel dependería de que
 * `npm run build` corriera bien en el servidor en cada despliegue. Por eso el
 * `.gitignore` tiene una excepción explícita para esa carpeta.
 *
 * Si este test falla, lo que falta es:
 *
 *     npm run build && git add public/build
 */
class ElTemaEstaCompiladoTest extends TestCase
{
    private const ENTRADA = 'resources/css/filament/admin/theme.css';

    public function test_el_manifiesto_existe(): void
    {
        $this->assertFileExists(
            public_path('build/manifest.json'),
            'Sin el manifiesto de Vite, Filament devuelve 500 en TODAS las páginas del panel. '
            . 'Corra `npm run build` y versione `public/build/`.'
        );
    }

    public function test_el_manifiesto_apunta_a_un_css_que_existe(): void
    {
        $manifiesto = json_decode(file_get_contents(public_path('build/manifest.json')), true);

        $this->assertArrayHasKey(self::ENTRADA, $manifiesto,
            'El manifiesto no tiene la entrada del tema: la compilación salió de otra configuración.');

        $this->assertFileExists(
            public_path('build/' . $manifiesto[self::ENTRADA]['file']),
            'El manifiesto apunta a un archivo que no está versionado.'
        );
    }

    /**
     * El CSS compilado tiene que corresponder al fuente.
     *
     * No compara el contenido --sería frágil--, sino que **el fuente no sea más
     * nuevo que el compilado**. Cazá el caso real: alguien toca el tema, lo ve
     * bien en su máquina porque el servidor de desarrollo lo recompila al vuelo,
     * y sube solo el `.css` fuente. En producción no hay servidor de
     * desarrollo: se sirve el compilado viejo y el cambio no aparece.
     */
    public function test_el_compilado_no_esta_mas_viejo_que_el_fuente(): void
    {
        $fuente     = base_path(self::ENTRADA);
        $manifiesto = public_path('build/manifest.json');

        $this->assertGreaterThanOrEqual(
            filemtime($fuente),
            filemtime($manifiesto),
            'El tema se modificó después de la última compilación. Corra `npm run build`.'
        );
    }

    /**
     * ⚠️ `public/hot` no puede llegar a producción.
     *
     * Lo crea `npm run dev`. Si existe, Filament pide los assets a un
     * `localhost:5173` que en el servidor no responde, y el panel queda sin
     * estilos. Está en el `.gitignore`, pero un `git add -f` o una copia manual
     * lo colarían.
     */
    public function test_no_hay_marcador_del_servidor_de_desarrollo(): void
    {
        $this->assertFileDoesNotExist(
            public_path('hot'),
            'public/hot hace que Filament pida los assets al servidor de desarrollo de Vite. '
            . 'En este servidor eso deja el panel sin estilos.'
        );
    }
}

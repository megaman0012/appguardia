<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guarda contra los nombres de ruta de Filament 2.
 *
 * **Por que existe, y por que es un test de texto y no de comportamiento.**
 * Dos listados (Locales y Gestiones) respondian 500 en produccion mientras los
 * 389 tests pasaban en verde. La causa era un `route('filament.resources.…')`
 * escrito a mano: en Filament 2 ese era el nombre, y desde Filament 3 el nombre
 * lleva el id del panel en el medio (`filament.admin.resources.…`), asi que
 * `route()` lanza «Route not defined» y se cae el listado entero -- porque la
 * URL se resuelve al DIBUJAR cada fila, no al hacer clic.
 *
 * `PanelSeDibujaTest` no lo vio y no podia verlo: corre con `RefreshDatabase` y
 * sin datos, asi que las tablas salen VACIAS y una clausura `fn ($record) => …`
 * de una accion de fila nunca llega a ejecutarse. Un listado sin filas se dibuja
 * perfecto con las acciones rotas.
 *
 * Sembrar una fila por cada uno de los 27 recursos seria la prueba de verdad,
 * pero son modelos heredados de coredt360 con columnas obligatorias y llaves
 * cruzadas; es un trabajo aparte. Mientras tanto esto cuesta milisegundos y
 * cierra exactamente la puerta por la que entro el error.
 *
 * **La forma correcta es `self::getUrl('edit', ['record' => $record])`**: se lo
 * pregunta al propio recurso, asi que sobrevive a un cambio de id del panel y a
 * que la pagina se renombre.
 */
class RutasDelPanelTest extends TestCase
{
    /** Carpetas donde puede haber codigo del panel. */
    private const CARPETAS = [
        __DIR__ . '/../../app',
        __DIR__ . '/../../Modules',
    ];

    #[Test]
    public function ningun_archivo_usa_los_nombres_de_ruta_de_filament_2(): void
    {
        $culpables = [];

        foreach (self::archivosPhp() as $archivo) {
            // Se recorre por TOKENS y no por texto. Buscar la cadena a secas
            // hace que el comentario que explica esta misma trampa se denuncie a
            // si mismo -- me paso al escribir este test. El tokenizador sabe que
            // es una cadena de codigo y que es un comentario.
            foreach (token_get_all(file_get_contents($archivo)) as $token) {
                if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                if (str_starts_with(trim($token[1], '\'"'), 'filament.resources.')) {
                    $culpables[] = str_replace(dirname(__DIR__, 2) . '/', '', $archivo) . ':' . $token[2];
                }
            }
        }

        $this->assertSame([], $culpables, implode("\n", array_merge(
            ['Nombre de ruta de Filament 2. Reemplazar por self::getUrl(...):'],
            $culpables
        )));
    }

    #[Test]
    public function las_paginas_que_referencian_las_acciones_existen(): void
    {
        // La contracara: `getUrl()` no protege de pedir una pagina que el recurso
        // no declara. `edit1` en Gestiones existe; `edit` NO. Un `getUrl('edit')`
        // ahi volveria a romper el listado, y otra vez sin que los tests lo vean.
        $faltantes = [];

        foreach (glob(__DIR__ . '/../../app/Filament/Resources/*Resource.php') as $archivo) {
            $recurso = 'App\\Filament\\Resources\\' . basename($archivo, '.php');

            if (!class_exists($recurso) || !method_exists($recurso, 'getPages')) {
                continue;
            }

            $declaradas = array_keys($recurso::getPages());

            if (preg_match_all('/getUrl\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', file_get_contents($archivo), $m)) {
                foreach (array_unique($m[1]) as $pedida) {
                    if (!in_array($pedida, $declaradas, true)) {
                        $faltantes[] = class_basename($recurso) . " pide '{$pedida}', declara: " . implode(', ', $declaradas);
                    }
                }
            }
        }

        $this->assertSame([], $faltantes, implode("\n", array_merge(
            ['Una accion apunta a una pagina que el recurso no declara:'],
            $faltantes
        )));
    }

    /** @return list<string> */
    private static function archivosPhp(): array
    {
        $archivos = [];

        foreach (self::CARPETAS as $carpeta) {
            if (!is_dir($carpeta)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta));

            foreach ($it as $archivo) {
                if ($archivo->isFile() && $archivo->getExtension() === 'php') {
                    $archivos[] = $archivo->getPathname();
                }
            }
        }

        return $archivos;
    }
}

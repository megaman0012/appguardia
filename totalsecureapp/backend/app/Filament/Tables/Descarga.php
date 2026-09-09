<?php

namespace App\Filament\Tables;

// ⚠️ La de **Pages**, no la de Tables. El paquete trae las dos y se parecen,
// pero solo la de Pages extiende `Filament\Pages\Actions\Action`: con la de
// Tables, Filament 2 revienta al dibujar la cabecera con «Method
// ExportAction::livewire does not exist». Filament 2 no tiene acciones de
// cabecera en la tabla -- las de arriba salen del `getActions()` de la pagina.
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Descarga a Excel de cualquier listado del panel.
 *
 * **El problema.** `pxlrbt/filament-excel` ya estaba instalado, pero solo con
 * `ExportBulkAction` y solo en 6 de 27 pantallas. Una accion masiva **exige
 * tildar filas primero** y exporta unicamente lo tildado, con la casilla de
 * «seleccionar todo» limitada a la pagina visible (25 filas). Para llevarse las
 * 37.617 filas de un reporte de rondas habia que paginar y tildar 1.505 veces.
 * De ahi que se leyera como «reporteria no tiene opcion de descarga»: la que
 * habia no servia para descargar un reporte.
 *
 * Aca hay dos acciones y se ponen las dos:
 *
 *  - `enCabecera()` — un boton **Descargar** arriba, que se lleva el resultado
 *    **completo de la consulta tal como esta filtrada**, sin tildar nada.
 *  - `enLote()` — la accion masiva de siempre, para cuando se quieren unas
 *    pocas filas concretas.
 *
 * ### `fromTable()`: las columnas que se ven, con sus nombres
 *
 * Los exports que ya existian llamaban `ExportBulkAction::make()` sin
 * configurar nada, y eso volcaba **todos los atributos del modelo con el nombre
 * crudo de la columna**: un cliente recibia un Excel con encabezados
 * `al_ins_code`, `al_estado_alerta`, `al_created_user`. Con `fromTable()` el
 * archivo lleva exactamente las columnas visibles del listado y las etiquetas
 * en castellano que ya tienen definidas.
 *
 * ### El alcance se respeta solo
 *
 * El export corre sobre la consulta del listado, que ya pasa por
 * `getEloquentQuery()` de cada recurso y por `PerfilPanel`. Un Lider Operativo
 * descarga su pais, no la base entera: no hay que volver a filtrar aca, y
 * conviene no hacerlo para que no se desincronice del listado.
 */
class Descarga
{
    /**
     * Cuantas filas se leen por vuelta.
     *
     * `QUEUE_CONNECTION=sync` y no hay worker, asi que el archivo se arma
     * durante la peticion. Con 500 filas por lote la memoria queda acotada:
     * 37.617 filas de rondas se leen en 76 vueltas en vez de entrar todas a la
     * vez en los 512 MB de PHP.
     */
    private const LOTE = 500;

    /**
     * @param string $nombre Base del archivo, sin extension ni fecha.
     */
    public static function enCabecera(string $nombre): ExportAction
    {
        return ExportAction::make('descargar')
            ->label('Descargar')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('secondary')
            ->before(fn () => self::margenDeMemoria())
            ->exports([self::hoja($nombre)]);
    }

    public static function enLote(string $nombre): ExportBulkAction
    {
        return ExportBulkAction::make('descargar')
            ->label('Descargar selección')
            ->icon('heroicon-o-arrow-down-tray')
            ->before(fn () => self::margenDeMemoria())
            ->exports([self::hoja($nombre)]);
    }

    /**
     * Sube el techo de memoria **solo para esta peticion**.
     *
     * Medido con el listado mas grande que hay hoy, «Locales por usuario» con
     * 38.247 filas: el archivo sale en **22 s con un pico de 263 MB**, sobre un
     * `memory_limit` de 512 MB. Funciona, pero deja poco margen: **dos
     * descargas grandes a la vez agotan la memoria** y las dos fallan.
     *
     * El pico no lo pone el escritor del Excel sino la hidratacion de los
     * modelos -- recorrer las mismas 38.247 filas por lotes sin exportar usa 46
     * MB, y en CSV el pico sigue en 214 MB. Bajar `LOTE` no lo arregla.
     *
     * ⚠️ **Esto es un parche con fecha de caducidad.** Lo correcto es encolar
     * la descarga (`->queue()` del propio paquete), y para eso hace falta un
     * worker: hoy `QUEUE_CONNECTION=sync` y no hay ninguno corriendo. Mientras
     * no lo haya, esto evita que la descarga de un reporte tumbe la peticion.
     *
     * El tiempo no es problema: `max_execution_time` es 0 y el nginx del host
     * da 300 s de `proxy_read_timeout`.
     */
    private static function margenDeMemoria(): void
    {
        if (self::aBytes(ini_get('memory_limit')) < self::aBytes('1G')) {
            @ini_set('memory_limit', '1G');
        }
    }

    private static function aBytes(string $valor): int
    {
        $valor = trim($valor);

        if ($valor === '-1') {
            // Sin limite: no hay nada que subir.
            return PHP_INT_MAX;
        }

        $unidad = strtolower(substr($valor, -1));
        $numero = (int) $valor;

        return match ($unidad) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    private static function hoja(string $nombre): ExcelExport
    {
        return ExcelExport::make()
            ->fromTable()
            ->withChunkSize(self::LOTE)
            // La fecha en el nombre evita el «descarga (3).xlsx» y deja claro a
            // que dia corresponde el corte.
            ->withFilename(fn () => $nombre . '-' . now()->format('Y-m-d-Hi'));
    }
}

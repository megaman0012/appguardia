<?php

namespace App\Filament\Pages;

use App\Filament\Tables\Descarga;
use Filament\Resources\Pages\ListRecords;

/**
 * Base de todas las pantallas de listado del panel.
 *
 * **Existe para quitar el rastro de migas de pan de los listados.**
 *
 * En un listado, `Filament\Resources\Pages\Page::getBreadcrumbs()` devuelve dos
 * eslabones: el nombre del recurso y el titulo de la pagina -- «Novedades /
 * Listado». El segundo es literalmente el encabezado que la pagina ya muestra
 * debajo, y el primero apunta a la pagina en la que ya estas. No informa nada y
 * ocupa la franja superior entera.
 *
 * En crear y editar **si** sirven, y ahi se dejan: el primer eslabon es el
 * camino de vuelta al listado, que es la unica forma comoda de salir de un
 * formulario sin guardar.
 *
 * Va como clase base y no como override de la vista Blade de Filament porque la
 * vista recibe solo el arreglo de migas: no sabe si esta en un listado o en un
 * formulario, asi que desde ahi no se puede distinguir un caso del otro sin
 * adivinar.
 */
abstract class ListadoBase extends ListRecords
{
    /**
     * ⚠️ **`public`, no `protected`.** En Filament 3 `Page::getBreadcrumbs()`
     * es publico, y bajarle la visibilidad es un **error fatal de PHP** al
     * cargar la clase: «Access level must be public». Tumbaba la aplicacion
     * entera antes de servir nada.
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Acciones de la cabecera: las propias del listado mas la descarga.
     *
     * ⚠️ **Las pantallas NO deben sobrescribir esto**, sino
     * `accionesPropias()`. Las 27 sobrescribian `getActions()` directamente, y
     * agregar la descarga a cada una habria sido copiar la misma linea 27 veces
     * -- con la garantia de que el listado numero 28 se olvida.
     */
    protected function getHeaderActions(): array
    {
        $acciones = $this->accionesPropias();

        if ($this->nombreDeDescarga() !== null) {
            $acciones[] = Descarga::enCabecera($this->nombreDeDescarga());
        }

        return $acciones;
    }

    /**
     * Lo que cada listado quiera poner arriba: «Crear», «Volver a Rondas»…
     *
     * @return array<int,mixed>
     */
    protected function accionesPropias(): array
    {
        return [];
    }

    /**
     * Base del nombre del archivo, o `null` para no ofrecer descarga.
     *
     * Por defecto se deriva del recurso, asi que un listado nuevo tiene
     * descarga sin hacer nada. Devolver `null` es para los listados donde no
     * tiene sentido -- una pantalla de configuracion, por ejemplo.
     */
    protected function nombreDeDescarga(): ?string
    {
        return \Illuminate\Support\Str::slug(
            (string) (static::$resource)::getPluralModelLabel()
        );
    }
}

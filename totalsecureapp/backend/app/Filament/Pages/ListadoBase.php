<?php

namespace App\Filament\Pages;

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
    protected function getBreadcrumbs(): array
    {
        return [];
    }
}

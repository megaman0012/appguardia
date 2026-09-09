<?php

/*
|--------------------------------------------------------------------------
| Lo que queda de config/filament.php
|--------------------------------------------------------------------------
|
| En Filament 2 este archivo configuraba el panel entero. En Filament 3 el
| panel se declara en `App\Providers\Filament\AdminPanelProvider` y de este
| archivo sobreviven solo unas pocas claves que la libreria sigue leyendo
| directamente del config.
|
| El resto -- ruta, marca, guard, ancho de contenido, middleware, grupos del
| menu, descubrimiento de recursos -- se mudo al proveedor. Ver la tabla de
| equivalencias que esta documentada ahi.
|
*/

return [

    /*
     * Disco donde Filament guarda lo que se sube desde el panel. Era
     * `default_filesystem_disk` y es la unica clave del archivo viejo que
     * **no** tiene equivalente encadenable en el `Panel`.
     */
    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

    /*
     * Cache de los componentes que Filament descubre. `null` deja el valor por
     * defecto de la libreria.
     */
    'assets_path' => null,

    'cache_path' => base_path('bootstrap/cache/filament'),

    'livewire_loading_delay' => 'default',

];

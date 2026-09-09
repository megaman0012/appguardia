<?php

namespace App\Filament\Tables;

use Closure;

/**
 * Traduce el valor guardado en la base a la etiqueta que se muestra.
 *
 * ⚠️ **Reemplaza a `BadgeColumn::enum()`, que Filament 3 elimino.** En la
 * version 2 se escribia:
 *
 *     BadgeColumn::make('mc_tipo')->formatStateUsing(Etiqueta::de(['recepcion' => 'Recepción', ...]))
 *
 * y en la 3 el equivalente es `->formatStateUsing()` con un cierre. Pasarlo a
 * mano en las siete columnas que lo usaban dejaba siete cierres identicos
 * repetidos, asi que se centraliza aca:
 *
 *     TextColumn::make('mc_tipo')->badge()
 *         ->formatStateUsing(Etiqueta::de(['recepcion' => 'Recepción', ...]))
 *
 * Un valor que no este en el mapa se muestra tal cual, que es lo que hacia
 * `enum()`: en estas tablas hay filas migradas de v1 con estados que ya no se
 * usan, y es mejor ver el valor crudo que una celda vacia.
 */
class Etiqueta
{
    /**
     * @param  array<int|string, string>  $mapa
     */
    public static function de(array $mapa): Closure
    {
        return static fn ($state): ?string => $mapa[$state] ?? ($state === null ? null : (string) $state);
    }
}

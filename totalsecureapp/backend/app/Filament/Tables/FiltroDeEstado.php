<?php

namespace App\Filament\Tables;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro «Estado» que abre mostrando solo los registros activos.
 *
 * **Por que existe.** Aca nada se borra: un guardia que se va, un local que
 * cierra o un cliente que termina contrato se **desactivan**, porque su
 * historial de rondas, marcajes y accesos tiene que seguir consultable. El
 * efecto es que los listados mezclan lo que opera con lo que ya no, y hay que
 * mirar la columna de estado fila por fila. En Clientes es lo mas notorio: 6 de
 * 21 son bajas.
 *
 * **Lo que NO se pudo hacer.** Filament 2 no tiene pestañas en los listados
 * (`getTabs()` y la clase `Tab` son de Filament 3): no hay «Activos | Inactivos
 * | Todos» arriba de la tabla. El equivalente en esta version es este filtro con
 * valor por defecto, que se maneja desde el panel de filtros. Cuando se suba a
 * Filament 3, esto se puede convertir en pestañas de verdad.
 *
 * ⚠️ **Un filtro por defecto es la trampa de «no aparece, entonces no existe».**
 * Alguien busca a un guardia dado de baja, no lo encuentra y lo crea de nuevo.
 * Dos cosas lo contienen: Filament muestra un indicador de filtro activo debajo
 * de la barra de busqueda, y el alta de usuarios valida `usu_cedula` unica
 * **contra toda la tabla**, incluidos los inactivos, asi que el duplicado se
 * rechaza igual.
 */
class FiltroDeEstado
{
    /**
     * @param string $columna    La columna de estado. Cada tabla la llama distinto
     *                           (`usu_state`, `ins_estado`, `ipc_activo`…),
     *                           herencia de coredt360.
     * @param mixed  $valorActivo Que valor significa «activo». Casi siempre
     *                           `true`, pero `users.usu_state` y
     *                           `user_has_institucion.ui_state` son enteros, y
     *                           `user_has_gestions.ug_finish` esta **invertida**:
     *                           ahi «activo» es `false`, porque la columna dice
     *                           si la gestion TERMINO.
     */
    public static function make(string $columna, $valorActivo = true, string $etiqueta = 'Estado'): SelectFilter
    {
        return SelectFilter::make('estado_' . $columna)
            ->label($etiqueta)
            ->options([
                'activos'   => 'Activos',
                'inactivos' => 'Inactivos',
                'todos'     => 'Todos',
            ])
            // Sin esto el listado abre con todo mezclado, que es el punto.
            ->default('activos')
            ->query(function (Builder $query, array $data) use ($columna, $valorActivo): Builder {
                $estado = $data['value'] ?? 'activos';

                if ($estado === 'todos') {
                    return $query;
                }

                if ($estado === 'inactivos') {
                    // El `orWhereNull` importa: una fila con la columna en NULL
                    // no es activa, y sin esto quedaria fuera de las dos
                    // opciones -- invisible en «Activos» y en «Inactivos».
                    return $query->where(function ($q) use ($columna, $valorActivo) {
                        $q->where($columna, '!=', $valorActivo)
                            ->orWhereNull($columna);
                    });
                }

                return $query->where($columna, $valorActivo);
            });
    }
}

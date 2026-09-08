<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;

/**
 * Selector de usuario que busca por nombre Y por cedula.
 *
 * **El problema que resuelve.** Los cinco selectores de usuario del panel
 * cargaban los 839 usuarios activos en un arreglo y dejaban que `searchable()`
 * filtrara el texto en el navegador. Con eso:
 *
 *  - Tres de ellos (`Locales por usuario`, `Gestiones`, `Perfil por usuario`)
 *    mostraban solo `usu_nmbcom`, asi que **buscar por cedula no encontraba
 *    nada**. Y la cedula es lo que el guardia dice por telefono y lo que figura
 *    en su credencial: buscar por nombre obliga a saber como esta escrito
 *    («CASTRO ALVARES ANDRES ARTURO», sin tildes y con los apellidos primero).
 *  - Los otros dos (`Turnos` y las franjas del cuadrante) si la encontraban,
 *    pero **por coincidencia**: la cedula estaba pegada en la etiqueta. Si
 *    alguien acortaba el texto, la busqueda por cedula dejaba de funcionar sin
 *    que nada avisara.
 *
 * Aca la busqueda es explicita y va a la base: `usu_nmbcom ILIKE` **o**
 * `usu_cedula LIKE`. Deja de depender de lo que diga la etiqueta.
 *
 * De paso ya no se cargan 839 filas en cada render del formulario: se consultan
 * solo las que coinciden, con tope de 50.
 */
class SelectorDeUsuario
{
    /** Tope de resultados. Mas que esto no se lee: se escriben dos letras mas. */
    private const MAX = 50;

    public static function make(string $campo, string $etiqueta = 'Usuario'): Select
    {
        return Select::make($campo)
            ->label($etiqueta)
            ->searchable()
            // **El parametro DEBE llamarse `$search`.** Filament inyecta los
            // argumentos de la clausura POR NOMBRE (Select::getSearchResults pasa
            // 'query', 'search' y 'searchQuery'); con cualquier otro nombre
            // intenta resolverlo del contenedor y revienta con
            // BindingResolutionException recien cuando alguien escribe en la caja.
            ->getSearchResultsUsing(function (string $search): array {
                return self::base()
                    ->where(function ($q) use ($search) {
                        // ILIKE para el nombre (viene en mayusculas en la base y
                        // nadie lo escribe asi al buscar) y LIKE para la cedula,
                        // que son digitos.
                        $q->where('usu_nmbcom', 'ILIKE', "%{$search}%")
                            ->orWhere('usu_cedula', 'LIKE', "%{$search}%");
                    })
                    ->orderBy('usu_nmbcom')
                    ->limit(self::MAX)
                    ->get()
                    ->mapWithKeys(fn ($u) => [$u->id => self::etiqueta($u)])
                    ->all();
            })
            // Sin esto, al ABRIR un registro existente el selector muestra el id
            // crudo en vez del nombre: `getSearchResultsUsing` solo corre cuando
            // alguien escribe.
            ->getOptionLabelUsing(function ($value): ?string {
                $u = self::base(false)->where('id', $value)->first();

                return $u ? self::etiqueta($u) : null;
            })
            ->helperText('Busca por nombre o por cédula.');
    }

    /**
     * @param bool $soloActivos Al buscar, solo activos. Al mostrar un registro
     *                          ya guardado, tambien los inactivos: si no, el
     *                          vinculo de un guardia dado de baja apareceria
     *                          vacio y se veria como un dato corrupto.
     */
    private static function base(bool $soloActivos = true)
    {
        $q = DB::table('users')->select('id', 'usu_nmbcom', 'usu_cedula');

        return $soloActivos ? $q->where('usu_state', 1) : $q;
    }

    private static function etiqueta($u): string
    {
        $nombre = trim((string) $u->usu_nmbcom);

        // Hay 665 usuarios sin nombres y apellidos separados, pero usu_nmbcom
        // nunca esta vacio; aun asi, si lo estuviera, la cedula sola es mejor
        // que una linea en blanco.
        return $nombre !== ''
            ? $nombre . ' — ' . $u->usu_cedula
            : (string) $u->usu_cedula;
    }
}

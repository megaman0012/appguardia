<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\MobileApp\Models\users;

/**
 * Carga masiva de usuarios desde un CSV.
 *
 * **El problema que resuelve.** Dar de alta un guardia a mano son cinco
 * pantallas: crear el usuario, asignarle el rol, abrirle la gestion, vincularlo
 * a sus locales y avisarle la clave. Para una nomina de cientos de personas eso
 * no se hace.
 *
 * ⚠️ **Un usuario necesita CUATRO piezas, no una.** Es lo que hace que la carga
 * masiva valga la pena, y tambien lo que se olvida al hacerlo a mano:
 *
 *   1. La fila en `users` con `usu_state = 1`.
 *   2. El rol en `user_has_roles`.
 *   3. Una gestion **abierta** en `user_has_gestions` (`ug_finish = false`).
 *      Sin ella el login responde «El usuario no tiene una gestion activa».
 *   4. El vinculo en `user_has_institucion`. Sin el, la app movil no puede
 *      registrar nada y la API del portal responde 403.
 *
 * El formulario de Usuarios del panel crea **solo la primera**, asi que un
 * usuario dado de alta ahi no puede entrar. Este servicio crea las cuatro, con
 * la misma logica que el comando `usuario:crear`.
 *
 * Se trabaja en dos pasos, igual que la carga de cuadrantes: `analizar()` no
 * escribe nada y devuelve los problemas fila por fila. Revisar antes de crear
 * trescientos usuarios es la mitad del valor.
 */
use App\Support\NombreDePersona;

class UsuarioImportService
{
    /** Columnas esperadas, en cualquier orden. */
    public const COLUMNAS = ['cedula', 'nombres', 'apellidos', 'rol', 'locales'];

    /** Columnas que se aceptan pero no son obligatorias. */
    public const OPCIONALES = ['email', 'whatsapp', 'acepta_whatsapp'];

    /**
     * Lee y valida SIN escribir nada.
     *
     * @return array{filas: array<int,array>, errores: string[], avisos: string[]}
     */
    public function analizar(string $rutaArchivo): array
    {
        $contenido = @file_get_contents($rutaArchivo);

        if ($contenido === false || trim($contenido) === '') {
            return ['filas' => [], 'errores' => ['El archivo está vacío o no se pudo leer.'], 'avisos' => []];
        }

        $lineas = $this->aLineas($contenido);

        if (count($lineas) < 2) {
            return ['filas' => [], 'errores' => ['El archivo no tiene filas de datos.'], 'avisos' => []];
        }

        $cabecera = $this->cabecera(array_shift($lineas));

        $faltan = array_diff(self::COLUMNAS, array_keys($cabecera));
        if ($faltan !== []) {
            return [
                'filas' => [],
                'errores' => ['Faltan columnas: ' . implode(', ', $faltan) . '. Descargue el modelo.'],
                'avisos' => [],
            ];
        }

        $roles = DB::table('roles')->pluck('id', 'name');
        $locales = $this->localesDisponibles();

        // Las cedulas ya usadas, y las que se repiten DENTRO del archivo: un
        // archivo con la misma persona dos veces es un error de quien lo armo,
        // y si no se avisa la segunda fila falla sola a mitad de la carga.
        $yaExisten = users::pluck('usu_cedula')->map(fn ($c) => trim((string) $c))->all();
        $vistas = [];

        $filas = [];
        $errores = [];
        $avisos = [];

        foreach ($lineas as $i => $linea) {
            // +2: la cabecera es la linea 1 y los humanos cuentan desde 1.
            $nro = $i + 2;
            $campos = $this->separar($linea);

            if ($campos === []) {
                continue;
            }

            $fila = [];
            foreach (array_merge(self::COLUMNAS, self::OPCIONALES) as $columna) {
                $indice = $cabecera[$columna] ?? null;
                $fila[$columna] = $indice !== null ? trim((string) ($campos[$indice] ?? '')) : '';
            }

            $cedula = $fila['cedula'];

            if ($cedula === '') {
                $errores[] = "Fila {$nro}: falta la cédula.";
                continue;
            }

            if (!preg_match('/^\d{6,13}$/', $cedula)) {
                $errores[] = "Fila {$nro}: la cédula «{$cedula}» no parece válida (solo dígitos, 6 a 13).";
                continue;
            }

            if (in_array($cedula, $yaExisten, true)) {
                $avisos[] = "Fila {$nro}: la cédula {$cedula} ya existe, se omite.";
                continue;
            }

            if (isset($vistas[$cedula])) {
                $errores[] = "Fila {$nro}: la cédula {$cedula} está repetida (ya venía en la fila {$vistas[$cedula]}).";
                continue;
            }

            if ($fila['nombres'] === '' || $fila['apellidos'] === '') {
                $errores[] = "Fila {$nro}: faltan nombres o apellidos.";
                continue;
            }

            $rol = $this->rolDe($fila['rol'], $roles);
            if ($rol === null) {
                $errores[] = "Fila {$nro}: el rol «{$fila['rol']}» no existe. Disponibles: " . $roles->keys()->implode(', ') . '.';
                continue;
            }

            list($codigos, $malos) = $this->resolverLocales($fila['locales'], $locales);

            if ($malos !== []) {
                $errores[] = "Fila {$nro}: no se reconocen estos locales: " . implode(', ', $malos) . '.';
                continue;
            }

            if ($codigos === []) {
                // No se bloquea: el usuario queda creado y se le vincula despues.
                // Pero hay que decirlo, porque sin local la app no le sirve.
                $avisos[] = "Fila {$nro}: sin locales. El usuario se crea, pero la app no le permitirá registrar nada hasta vincularlo.";
            }

            if ($fila['email'] !== '' && !filter_var($fila['email'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = "Fila {$nro}: el correo «{$fila['email']}» no es válido.";
                continue;
            }

            $vistas[$cedula] = $nro;

            $filas[] = [
                'nro'              => $nro,
                'cedula'           => $cedula,
                'nombres'          => $fila['nombres'],
                'apellidos'        => $fila['apellidos'],
                // El correo NO es obligatorio a proposito: 505 de los 879
                // usuarios reales lo tienen vacio, y la columna no es unica en
                // la practica (hay correos repetidos en 3 casos).
                'email'            => $fila['email'],
                'rol_id'           => $rol['id'],
                'rol'              => $rol['name'],
                'locales'          => $codigos,
                'whatsapp'         => $this->normalizarWhatsapp($fila['whatsapp']),
                'acepta_whatsapp'  => $this->aBooleano($fila['acepta_whatsapp']),
            ];
        }

        if ($filas === [] && $errores === []) {
            $errores[] = 'No quedó ninguna fila para crear.';
        }

        return ['filas' => $filas, 'errores' => $errores, 'avisos' => $avisos];
    }

    /**
     * Crea los usuarios. Si hay errores de formato NO escribe nada.
     *
     * @return array{creados: int, errores: string[], avisos: string[], claves: array<int,array{cedula: string, nombre: string, clave: string}>}
     */
    public function importar(string $rutaArchivo, ?int $creadoPor = null): array
    {
        $r = $this->analizar($rutaArchivo);

        if ($r['errores'] !== []) {
            return ['creados' => 0, 'errores' => $r['errores'], 'avisos' => $r['avisos'], 'claves' => []];
        }

        $claves = [];

        // Todo o nada: una nomina cargada a medias es peor que no cargarla, porque
        // no se sabe donde quedo.
        DB::transaction(function () use ($r, $creadoPor, &$claves) {
            foreach ($r['filas'] as $fila) {
                $clave = $this->claveTemporal();

                $usuario = new users();
                $usuario->usu_cedula = $fila['cedula'];
                $usuario->usu_tipdoc = 'C';
                // ⚠️ Esto escribia el nombre completo como «nombres apellidos»,
                // al reves que el panel y que la mayoria de la data heredada.
                // Ahora los tres caminos de alta usan la misma clase y el mismo
                // orden: **apellidos primero**.
                foreach (NombreDePersona::componer($fila['nombres'], $fila['apellidos']) as $columna => $valor) {
                    $usuario->$columna = $valor;
                }

                $usuario->usu_email = $fila['email'];
                $usuario->usu_state = 1;
                $usuario->usu_whatsapp = $fila['whatsapp'] ?: null;
                $usuario->usu_acepta_whatsapp = $fila['acepta_whatsapp'];

                // `usu_password` esta en $hidden y fuera de $fillable, asi que
                // `create()` lo descartaria en silencio. Se asigna directo y el
                // evento `saving` del modelo lo hashea.
                $usuario->usu_password = $clave;
                $usuario->save();

                DB::table('user_has_roles')->updateOrInsert(
                    ['user_id' => $usuario->id, 'role_id' => $fila['rol_id']],
                    ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
                );

                DB::table('user_has_gestions')->updateOrInsert(
                    ['ug_user_id' => $usuario->id, 'ug_finish' => false],
                    [
                        'ug_ingreso'      => now(),
                        'ug_state'        => 1,
                        'ug_created_user' => $creadoPor ?? $usuario->id,
                        'ug_created_at'   => now(),
                        'ug_updated_at'   => now(),
                    ]
                );

                foreach ($fila['locales'] as $insCode) {
                    DB::table('user_has_institucion')->updateOrInsert(
                        ['ui_usu_id' => $usuario->id, 'ui_ins_code' => $insCode],
                        ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
                    );
                }

                $claves[] = [
                    'cedula' => $fila['cedula'],
                    'nombre' => $usuario->usu_nmbcom,
                    'clave'  => $clave,
                ];
            }
        });

        return [
            'creados' => count($claves),
            'errores' => [],
            'avisos'  => $r['avisos'],
            'claves'  => $claves,
        ];
    }

    /**
     * CSV de ejemplo con los locales y roles reales.
     *
     * Que el operador no tipee nombres a mano evita la mitad de los errores de
     * carga, y es la razon de que el modelo salga con datos de esta base y no
     * con un ejemplo inventado.
     */
    public function plantillaDeEjemplo(): string
    {
        $columnas = array_merge(self::COLUMNAS, self::OPCIONALES);

        $local = DB::table('organizacion_institucion')
            ->where('ins_estado', true)
            ->orderBy('ins_descripcion')
            ->first();

        $rol = DB::table('roles')->where('name', 'Vigilante')->exists() ? 'Vigilante' : 'Supervisor';

        $ejemplo = [
            '0912345678',
            'JUAN CARLOS',
            'PEREZ GOMEZ',
            $rol,
            $local ? (string) $local->ins_code : '',
            'jperez@example.com',
            '0987654321',
            'no',
        ];

        $lineas = [
            implode(',', $columnas),
            implode(',', $ejemplo),
        ];

        return implode("\n", $lineas) . "\n";
    }

    /** Los locales activos, indexados por codigo y por nombre en mayusculas. */
    private function localesDisponibles(): array
    {
        $porCodigo = [];
        $porNombre = [];

        DB::table('organizacion_institucion')
            ->where('ins_estado', true)
            ->select('ins_code', 'ins_descripcion')
            ->get()
            ->each(function ($l) use (&$porCodigo, &$porNombre) {
                $porCodigo[(string) $l->ins_code] = (int) $l->ins_code;
                $porNombre[mb_strtoupper(trim((string) $l->ins_descripcion))] = (int) $l->ins_code;
            });

        return ['codigo' => $porCodigo, 'nombre' => $porNombre];
    }

    /**
     * Acepta codigos o nombres, separados por «|» o «;».
     *
     * No se usa la coma porque el archivo ES un CSV y un local con coma en el
     * nombre partiria la fila.
     *
     * ⚠️ **Excel en español guarda los CSV con punto y coma.** En ese caso el
     * separador de campos ya se comio los «;», asi que dentro de `locales` hay
     * que usar **«|»**. Con un archivo separado por comas sirven los dos. El
     * modelo descargable usa comas, que es el caso simple.
     *
     * @return array{0: int[], 1: string[]}  [codigos, no reconocidos]
     */
    private function resolverLocales(string $texto, array $locales): array
    {
        if (trim($texto) === '') {
            return [[], []];
        }

        $codigos = [];
        $malos = [];

        foreach (preg_split('/[;|]/', $texto) as $parte) {
            $parte = trim($parte);

            if ($parte === '') {
                continue;
            }

            if (isset($locales['codigo'][$parte])) {
                $codigos[] = $locales['codigo'][$parte];
                continue;
            }

            $clave = mb_strtoupper($parte);
            if (isset($locales['nombre'][$clave])) {
                $codigos[] = $locales['nombre'][$clave];
                continue;
            }

            $malos[] = $parte;
        }

        return [array_values(array_unique($codigos)), $malos];
    }

    /** El rol, comparando sin distinguir mayusculas ni tildes. */
    private function rolDe(string $texto, $roles): ?array
    {
        $buscado = $this->plano($texto);

        if ($buscado === '') {
            return null;
        }

        foreach ($roles as $nombre => $id) {
            if ($this->plano((string) $nombre) === $buscado) {
                return ['id' => (int) $id, 'name' => (string) $nombre];
            }
        }

        return null;
    }

    private function plano(string $t): string
    {
        $t = mb_strtoupper(trim($t));

        return strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }

    /**
     * Clave temporal.
     *
     * ⚠️ **La clave NO va en el archivo de entrada, a proposito.** Un CSV con
     * contraseñas se manda por correo, se guarda en Descargas y queda en el
     * historial de quien lo abrio. Se genera aca y se devuelve en el resultado
     * para que el panel la entregue una sola vez.
     *
     * Cumple las mismas reglas que exige el cambio de clave de la app: 8+
     * caracteres con mayuscula, minuscula y numero.
     *
     * ⚠️ **Se eligen los caracteres por clase, no con `Str::random`.** La
     * primera version era `Str::upper(Str::random(2)) . Str::lower(Str::random(4)) . random_int(10,99)`,
     * y `Str::random` puede devolver **solo digitos**: salio `HL025830`, sin
     * ninguna minuscula, y no habria pasado la validacion del cambio de clave
     * de la app. Un fallo que aparece una vez cada tantas cargas es peor que uno
     * que aparece siempre.
     */
    public function claveTemporal(): string
    {
        $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $minusculas = 'abcdefghijkmnopqrstuvwxyz';
        $digitos    = '23456789';

        $clave = [
            $mayusculas[random_int(0, strlen($mayusculas) - 1)],
            $mayusculas[random_int(0, strlen($mayusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $digitos[random_int(0, strlen($digitos) - 1)],
            $digitos[random_int(0, strlen($digitos) - 1)],
        ];

        return implode('', $clave);
    }

    private function normalizarWhatsapp(string $numero): string
    {
        $limpio = preg_replace('/\D+/', '', $numero) ?? '';

        if ($limpio === '') {
            return '';
        }

        // 0987654321 => 593987654321. El gateway acepta el numero local sin
        // quejarse y el mensaje simplemente no llega nunca.
        if (Str::startsWith($limpio, '0') && strlen($limpio) === 10) {
            return '593' . substr($limpio, 1);
        }

        return $limpio;
    }

    private function aBooleano(string $valor): bool
    {
        return in_array($this->plano($valor), ['SI', 'S', 'TRUE', '1', 'X', 'SÍ'], true);
    }

    /** @return string[] */
    private function aLineas(string $contenido): array
    {
        // Se quita el BOM que agrega Excel al guardar como CSV: si no, la
        // primera columna se llama «\xEF\xBB\xBFcedula» y no se reconoce.
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

        $lineas = preg_split('/\r\n|\r|\n/', $contenido) ?: [];

        return array_values(array_filter($lineas, fn ($l) => trim($l) !== ''));
    }

    /** @return array<string,int> nombre de columna => posicion */
    private function cabecera(string $linea): array
    {
        $mapa = [];

        foreach ($this->separar($linea) as $i => $nombre) {
            $limpio = strtolower(trim($nombre));
            $limpio = strtr($limpio, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

            if ($limpio !== '') {
                $mapa[$limpio] = $i;
            }
        }

        return $mapa;
    }

    /** @return string[] */
    private function separar(string $linea): array
    {
        // Punto y coma o coma: Excel en español guarda con punto y coma.
        $separador = substr_count($linea, ';') > substr_count($linea, ',') ? ';' : ',';

        return str_getcsv($linea, $separador);
    }
}

<?php

namespace App\Services\Etl;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Migracion de datos de v1 (MariaDB) a v2 (PostgreSQL), por etapas.
 *
 * Contexto y decisiones en `ANALISIS-MIGRACION-V1.md` (raiz del monorepo). Lo
 * que hay que tener presente para leer este archivo:
 *
 * **1. Se conservan las claves primarias de v1.** Es lo que hace triviales las
 * claves ajenas: un `ronda_detalle` de v1 sigue apuntando al mismo
 * `ronda_cabecera`. El precio es que **el destino tiene que estar vacio**, y por
 * eso cada etapa lo exige. La alternativa era una tabla de equivalencias de ids
 * y reescribir cada FK, con una oportunidad de equivocarse por tabla.
 *
 * **2. Correr `migrate` sobre una copia de v1 NO funciona.** v1 tiene 8
 * migraciones registradas y 41 tablas: el esquema se hizo a mano. v2 trae
 * migraciones que CREAN esas tablas y ninguna usa `hasTable` como guarda, asi
 * que sobre la base de v1 se caen en la primera. De ahi que esto sea un ETL.
 *
 * **3. Las migraciones que TRANSFORMAN datos ya estaran aplicadas** cuando
 * lleguen estas filas, asi que no van a transformar nada. Lo que hacian se hace
 * aqui a mano: el rescate del cliente que colgaba de la sede y la resolucion de
 * la ciudad (etapa `locales`).
 *
 * **4. Las fechas originales son obligatorias, no un detalle.** La ruta de cada
 * foto se CALCULA desde la fecha del registro
 * (`images/<modulo>/<AAAA/MM/DD>/<archivo>`); no se guarda. Cargar con `now()`
 * apuntaria las rutas al dia de la migracion y **las 42.000 fotos
 * desapareceran sin un solo error en el log**. Nunca reemplazar una fecha de
 * origen por `now()` en este archivo.
 *
 * **5. El casteo de tipos va por el tipo de DESTINO, no a mano.** v1 y v2
 * difieren en ~130 columnas: nueve `tinyint` que en Postgres son `boolean` (y
 * Postgres **rechaza** un 1 entero donde espera booleano), `decimal` de GPS que
 * pasaron a texto, y `varchar` de auditoria que pasaron a `bigint`. Mapear eso a
 * mano en 20 tablas era garantizar un error silencioso, asi que `copiar()` lee
 * los tipos del destino y convierte.
 */
class EtlV1
{
    /**
     * Texto libre de `ins_ciudad` en v1 => ciudad del catalogo de v2.
     *
     * Mismo mapeo que sembro la migracion 2026_09_07_200001. Se repite aqui a
     * proposito: la migracion crea las filas y esto resuelve el texto; una
     * migracion ya aplicada no se puede reusar como fuente.
     *
     * `MANTENIMIENTO` no es una ciudad -- alguien escribio un area en ese campo.
     * Va a Guayaquil por decision del usuario (2026-09-07). Sin esto ese local
     * no pertenece a ningun pais y **ningun Lider Operativo lo ve**.
     */
    private const CIUDADES = [
        'GUAYAQUIL'       => 'Guayaquil',
        'QUITO'           => 'Quito',
        'MANTA'           => 'Manta',
        'CUENCA'          => 'Cuenca',
        'AMBATO'          => 'Ambato',
        'PORTOVIEJO'      => 'Portoviejo',
        'IBARRA'          => 'Ibarra',
        'STO. DOMINGO'    => 'Santo Domingo',
        'DURÁN'           => 'Durán',
        'NARANJAL'        => 'Naranjal',
        'EL TRIUNFO'      => 'El Triunfo',
        'NOBOL'           => 'Nobol',
        'VILLAMIL PLAYAS' => 'Playas',
        'SAN CRISTOBAL'   => 'San Cristóbal',
        'BALTRA'          => 'Baltra',
        'MANTENIMIENTO'   => 'Guayaquil',
    ];

    /**
     * Rol de v1 => rol de v2, POR NOMBRE.
     *
     * **Los ids de rol significan cosas distintas en cada version**, y copiarlos
     * tal cual habria sido un desastre silencioso:
     *
     *   id | v1                        | v2
     *    1 | Administrador General     | Supervisor
     *    2 | Administrador             | Vigilante
     *    3 | Supervisor                | Cliente
     *    4 | Vigilante                 | Administrador
     *    5 | Administrador Institucion | Lider Operativo
     *    6 | Consola Notificacion      | Consola
     *
     * O sea que conservar `role_id` convertia a los 867 vigilantes de v1
     * (role_id 4) en **Administradores** de v2, y a los 50 supervisores en
     * Clientes. Todo el RBAC al reves, sin un solo error.
     *
     * `Administrador General` y `Administrador` colapsan los dos en
     * `Administrador`: v2 no distingue niveles de administrador. Eso hace que
     * **dos usuarios que tienen ambos roles en v1 choquen** en la clave primaria
     * (role_id, user_id) de v2, asi que la etapa descarta el repetido.
     *
     * `Administrador Institucion` -> `Lider Operativo` es el equivalente por
     * funcion (administra los locales de su alcance). En v1 **no lo tiene ningun
     * usuario**, asi que la equivalencia no cambia nada hoy; queda escrita para
     * que no haya que decidirla a las apuradas si aparece.
     */
    private const ROLES = [
        'Administrador General'     => 'Administrador',
        'Administrador'             => 'Administrador',
        'Supervisor'                => 'Supervisor',
        'Vigilante'                 => 'Vigilante',
        'Administrador Institucion' => 'Lider Operativo',
        'Consola Notificacion'      => 'Consola',
    ];

    /** Filas por lote. Con 38.000 vinculos, traerlas todas a memoria no va. */
    private const LOTE = 2000;

    private bool $forzar;
    private array $avisos = [];
    private array $notas = [];
    private array $rellenos = [];

    public function __construct(bool $forzar = false)
    {
        $this->forzar = $forzar;
    }

    // ─────────────────────────── Etapas ───────────────────────────

    /**
     * Clientes. `organizacion` es la tabla de clientes (ahi va DHL), no de
     * organizaciones internas: no confundir con `organizacion_institucion`.
     */
    public function clientes(): array
    {
        return $this->copiar('organizacion', 'organizacion', 'org_code');
    }

    /**
     * Locales, con las dos transformaciones que las migraciones ya no haran.
     */
    public function locales(): array
    {
        // El cliente que colgaba de la sede. En v1 la cadena era
        // local -> organizacion_sede -> organizacion; en v2 el local apunta
        // directo al cliente con ins_cliente_id.
        $clientePorSede = $this->v1()->table('organizacion_sede')
            ->pluck('so_org_code', 'so_code');

        $ciudades = DB::table('ciudad')->pluck('cd_id', 'cd_nombre');

        $sinCiudad = 0;
        $sinCliente = 0;

        $r = $this->copiar(
            'organizacion_institucion',
            'organizacion_institucion',
            'ins_code',
            function (array $fila, $origen) use ($clientePorSede, $ciudades, &$sinCiudad, &$sinCliente) {
                $clave = mb_strtoupper(trim((string) $origen->ins_ciudad));
                $nombre = self::CIUDADES[$clave] ?? null;
                $cdId = $nombre !== null ? ($ciudades[$nombre] ?? null) : null;

                if ($cdId === null) {
                    $sinCiudad++;
                    $this->nota("local {$origen->ins_code}: ciudad '{$origen->ins_ciudad}' sin equivalencia");
                }

                $clienteId = $clientePorSede[$origen->ins_so_code] ?? null;
                if ($clienteId === null) {
                    $sinCliente++;
                }

                // ins_ciudad (el texto original) se copia solo: existe en las dos
                // versiones. Es el unico registro de lo que habia escrito, y
                // sirve para auditar el mapeo despues.
                $fila['ins_cd_id'] = $cdId;
                $fila['ins_cliente_id'] = $clienteId;

                return $fila;
            }
        );

        if ($sinCiudad > 0) {
            $this->aviso("{$sinCiudad} locales quedaron SIN ciudad: no los vera ningun Lider Operativo");
        }
        if ($sinCliente > 0) {
            $this->aviso("{$sinCliente} locales sin cliente (ins_so_code sin fila en organizacion_sede)");
        }

        return $this->conMensajes($r);
    }

    /** Los 878 usuarios. Sin ellos no cuelga nada mas. */
    public function usuarios(): array
    {
        return $this->copiar('users', 'users', 'id');
    }

    /**
     * Gestiones: el periodo de trabajo de cada usuario.
     *
     * Importa mas de lo que parece: `getSanctumSession()` resuelve la gestion
     * activa en cada request de la app, y un usuario sin gestion abierta no
     * puede registrar nada.
     */
    public function gestiones(): array
    {
        return $this->copiar('user_has_gestions', 'user_has_gestions', 'ug_code');
    }

    /** Roles de cada usuario, traducidos por nombre (ver self::ROLES). */
    public function roles(): array
    {
        $idPorNombre = DB::table('roles')->pluck('id', 'name');
        $nombreV1 = $this->v1()->table('roles')->pluck('name', 'id');

        foreach (self::ROLES as $de => $a) {
            if (!isset($idPorNombre[$a])) {
                throw new RuntimeException("El rol '{$a}' no existe en v2: revisar el sembrado de roles.");
            }
        }

        $vistos = [];
        $descartados = 0;
        $sinEquivalencia = 0;

        $r = $this->copiar(
            'user_has_roles',
            'user_has_roles',
            'ru_code',
            function (array $fila, $origen) use ($idPorNombre, $nombreV1, &$vistos, &$descartados, &$sinEquivalencia) {
                $nombre = $nombreV1[$origen->role_id] ?? null;
                $destino = $nombre !== null ? (self::ROLES[$nombre] ?? null) : null;

                if ($destino === null) {
                    $sinEquivalencia++;
                    $this->nota("rol '{$nombre}' (id {$origen->role_id}) sin equivalencia: se omite");
                    return null;
                }

                $rolId = (int) $idPorNombre[$destino];
                $clave = $rolId . ':' . $origen->user_id;

                // Dos roles de v1 pueden colapsar en uno de v2 (los dos
                // "Administrador"). La segunda fila chocaria en la clave
                // primaria compuesta.
                if (isset($vistos[$clave])) {
                    $descartados++;
                    return null;
                }
                $vistos[$clave] = true;

                $fila['role_id'] = $rolId;

                return $fila;
            }
        );

        if ($descartados > 0) {
            $this->aviso("{$descartados} vinculos repetidos descartados (dos roles de v1 que son uno en v2)");
        }
        if ($sinEquivalencia > 0) {
            $this->aviso("{$sinEquivalencia} vinculos omitidos por rol sin equivalencia");
        }

        // El conteo NO cuadra a proposito cuando se descarta algo: se informa
        // el esperado para que la diferencia sea explicable y no sospechosa.
        $r['esperado'] = $r['origen'] - $descartados - $sinEquivalencia;

        return $this->conMensajes($r);
    }

    /** Que local ve cada usuario. 38.246 filas, sin pares duplicados en v1. */
    public function vinculos(): array
    {
        return $this->copiar('user_has_institucion', 'user_has_institucion', 'ui_code');
    }

    /**
     * Puntos QR de cada local.
     *
     * Son 118 para 137 locales, asi que **hay locales sin marcador**: sus
     * marcajes van a entrar como «ubicacion no verificada» (ver AGENTS.md,
     * seccion de geocerca). La etapa lo informa.
     */
    public function marcadores(): array
    {
        $r = $this->copiar('institucion_marcadores', 'institucion_marcadores', 'im_code');

        $sinMarcador = DB::table('organizacion_institucion')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('institucion_marcadores')
                    ->whereColumn('im_ins_code', 'ins_code')
                    ->where('im_estado', true);
            })->count();

        if ($sinMarcador > 0) {
            $this->aviso(
                "{$sinMarcador} locales quedan SIN marcador activo: su asistencia entrara " .
                'como ubicacion no verificada hasta que se les cargue el punto QR'
            );
        }

        return $this->conMensajes($r);
    }

    // ─────────────────────────── Interno ───────────────────────────

    private function v1(): ConnectionInterface
    {
        if ((string) config('database.connections.v1.host') === '') {
            throw new RuntimeException(
                'La conexion v1 no esta configurada. Definir V1_DB_* en el .env (ver .env.example).'
            );
        }

        return DB::connection('v1');
    }

    /**
     * Copia una tabla de v1 a v2 casteando por el tipo de destino.
     *
     * Solo copia las columnas que existen en LAS DOS. Las que solo estan en v1
     * se informan como nota, porque una columna que se cae en silencio es
     * exactamente el tipo de perdida que despues nadie puede explicar.
     *
     * @param callable|null $transformar fn(array $fila, object $origen): array
     */
    private function copiar(string $tablaV1, string $tablaV2, string $pk, ?callable $transformar = null): array
    {
        // Por etapa: si no, las notas de 'clientes' reaparecen en 'locales' y
        // parece que el problema es de la etapa que se esta mirando.
        $this->notas = [];
        $this->avisos = [];
        $this->rellenos = [];

        $this->exigirVacia($tablaV2);

        $tipos = $this->tiposDe($tablaV2);
        $obligatorias = $this->obligatoriasDe($tablaV2);
        $colsV1 = $this->columnasV1($tablaV1);
        $comunes = array_values(array_intersect($colsV1, array_keys($tipos)));

        $descartadas = array_diff($colsV1, $comunes);
        if ($descartadas !== []) {
            $this->nota("columnas de v1 sin destino: " . implode(', ', $descartadas));
        }

        $insertadas = 0;

        $this->v1()->table($tablaV1)->orderBy($pk)->chunk(self::LOTE,
            function ($lote) use ($comunes, $tipos, $obligatorias, $tablaV2, $transformar, &$insertadas) {
                $filas = [];

                foreach ($lote as $origen) {
                    $fila = [];
                    foreach ($comunes as $col) {
                        $fila[$col] = $this->castear($origen->{$col}, $tipos[$col]);
                    }
                    $fila = $this->rellenarObligatorias($fila, $obligatorias, $tipos, $tablaV2);

                    if ($transformar !== null) {
                        $fila = $transformar($fila, $origen);
                        // null = la fila se descarta a proposito (repetida, o sin
                        // equivalencia). La etapa lo informa como aviso.
                        if ($fila === null) {
                            continue;
                        }
                    }
                    $filas[] = $fila;
                }

                if ($filas !== []) {
                    DB::table($tablaV2)->insert($filas);
                    $insertadas += count($filas);
                }
            }
        );

        $this->ajustarSecuencia($tablaV2, $pk);

        foreach ($this->rellenos as $columna => $n) {
            $this->aviso("{$n} filas llegaron sin {$columna}: se guardo vacio");
        }

        return [
            'origen'     => $this->v1()->table($tablaV1)->count(),
            'destino'    => DB::table($tablaV2)->count(),
            'insertadas' => $insertadas,
            'notas'      => $this->notas,
            'avisos'     => $this->avisos,
        ];
    }

    /**
     * Convierte un valor de v1 al tipo que espera la columna de v2.
     *
     * Los casos que importan:
     *  - **boolean**: Postgres rechaza un 1 entero donde espera booleano, y v1
     *    usa `tinyint` en nueve columnas.
     *  - **numerico**: `organizacion.org_created_user` es varchar en v1 y trae
     *    `'admin'` en tres filas. No es un id de usuario y no hay a quien
     *    apuntar, asi que va a null en vez de reventar la carga.
     *  - **texto**: el GPS era `decimal`/`double` y ahora es varchar. Se
     *    convierte explicitamente para que no viaje como float.
     */
    private function castear($valor, string $tipoDestino)
    {
        if ($valor === null) {
            return null;
        }

        switch ($tipoDestino) {
            case 'boolean':
                return (bool) $valor;

            case 'bigint':
            case 'integer':
            case 'smallint':
                if (is_numeric($valor)) {
                    return (int) $valor;
                }
                // Texto donde v2 espera un id: se pierde el valor, pero queda
                // contado en las notas de la etapa.
                $this->nota("valor no numerico descartado en columna {$tipoDestino}: '{$valor}'");
                return null;

            case 'character varying':
            case 'text':
                return (string) $valor;

            case 'timestamp without time zone':
            case 'timestamp with time zone':
            case 'date':
                // La fecha cero de MySQL. Es un valor que MariaDB acepta y
                // Postgres rechaza ("date/time field value out of range"), y en
                // v1 aparece 69 veces en usu_email_verified_at. Significa "sin
                // fecha", asi que va a null: es lo que quiso decir.
                if (is_string($valor) && str_starts_with($valor, '0000-00-00')) {
                    return null;
                }
                return $valor;

            default:
                return $valor;
        }
    }

    /**
     * Columnas que v2 exige y v1 permitia vacias.
     *
     * Son 15 en total, y **14 no tienen ni una fila nula en los datos reales**.
     * La que si: `users`, donde 665 de 878 usuarios no tienen los nombres y
     * apellidos separados y 505 no tienen correo. El nombre completo
     * (`usu_nmbcom`) nunca falta, asi que no se pierde a nadie.
     */
    private function obligatoriasDe(string $tabla): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $tabla)
            ->where('is_nullable', 'NO')
            ->whereNull('column_default')
            ->pluck('column_name')
            ->all();
    }

    /**
     * Rellena lo que v2 exige y llego vacio.
     *
     * En texto va cadena vacia: el dato no existe y no hay nada que inventar.
     * En una columna numerica **no se rellena**: un 0 en una clave ajena crearia
     * un vinculo a un local o un usuario que no existe, que es peor que fallar.
     * Se deja pasar para que reviente y quede a la vista.
     */
    private function rellenarObligatorias(array $fila, array $obligatorias, array $tipos, string $tabla): array
    {
        foreach ($obligatorias as $col) {
            if (!array_key_exists($col, $fila) || $fila[$col] !== null) {
                continue;
            }

            $tipo = $tipos[$col] ?? '';

            if ($tipo === 'character varying' || $tipo === 'text') {
                $fila[$col] = '';
                $this->contarRelleno("{$tabla}.{$col}");
            }
        }

        return $fila;
    }

    private function contarRelleno(string $columna): void
    {
        $this->rellenos[$columna] = ($this->rellenos[$columna] ?? 0) + 1;
    }

    private function tiposDe(string $tabla): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $tabla)
            ->pluck('data_type', 'column_name')
            ->all();
    }

    private function columnasV1(string $tabla): array
    {
        return $this->v1()->table('information_schema.columns')
            ->where('table_schema', $this->v1()->getDatabaseName())
            ->where('table_name', $tabla)
            ->pluck('column_name')
            ->all();
    }

    /**
     * El destino tiene que estar vacio porque se conservan los ids de v1.
     *
     * Con filas previas el INSERT chocaria en la clave primaria, y peor: si no
     * chocara (ids distintos) quedarian mezclados los datos de demostracion con
     * los reales, que es mucho mas dificil de deshacer que un error.
     */
    private function exigirVacia(string $tabla): void
    {
        $n = DB::table($tabla)->count();

        if ($n > 0 && !$this->forzar) {
            throw new RuntimeException(
                "La tabla '{$tabla}' ya tiene {$n} filas. El ETL conserva los ids de v1, " .
                'asi que el destino debe estar vacio. Partir de una base recien migrada ' .
                '(migrate:fresh, SIN db:seed) o pasar --forzar si se sabe lo que se hace.'
            );
        }
    }

    /**
     * Pone la secuencia por encima del id mas alto insertado.
     *
     * Insertar ids explicitos NO mueve la secuencia de Postgres. Sin esto, el
     * primer registro que cree la aplicacion arranca desde 1 y falla con
     * violacion de clave primaria -- y el sintoma aparece recien en produccion,
     * cuando un guardia marca por primera vez.
     */
    private function ajustarSecuencia(string $tabla, string $pk): void
    {
        $secuencia = DB::selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS s',
            [$tabla, $pk]
        );

        // Una tabla con clave compuesta (user_has_roles) no tiene secuencia.
        if ($secuencia === null || $secuencia->s === null) {
            return;
        }

        DB::statement(
            "SELECT setval(?, COALESCE((SELECT MAX({$pk}) FROM {$tabla}), 1))",
            [$secuencia->s]
        );
    }

    private function nota(string $t): void
    {
        // Se recortan: una etapa con 38.000 filas podria generar 38.000 notas y
        // la salida dejaria de servir para leerla.
        if (count($this->notas) < 15) {
            $this->notas[] = $t;
        }
    }

    private function aviso(string $t): void
    {
        $this->avisos[] = $t;
    }

    private function conMensajes(array $r): array
    {
        $r['notas'] = $this->notas;
        $r['avisos'] = $this->avisos;

        return $r;
    }
}

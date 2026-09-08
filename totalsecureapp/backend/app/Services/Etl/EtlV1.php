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

    /** Rondas: cabecera y detalle. El detalle trae 29.243 fotos. */
    public function rondas(): array
    {
        // Las PK de v1 no siguen un patron: unas son *_code y otras *_id
        // (rc_id, rd_id, nv_id, bt_id). Asumirlo cuesta un fallo por tabla.
        $cab = $this->copiar('ronda_cabecera', 'ronda_cabecera', 'rc_id');
        $det = $this->copiar('ronda_detalle', 'ronda_detalle', 'rd_id');

        return $this->sumar([$cab, $det]);
    }

    /** Marcajes de asistencia. Las 12.664 filas traen foto. */
    public function biometria(): array
    {
        return $this->copiar('user_has_biometria', 'user_has_biometria', 'bio_code');
    }

    public function novedades(): array
    {
        return $this->copiar('novedad', 'novedad', 'nv_id');
    }

    /**
     * Alertas. El estado viene capitalizado y v2 tiene un CHECK en minusculas.
     *
     * v1 guarda 'Finalizada' en las 278 filas; el constraint de v2 solo acepta
     * pendiente / en_atencion / finalizada / cancelada. Es un choque de
     * mayusculas, no de significado, asi que se normaliza.
     */
    public function alertas(): array
    {
        $estados = ['pendiente', 'en_atencion', 'finalizada', 'cancelada'];

        return $this->copiar('alertas', 'alertas', 'al_code',
            function (array $fila, $origen) use ($estados) {
                if (!array_key_exists('al_estado_alerta', $fila)) {
                    return $fila;
                }

                $valor = mb_strtolower(trim((string) $fila['al_estado_alerta']));

                if ($valor === '' ) {
                    $fila['al_estado_alerta'] = null;
                } elseif (in_array($valor, $estados, true)) {
                    $fila['al_estado_alerta'] = $valor;
                } else {
                    // Un valor que no esta en la lista se deja en null en vez de
                    // inventar uno: el CHECK acepta null, y una alerta sin estado
                    // se ve en el panel como pendiente de revisar.
                    $this->nota("al_estado_alerta '{$fila['al_estado_alerta']}' fuera de la lista: se guarda null");
                    $fila['al_estado_alerta'] = null;
                }

                return $fila;
            }
        );
    }

    /** Bitacora, tokens push y parametros. Poco volumen, pero hacen falta. */
    public function varios(): array
    {
        return $this->sumar([
            $this->copiar('bitacora', 'bitacora', 'bt_id'),
            $this->copiar('user_has_push_tkn', 'user_has_push_tkn', 'pt_code'),
            $this->copiar('parametros', 'parametros', 'pr_code'),
        ]);
    }

    /**
     * Accesos, con las columnas que v2 movio a otras tablas.
     *
     * Siete columnas de `acceso` no existen en el `acceso` de v2 porque se
     * normalizaron en `acceso_vehiculo`, y **tienen datos**: `ac_empresa` en
     * 8.975 de 9.769 filas. Un ETL columna a columna las tiraria en silencio.
     *
     * `ac_nombre_contrato` (36 filas) va a `acceso_visitante.avi_persona_visita`:
     * mirando los valores se ve que guardaba la persona visitada o el
     * responsable que autorizaba, en texto libre.
     */
    public function accesos(): array
    {
        // Las personas primero: el acceso apunta a ellas.
        $personas = $this->copiar('acceso_persona', 'acceso_persona', 'ap_code');

        $cab = $this->copiar('acceso', 'acceso', 'ac_code',
            function (array $fila, $origen) {
                // `ac_tipo` NO significa lo mismo en las dos versiones.
                //
                // En v1 es el MEDIO DE TRANSPORTE, un entero que apunta a
                // `acceso_transporte_tipo`: 1=Caminando, 2=Bicicleta, 3=Moto,
                // 4=Vehiculo. Se confirma con los datos: las 1.320 filas con
                // ac_tipo=4 son las unicas que traen patente.
                //
                // En v2 es el TIPO DE ACCESO: peatonal / vehicular / proveedor /
                // empleado / visitante. Copiar el entero tal cual dejaria "1" y
                // "4" en una columna de texto, y `Acceso::esVehicular()` diria
                // que ningun acceso lo es.
                $transporte = (int) $origen->ac_tipo;

                $fila['ac_tipo'] = in_array($transporte, [3, 4], true)
                    ? 'vehicular'   // moto o vehiculo
                    : 'peatonal';   // caminando o bicicleta

                // La bicicleta no se pierde: v2 tiene una columna propia para
                // eso. En v1 `ac_bicicleta` esta casi sin usar (17 filas en
                // 9.769), asi que el dato real vivia en ac_tipo=2.
                if ($transporte === 2) {
                    $fila['ac_bicicleta'] = true;
                }

                return $fila;
            }
        );

        $this->exigirVacia('acceso_vehiculo');
        $this->exigirVacia('acceso_visitante');

        $vehiculos = 0;
        $visitantes = 0;

        $this->v1()->table('acceso')->orderBy('ac_code')->chunk(self::LOTE,
            function ($lote) use (&$vehiculos, &$visitantes) {
                $veh = [];
                $vis = [];

                foreach ($lote as $a) {
                    // Solo se crea la fila si hay algo que guardar: un acceso
                    // peatonal sin nada de vehiculo no necesita una fila vacia.
                    //
                    // `ac_pta_llave` va aparte y NO por algunoConDato: es un
                    // varchar con '0'/'1', y '0' no es cadena vacia, asi que
                    // contaba como dato y se creaba una fila para los 9.769
                    // accesos. Con esto quedan 8.993, que es lo que dice la
                    // consulta equivalente sobre v1.
                    $tieneVehiculo = $this->algunoConDato([$a->ac_patente, $a->ac_empresa, $a->ac_kms])
                        || (bool) $a->ac_is_carro
                        || (bool) $a->ac_is_sello
                        || (bool) $a->ac_is_neumatico
                        || (bool) $a->ac_pta_llave;

                    if ($tieneVehiculo) {
                        $veh[] = [
                            'av_ac_code'      => $a->ac_code,
                            'av_patente'      => $this->texto($a->ac_patente),
                            'av_empresa'      => $this->texto($a->ac_empresa),
                            'av_is_sello'     => (bool) $a->ac_is_sello,
                            'av_is_neumatico' => (bool) $a->ac_is_neumatico,
                            'av_is_carro'     => (bool) $a->ac_is_carro,
                            // varchar '0'/'1' en v1, booleano en v2.
                            'av_pta_llave'    => (bool) $a->ac_pta_llave,
                            'av_kms'          => $this->texto($a->ac_kms),
                            'created_at'      => $a->ac_created_at,
                            'updated_at'      => $a->ac_updated_at,
                        ];
                    }

                    if ($this->algunoConDato([$a->ac_nombre_contrato])) {
                        $vis[] = [
                            'avi_ac_code'        => $a->ac_code,
                            'avi_persona_visita' => $this->texto($a->ac_nombre_contrato),
                            'created_at'         => $a->ac_created_at,
                            'updated_at'         => $a->ac_updated_at,
                        ];
                    }
                }

                if ($veh !== []) {
                    DB::table('acceso_vehiculo')->insert($veh);
                    $vehiculos += count($veh);
                }
                if ($vis !== []) {
                    DB::table('acceso_visitante')->insert($vis);
                    $visitantes += count($vis);
                }
            }
        );

        $r = $this->sumar([$personas, $cab]);
        $r['avisos'][] = "{$vehiculos} filas de acceso_vehiculo creadas desde las columnas que v2 movio";
        $r['avisos'][] = "{$visitantes} filas de acceso_visitante con la persona visitada (ac_nombre_contrato)";

        return $r;
    }

    /**
     * Inventario. Es la etapa que NO puede conservar los ids, y por que.
     *
     * Dos cambios de forma:
     *
     * **1. Los productos eran globales y ahora son por local.** Los 16 de
     * `inv_productos` se convierten en 523 filas de `inv_producto_catalogo`, una
     * por cada par (local, producto) realmente usado. Un id de v1 pasa a ser N
     * ids de v2, asi que aqui hace falta un mapa; es el unico lugar del ETL
     * donde los ids se reasignan.
     *
     * **2. Un movimiento era el ciclo completo y ahora cada fila es un evento.**
     * En los datos reales los 5.963 movimientos tienen fecha de recepcion y
     * 5.916 tambien de devolucion, asi que salen **11.879 eventos**. Ninguno
     * tiene asignacion ni entrega, asi que esas dos etapas no generan nada.
     */
    public function inventario(): array
    {
        foreach (['inv_producto_catalogo', 'inv_lista', 'inv_lista_item',
                  'inv_movimiento_cabecera', 'inv_movimiento_detalle'] as $t) {
            $this->exigirVacia($t);
        }

        $this->notas = [];
        $this->avisos = [];

        // ── Listas (ids conservados) ──
        $listas = $this->v1()->table('inv_listas_productos')->get();
        DB::table('inv_lista')->insert($listas->map(fn ($l) => [
            'li_id'           => $l->lp_id,
            'li_ins_code'     => $l->lp_ins_code,
            'li_nombre'       => $l->lp_nombre,
            'li_descripcion'  => $l->lp_descripcion,
            'li_activo'       => (bool) $l->lp_estado,
            'li_created_user' => $l->lp_created_user,
            'li_updated_user' => $l->lp_updated_user,
            'li_created_at'   => $l->lp_created_at,
            'li_updated_at'   => $l->lp_updated_at,
        ])->all());
        $this->ajustarSecuencia('inv_lista', 'li_id');

        $localPorLista = $listas->pluck('lp_ins_code', 'lp_id');

        // ── Productos: uno por (local, producto) usado ──
        $productos = $this->v1()->table('inv_productos')->get()->keyBy('pr_id');
        $items = $this->v1()->table('inv_lista_producto_items')->get();

        // El catalogo se arma con TODOS los pares (local, producto) realmente
        // referenciados, y eso incluye los movimientos, no solo las listas.
        //
        // Nueve detalles apuntan a un producto que no esta en ninguna lista de
        // su local (locales 22, 158 y 162): probablemente la lista se cambio
        // despues del movimiento. Armando el catalogo solo desde las listas,
        // esos nueve detalles se descartaban en silencio.
        $pares = [];
        foreach ($items as $it) {
            $ins = $localPorLista[$it->lpi_lp_id] ?? null;
            if ($ins !== null) {
                $pares[$ins . ':' . $it->lpi_pr_id] = [$ins, $it->lpi_pr_id];
            }
        }

        $usadosEnMovimientos = $this->v1()->table('inv_movimiento_detalles as d')
            ->join('inv_movimientos as m', 'm.mov_id', '=', 'd.md_mov_id')
            ->select('m.mov_ins_code', 'd.md_pr_id')
            ->distinct()
            ->get();

        foreach ($usadosEnMovimientos as $u) {
            $pares[$u->mov_ins_code . ':' . $u->md_pr_id] = [$u->mov_ins_code, $u->md_pr_id];
        }

        // Sin array_chunk a proposito: `array_chunk` DESCARTA las claves de
        // texto salvo que se le pase preserve_keys, y con eso el mapa quedaba
        // indexado 0,1,2... En la primera corrida ningun item pudo resolver su
        // producto y se omitieron los 526, sin ningun error.
        $mapaProducto = [];   // "local:pr_id" => ipc_id
        foreach ($pares as $clave => [$ins, $prId]) {
            {
                $p = $productos[$prId] ?? null;
                if ($p === null) {
                    $this->nota("producto {$prId} referenciado y no existe en inv_productos");
                    continue;
                }
                $mapaProducto[$clave] = DB::table('inv_producto_catalogo')->insertGetId([
                    'ipc_ins_code'       => $ins,
                    'ipc_nombre'         => $p->pr_nombre,
                    'ipc_descripcion'    => $p->pr_descripcion,
                    'ipc_especificacion' => $p->pr_especificacion,
                    'ipc_stock_actual'   => $p->pr_stock_actual,
                    'ipc_activo'         => (bool) $p->pr_estado,
                    'ipc_created_at'     => $p->pr_created_at,
                    'ipc_updated_at'     => $p->pr_updated_at,
                ], 'ipc_id');
            }
        }

        // ── Items de lista (ids conservados) ──
        $filasItems = [];
        $itemsHuerfanos = 0;
        foreach ($items as $it) {
            $ins = $localPorLista[$it->lpi_lp_id] ?? null;
            $ipc = $mapaProducto[$ins . ':' . $it->lpi_pr_id] ?? null;
            if ($ipc === null) {
                $itemsHuerfanos++;
                continue;
            }
            $filasItems[] = [
                'lia_id'               => $it->lpi_id,
                'lia_lista_id'         => $it->lpi_lp_id,
                'lia_producto_id'      => $ipc,
                'lia_cantidad_default' => $it->lpi_cantidad,
                'lia_activo'           => (bool) $it->lpi_estado,
                'lia_created_user'     => $it->lpi_created_user,
                'lia_updated_user'     => $it->lpi_updated_user,
                'lia_created_at'       => $it->lpi_created_at,
                'lia_updated_at'       => $it->lpi_updated_at,
            ];
        }
        foreach (array_chunk($filasItems, self::LOTE) as $lote) {
            DB::table('inv_lista_item')->insert($lote);
        }
        $this->ajustarSecuencia('inv_lista_item', 'lia_id');

        // ── Movimientos: un evento por etapa con fecha ──
        $detallesPorMov = $this->v1()->table('inv_movimiento_detalles')->get()->groupBy('md_mov_id');
        $eventos = 0;
        $detalles = 0;
        $detallesSinProducto = 0;

        foreach ($this->v1()->table('inv_movimientos')->orderBy('mov_id')->cursor() as $m) {
            $etapas = [];
            if ($m->mov_recep_fecha !== null) {
                $etapas[] = ['recepcion', $m->mov_recep_fecha, $m->mov_recep_user,
                             $m->mov_recep_lat, $m->mov_recep_lng, $m->mov_recep_obsv];
            }
            if ($m->mov_devol_fecha !== null) {
                $etapas[] = ['devolucion', $m->mov_devol_fecha, $m->mov_devol_user,
                             $m->mov_devol_lat, $m->mov_devol_lng, $m->mov_devol_obsv];
            }

            foreach ($etapas as [$tipo, $fecha, $usuario, $lat, $lng, $obsv]) {
                $mcId = DB::table('inv_movimiento_cabecera')->insertGetId([
                    'mc_ins_code'      => $m->mov_ins_code,
                    'mc_lista_id'      => $m->mov_lp_id,
                    'mc_tipo'          => $tipo,
                    'mc_usuario_id'    => $usuario,
                    'mc_fecha'         => $fecha,
                    'mc_lat'           => $this->texto($lat),
                    'mc_lng'           => $this->texto($lng),
                    'mc_observaciones' => $this->texto($obsv),
                    'mc_estado'        => 'completado',
                    'mc_created_at'    => $m->mov_created_at,
                    'mc_updated_at'    => $m->mov_updated_at,
                ], 'mc_id');
                $eventos++;

                // **El detalle solo se crea para la recepcion.**
                //
                // `md_cant_devol` esta NULL en las 23.790 filas de v1: la
                // devolucion se registraba solo en la cabecera (fecha, usuario,
                // GPS), nunca producto por producto. Crear detalles para el
                // evento de devolucion obligaria a inventar la cantidad
                // devuelta -- y "asumo que devolvio todo" es exactamente la
                // clase de dato que despues alguien lee como si fuera real.
                //
                // El evento de devolucion queda con su cabecera, que es todo lo
                // que v1 sabia.
                $filasDet = [];
                foreach ($tipo === 'recepcion' ? ($detallesPorMov[$m->mov_id] ?? []) : [] as $d) {
                    $ipc = $mapaProducto[$m->mov_ins_code . ':' . $d->md_pr_id] ?? null;
                    if ($ipc === null) {
                        $detallesSinProducto++;
                        continue;
                    }

                    // Lo esperado es lo asignado y lo contado lo recibido.
                    $esperado = $d->md_cant_asign;
                    $contado  = $d->md_cant_recep;

                    $filasDet[] = [
                        'md_movimiento_id'    => $mcId,
                        'md_producto_id'      => $ipc,
                        'md_cantidad_default' => $esperado,
                        'md_cantidad_real'    => $contado,
                        'md_recibido'         => (bool) $d->md_exist,
                        'md_observacion'      => $this->texto($d->md_recep_obsv),
                        // v1 tenia un booleano; v2 distingue ok/falta/danado.
                        // "danado" no tiene origen, asi que solo se deduce si
                        // cuadra o falta.
                        'md_estado'           => ((float) $contado >= (float) $esperado) ? 'ok' : 'falta',
                        'md_created_at'       => $d->md_created_at,
                        'md_updated_at'       => $d->md_updated_at,
                    ];
                }

                foreach (array_chunk($filasDet, self::LOTE) as $lote) {
                    DB::table('inv_movimiento_detalle')->insert($lote);
                    $detalles += count($lote);
                }
            }
        }

        $this->ajustarSecuencia('inv_movimiento_cabecera', 'mc_id');
        $this->ajustarSecuencia('inv_movimiento_detalle', 'md_id');

        $movV1 = $this->v1()->table('inv_movimientos')->count();
        $this->aviso("{$movV1} movimientos de v1 -> {$eventos} eventos (uno por etapa con fecha)");
        $this->aviso(count($mapaProducto) . ' productos por local creados desde ' . $productos->count() . ' productos globales');
        if ($itemsHuerfanos > 0) {
            $this->aviso("{$itemsHuerfanos} items de lista omitidos: su producto no se pudo resolver");
        }

        $detV1 = $this->v1()->table('inv_movimiento_detalles')->count();
        $this->aviso("{$detV1} detalles de v1 -> {$detalles} (solo los de recepcion: v1 nunca guardo cantidades devueltas por producto)");
        if ($detallesSinProducto > 0) {
            $this->aviso("{$detallesSinProducto} detalles omitidos: su producto no existe en inv_productos");
        }

        return [
            // El origen que se compara es el de las LISTAS: es lo unico que
            // conserva una relacion 1 a 1 entre las dos versiones.
            'origen'     => $this->v1()->table('inv_listas_productos')->count(),
            'destino'    => DB::table('inv_lista')->count(),
            'insertadas' => $eventos + $detalles,
            'notas'      => $this->notas,
            'avisos'     => $this->avisos,
        ];
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

    private function texto($v): ?string
    {
        return $v === null ? null : (string) $v;
    }

    private function algunoConDato(array $valores): bool
    {
        foreach ($valores as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return true;
            }
        }

        return false;
    }

    /** Junta varias copias en un solo resultado, sumando los conteos. */
    private function sumar(array $partes): array
    {
        $r = ['origen' => 0, 'destino' => 0, 'insertadas' => 0, 'notas' => [], 'avisos' => []];

        foreach ($partes as $p) {
            $r['origen'] += $p['origen'];
            $r['destino'] += $p['destino'];
            $r['insertadas'] += $p['insertadas'];
            $r['notas'] = array_merge($r['notas'], $p['notas'] ?? []);
            $r['avisos'] = array_merge($r['avisos'], $p['avisos'] ?? []);
        }

        return $r;
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

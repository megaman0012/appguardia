<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Quien puede ver y hacer que en el panel, segun el perfil elegido al entrar.
 *
 * Antes esto vivia escrito a mano en 24 comprobaciones repartidas en 20 archivos,
 * del tipo:
 *
 *     in_array(Session::get('usuPF'), ['Administrador', 'Administrador General'])
 *
 * Con cinco roles eso se vuelve inmanejable: agregar uno obliga a tocar los 20
 * archivos, y olvidarse de uno deja una pantalla mal expuesta o inaccesible. Fue
 * exactamente lo que paso con UsersResource, que quedo invisible para todos
 * porque exigia un rol que nunca se creo en la base.
 *
 * Los cinco roles del sistema:
 *
 *   Vigilante        app movil, no entra al panel
 *   Supervisor       observa guardias y turnos, atiende alertas. Ve solo sus locales
 *   Consola          central 24/7: consigue el reemplazo cuando falta un guardia
 *   Lider Operativo  da de alta guardias y los asigna. Ve solo su(s) pais(es)
 *   Administrador    departamento de Sistemas: todo, sin filtro
 *   Cliente          portal de solo lectura (API), no entra al panel
 */
final class PerfilPanel
{
    public const ADMINISTRADOR         = 'Administrador';
    public const ADMINISTRADOR_GENERAL = 'Administrador General';
    public const LIDER_OPERATIVO       = 'Lider Operativo';
    public const SUPERVISOR            = 'Supervisor';
    public const CONSOLA               = 'Consola';

    /** Configuracion del sistema: clientes, geografia, catalogos, parametros. */
    private const SISTEMAS = [self::ADMINISTRADOR, self::ADMINISTRADOR_GENERAL];

    /** Gestion de personas y de locales: alta de guardias, roles, vinculos, puestos. */
    private const PERSONAL = [
        self::ADMINISTRADOR,
        self::ADMINISTRADOR_GENERAL,
        self::LIDER_OPERATIVO,
    ];

    /** Operacion diaria: rondas, accesos, alertas, novedades, inventario. */
    private const OPERACION = [
        self::ADMINISTRADOR,
        self::ADMINISTRADOR_GENERAL,
        self::LIDER_OPERATIVO,
        self::SUPERVISOR,
        self::CONSOLA,
    ];

    /**
     * Quien puede decidir que un guardia cubra un turno vacio.
     *
     * Es del Lider Operativo, pero una falta a las tres de la mañana no espera a
     * que el lider despierte: la Consola trabaja 24/7 y es quien consigue el
     * reemplazo. Por eso esta capacidad es propia y no se deduce de
     * puedeAdministrarLocales(): la Consola asigna coberturas, pero no crea
     * locales, ni puestos, ni cuadrantes.
     */
    private const COBERTURA = [
        self::ADMINISTRADOR,
        self::ADMINISTRADOR_GENERAL,
        self::LIDER_OPERATIVO,
        self::CONSOLA,
    ];

    /** Perfil elegido en la seleccion posterior al login web. */
    public static function actual(): ?string
    {
        return Session::get('usuPF');
    }

    public static function es(string ...$perfiles): bool
    {
        return in_array(self::actual(), $perfiles, true);
    }

    // ── Que puede hacer ──

    public static function puedeEntrarAlPanel(): bool
    {
        return in_array(self::actual(), self::OPERACION, true);
    }

    /** Clientes, paises/provincias/ciudades, catalogo de productos, parametros. */
    public static function puedeConfigurarSistema(): bool
    {
        return in_array(self::actual(), self::SISTEMAS, true);
    }

    /** Crear y editar usuarios, asignarles rol, local y puesto. */
    public static function puedeGestionarPersonal(): bool
    {
        return in_array(self::actual(), self::PERSONAL, true);
    }

    /**
     * Crear y editar locales y puestos.
     *
     * El Supervisor queda fuera a proposito: observa la operacion, no la define.
     * Para el los locales son de solo lectura.
     */
    public static function puedeAdministrarLocales(): bool
    {
        return in_array(self::actual(), self::PERSONAL, true);
    }

    /** Ver las pantallas operativas del dia a dia. */
    public static function puedeOperar(): bool
    {
        return in_array(self::actual(), self::OPERACION, true);
    }

    /** Elegir que guardia cubre una vacante. */
    public static function puedeAsignarCobertura(): bool
    {
        return in_array(self::actual(), self::COBERTURA, true);
    }

    // ── Que datos ve ──

    /** Supervisor: solo los locales que tiene asignados en user_has_institucion. */
    public static function alcanceEsPorInstitucion(): bool
    {
        return self::es(self::SUPERVISOR);
    }

    /** Lider Operativo: solo los locales de su(s) pais(es). */
    public static function alcanceEsPorPais(): bool
    {
        return self::es(self::LIDER_OPERATIVO);
    }

    /**
     * Sistemas y la Consola ven el total, sin filtro.
     *
     * La Consola atiende toda la operacion de madrugada: acotarla a un local o a
     * un pais la dejaria sin ver justo la falta que tiene que resolver.
     */
    public static function alcanceEsGlobal(): bool
    {
        return in_array(self::actual(), self::SISTEMAS, true) || self::es(self::CONSOLA);
    }

    /**
     * Paises asignados al usuario en sesion (user_has_pais).
     *
     * Un lider puede llevar mas de uno. Devuelve [] si no tiene ninguno, y el
     * llamador debe tratarlo como "no ve nada", no como "ve todo": sin esta
     * distincion un lider mal configurado terminaria con acceso global.
     *
     * @return int[]
     */
    public static function paisesDelUsuario(): array
    {
        $usuId = Session::get('usuID');
        if (!$usuId) {
            return [];
        }

        return DB::table('user_has_pais')
            ->where('up_usu_id', $usuId)
            ->where('up_estado', true)
            ->pluck('up_pa_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Locales que el usuario en sesion puede ver, o null si no corresponde
     * acotar (Sistemas, o un perfil cuyo alcance se resuelve de otra forma).
     *
     * @return int[]|null
     */
    public static function localesDelUsuario(): ?array
    {
        if (!self::alcanceEsPorPais()) {
            return null;
        }

        $paises = self::paisesDelUsuario();
        if (empty($paises)) {
            return [];
        }

        return DB::table('organizacion_institucion')
            ->join('ciudad', 'ciudad.cd_id', '=', 'organizacion_institucion.ins_cd_id')
            ->join('provincia', 'provincia.pr_id', '=', 'ciudad.cd_pr_id')
            ->whereIn('provincia.pr_pa_id', $paises)
            ->pluck('organizacion_institucion.ins_code')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

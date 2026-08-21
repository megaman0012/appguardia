<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\AccesoHistorial;
use Modules\Administracion\Models\AccesoPersona;
use Modules\Administracion\Models\AccesoPreregistro;
use Modules\Administracion\Models\AccesoVehiculo;
use Modules\Administracion\Models\AccesoVisitante;

class AccesoService
{
    // ── Reglas de validacion ──

    protected array $reglasBase = [
        'latitud'        => 'required|numeric',
        'longitud'       => 'required|numeric',
        'institucion'    => 'required|integer',
        'tipoAc'         => 'required|string|in:peatonal,vehicular,proveedor,empleado,visitante',
        'identificacion' => 'required',
        'nombres'        => 'required',
        'apellidos'      => 'required',
    ];

    protected array $reglasVehicular = [
        'patente' => 'required',
    ];

    protected array $reglasVisitante = [
        'motivo' => 'required',
    ];

    protected array $mensajes = [
        'latitud.required'        => 'Campo latitud es obligatorio',
        'latitud.numeric'         => 'Campo latitud debe ser numerico',
        'longitud.required'       => 'Campo longitud es obligatorio',
        'longitud.numeric'        => 'Campo longitud debe ser numerico',
        'institucion.required'    => 'Campo institucion es obligatorio',
        'tipoAc.required'         => 'Campo tipo de acceso es obligatorio',
        'tipoAc.in'               => 'Tipo de acceso no valido',
        'identificacion.required' => 'Campo identificacion es obligatorio',
        'nombres.required'        => 'Campo nombres es obligatorio',
        'apellidos.required'      => 'Campo apellidos es obligatorio',
        'patente.required'        => 'Campo patente es obligatorio para accesos vehiculares',
        'motivo.required'         => 'Campo motivo es obligatorio para visitantes y proveedores',
    ];

    /**
     * Registrar un acceso generalizado (peatonal, vehicular, proveedor, empleado, visitante).
     * Crea/adquiere la persona, el acceso, el historial y los detalles segun tipo.
     *
     * @throws ValidationException
     */
    public function registrar(array $datos, int $usuarioId, ?string $ugCode = null): Acceso
    {
        $this->validarRegistro($datos);

        return DB::transaction(function () use ($datos, $usuarioId, $ugCode) {
            $ap = AccesoPersona::firstOrCreate(
                ['ap_documento' => $datos['identificacion']],
                [
                    'ap_tip_doc'      => $datos['tip_doc'] ?? 'CI',
                    'ap_nombres'      => $datos['nombres'],
                    'ap_apellidos'    => $datos['apellidos'],
                    'ap_estado'       => true,
                    'ap_created_user' => $usuarioId,
                    'ap_updated_user' => $usuarioId,
                ]
            );

            $esEntrada = !empty($datos['isEntrada']);

            $acc = Acceso::create([
                'ac_usu_id'        => $usuarioId,
                'ac_ug_code'       => $ugCode,
                'ac_ins_code'      => $datos['institucion'],
                'ac_tipo'          => $datos['tipoAc'],
                'ac_is_entrada'    => $esEntrada ? 1 : 0,
                'ac_ap_code'       => $ap->ap_code,
                'ac_lat'           => $datos['latitud'],
                'ac_lng'           => $datos['longitud'],
                'ac_estado_acceso' => $esEntrada ? Acceso::ESTADO_EN_CURSO : Acceso::ESTADO_COMPLETADA,
                'ac_temperatura'   => $datos['temperatura'] ?? null,
                'ac_bicicleta'     => !empty($datos['isBici']),
                'ac_is_acomp'      => !empty($datos['isAcomp']),
                'ac_nomb_acomp'    => $datos['nombAcomp'] ?? null,
                'ac_rut_acomp'     => $datos['rutAcomp'] ?? null,
                'ac_observaciones' => $datos['observacion'] ?? null,
                'ac_estado'        => true,
                'ac_created_user'  => $usuarioId,
                'ac_updated_user'  => $usuarioId,
            ]);

            AccesoHistorial::registrar(
                $acc->ac_code,
                $esEntrada ? AccesoHistorial::MARCA_ENTRADA : AccesoHistorial::MARCA_SALIDA,
                $datos['latitud'],
                $datos['longitud']
            );

            if ($this->requiereDetalleVehiculo($datos)) {
                $this->crearDetalleVehiculo($acc->ac_code, $datos);
            }

            if ($this->requiereDetalleVisita($datos)) {
                $this->crearDetalleVisita($acc->ac_code, $datos);
            }

            if ($esEntrada) {
                $this->confirmarPreregistro($datos['institucion'], $ap->ap_code);
            }

            return $acc;
        });
    }

    /**
     * Registrar salida de un acceso en curso.
     *
     * @throws \RuntimeException
     */
    public function registrarSalida(int $acCode, ?string $lat = null, ?string $lng = null): Acceso
    {
        $acc = Acceso::find($acCode);

        if (!$acc) {
            throw new \RuntimeException('No se encontro informacion del codigo provisto');
        }
        if (!$acc->enCurso()) {
            throw new \RuntimeException('Al acceso se le registro como salida previamente');
        }

        return DB::transaction(function () use ($acc, $lat, $lng) {
            $acc->ac_is_entrada = 0;
            $acc->ac_lat_sal = $lat;
            $acc->ac_lng_sal = $lng;
            $acc->ac_is_salida_fecha = Carbon::now();
            $acc->ac_estado_acceso = Acceso::ESTADO_COMPLETADA;
            $acc->save();

            AccesoHistorial::registrar(
                $acc->ac_code,
                AccesoHistorial::MARCA_SALIDA,
                $lat,
                $lng
            );

            return $acc;
        });
    }

    /**
     * Crear pre-registro de visitante esperado.
     *
     * @throws ValidationException
     */
    public function crearPreregistro(array $datos, int $usuarioId): AccesoPreregistro
    {
        $validator = Validator::make($datos, [
            'institucion'    => 'required|integer',
            'fechaEstimada'  => 'required|date',
            'identificacion' => 'required',
            'nombres'        => 'required',
            'apellidos'      => 'required',
        ], [
            'institucion.required'    => 'Campo institucion es obligatorio',
            'fechaEstimada.required'  => 'Campo fecha estimada es obligatorio',
            'fechaEstimada.date'      => 'Campo fecha estimada debe ser una fecha valida',
            'identificacion.required' => 'Campo identificacion es obligatorio',
            'nombres.required'        => 'Campo nombres es obligatorio',
            'apellidos.required'      => 'Campo apellidos es obligatorio',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($datos, $usuarioId) {
            $ap = AccesoPersona::firstOrCreate(
                ['ap_documento' => $datos['identificacion']],
                [
                    'ap_tip_doc'      => $datos['tip_doc'] ?? 'CI',
                    'ap_nombres'      => $datos['nombres'],
                    'ap_apellidos'    => $datos['apellidos'],
                    'ap_estado'       => true,
                    'ap_created_user' => $usuarioId,
                    'ap_updated_user' => $usuarioId,
                ]
            );

            $preregistro = AccesoPreregistro::create([
                'apr_ins_code'      => $datos['institucion'],
                'apr_ap_code'       => $ap->ap_code,
                'apr_fecha_estimada' => $datos['fechaEstimada'],
                'apr_hora_estimada' => $datos['horaEstimada'] ?? null,
                'apr_motivo'        => $datos['motivo'] ?? null,
                'apr_area_visita'   => $datos['areaVisita'] ?? null,
                'apr_estado'        => AccesoPreregistro::ESTADO_PENDIENTE,
                'apr_token'         => bin2hex(random_bytes(20)),
                'apr_created_user'  => $usuarioId,
            ]);

            return $preregistro;
        });
    }

    public function listarPreregistros(int $insCode, ?string $fecha = null)
    {
        $query = AccesoPreregistro::with('persona')
            ->where('apr_ins_code', $insCode)
            ->orderBy('apr_fecha_estimada', 'desc')
            ->orderBy('apr_code', 'desc');

        if ($fecha) {
            $query->whereDate('apr_fecha_estimada', $fecha);
        }

        return $query->get();
    }

    /**
     * Cancelar un pre-registro pendiente.
     *
     * @throws \RuntimeException
     */
    public function cancelarPreregistro(int $aprCode): AccesoPreregistro
    {
        $preregistro = AccesoPreregistro::find($aprCode);

        if (!$preregistro) {
            throw new \RuntimeException('No se encontro el pre-registro');
        }
        if (!$preregistro->estaPendiente()) {
            throw new \RuntimeException('Solo se pueden cancelar pre-registros pendientes');
        }

        $preregistro->apr_estado = AccesoPreregistro::ESTADO_CANCELADO;
        $preregistro->save();

        return $preregistro;
    }

    // ── Metodos protegidos ──

    protected function validarRegistro(array $datos): void
    {
        $validator = Validator::make($datos, $this->reglasBase, $this->mensajes);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $tipoAc = $datos['tipoAc'];

        if (in_array($tipoAc, Acceso::TIPOS_CON_VEHICULO, true)) {
            $v = Validator::make($datos, $this->reglasVehicular, $this->mensajes);
            if ($v->fails()) {
                throw new ValidationException($v);
            }
        }

        if (in_array($tipoAc, Acceso::TIPOS_CON_VISITA, true)) {
            $v = Validator::make($datos, $this->reglasVisitante, $this->mensajes);
            if ($v->fails()) {
                throw new ValidationException($v);
            }
        }
    }

    protected function requiereDetalleVehiculo(array $datos): bool
    {
        if (in_array($datos['tipoAc'], Acceso::TIPOS_CON_VEHICULO, true)) {
            return true;
        }

        // Proveedor puede llegar en vehiculo (patente opcional)
        return $datos['tipoAc'] === Acceso::TIPO_PROVEEDOR && !empty($datos['patente']);
    }

    protected function requiereDetalleVisita(array $datos): bool
    {
        return in_array($datos['tipoAc'], Acceso::TIPOS_CON_VISITA, true);
    }

    protected function crearDetalleVehiculo(int $acCode, array $datos): void
    {
        AccesoVehiculo::create([
            'av_ac_code'      => $acCode,
            'av_patente'      => $datos['patente'],
            'av_empresa'      => $datos['empresa'] ?? null,
            'av_is_sello'     => !empty($datos['isSello']),
            'av_is_neumatico' => !empty($datos['isNeumaticos']),
            'av_is_carro'     => !empty($datos['isCarro']),
            'av_pta_llave'    => !empty($datos['isPtaConLlave']),
            'av_kms'          => $datos['kms'] ?? null,
            'av_color'        => $datos['color'] ?? null,
            'av_marca'        => $datos['marca'] ?? null,
            'av_modelo'       => $datos['modelo'] ?? null,
            'av_anio'         => $datos['anio'] ?? null,
        ]);
    }

    protected function crearDetalleVisita(int $acCode, array $datos): void
    {
        AccesoVisitante::create([
            'avi_ac_code'           => $acCode,
            'avi_motivo'            => $datos['motivo'] ?? null,
            'avi_area_visita'       => $datos['areaVisita'] ?? null,
            'avi_persona_visita'    => $datos['personaVisita'] ?? null,
            'avi_empresa_origen'    => $datos['empresaOrigen'] ?? ($datos['empresa'] ?? null),
            'avi_personas_grupo'    => $datos['personasGrupo'] ?? 1,
            'avi_duracion_estimada' => $datos['duracionEstimada'] ?? null,
        ]);
    }

    /**
     * Si existe un pre-registro pendiente para esta persona/institucion
     * con fecha estimada vencida o de hoy, lo marca como llego.
     */
    protected function confirmarPreregistro(int $insCode, int $apCode): void
    {
        AccesoPreregistro::where('apr_ins_code', $insCode)
            ->where('apr_ap_code', $apCode)
            ->where('apr_estado', AccesoPreregistro::ESTADO_PENDIENTE)
            ->whereDate('apr_fecha_estimada', '<=', Carbon::today()->toDateString())
            ->update(['apr_estado' => AccesoPreregistro::ESTADO_LLEGO]);
    }
}

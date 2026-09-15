<?php

namespace App\Services;

use App\Services\Avisos\CanalDeAviso;
use App\Services\Avisos\ResultadoDeAviso;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\AvisoEnvio;
use Modules\Administracion\Models\Alertas;

/**
 * A quién se le avisa cuando un guardia aprieta el botón de EMERGENCIA.
 *
 * ⚠️ **Esto no existía, y es la razón por la que el botón de pánico no servía
 * para nada.** La alerta se guardaba correctamente —por eso quedaba el registro
 * y aparecía en el listado— pero el único «aviso» era
 * `event(new AlertaCreada(...))`, un evento `ShouldBroadcast` emitido con
 * `BROADCAST_DRIVER=log`: el aviso se escribía en `storage/logs` y ahí moría.
 * Nadie recibía nada. Un guardia podía pedir auxilio y el sistema se limitaba a
 * anotarlo.
 *
 * El diseño es el mismo que `NotificadorVacante`, a propósito: los canales salen
 * de `config/avisos.php` y cada intento queda en `aviso_envio`, salga o no.
 *
 * Diferencia de fondo con una vacante: **una vacante puede esperar, una alerta
 * no.** Por eso acá se avisa a todos los responsables a la vez y no en
 * escalones, y por eso el canal `panel` importa tanto: es el único que no
 * depende de Firebase ni del gateway de WhatsApp.
 *
 * Como en vacantes, un aviso nunca puede hacer fallar lo que lo originó: si el
 * envío revienta, la alerta ya está creada y visible en la pantalla «Alertas de
 * hoy».
 */
class NotificadorAlerta
{
    /** @var CanalDeAviso[] */
    private array $canales;

    public function __construct()
    {
        /*
         * Un canal que no se puede construir se descarta y se sigue con los
         * demas.
         *
         * Esto no es defensa por las dudas: el notificador se inyecta en el
         * constructor del listener, asi que una clase mal escrita en
         * `config/avisos.php` reventaba al RESOLVER el listener -- fuera del
         * try/catch del `handle()` -- y el pedido de auxilio terminaba en un
         * 500. Un typo en la configuracion no puede dejar sin boton de panico a
         * los guardias.
         */
        $this->canales = [];

        foreach (config('avisos.canales', []) as $clase) {
            try {
                $this->canales[] = app($clase);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Se creó una alerta: se le avisa a quien puede atenderla.
     *
     * @return int cuántos avisos salieron por al menos un canal
     */
    public function alertaCreada(Alertas $alerta): int
    {
        return $this->avisarA(
            $this->responsables($alerta),
            $this->titulo($alerta),
            $this->cuerpo($alerta),
            $alerta,
            'alerta_creada'
        );
    }

    /**
     * El título lleva la prioridad adelante porque en una lista de avisos es lo
     * único que se alcanza a leer.
     */
    public function titulo(Alertas $alerta): string
    {
        $local = optional($alerta->institucion)->ins_descripcion ?? "Local {$alerta->al_ins_code}";

        return match ($alerta->al_prioridad) {
            'critica' => "🚨 EMERGENCIA en {$local}",
            'alta'    => "⚠️ Alerta alta en {$local}",
            default   => "Alerta en {$local}",
        };
    }

    /**
     * El cuerpo dice quién, dónde y qué pasa, en ese orden.
     *
     * La ubicación va como enlace a un mapa sólo si es real: la app manda `0/0`
     * cuando el GPS no respondió —a propósito, para que una emergencia no se
     * pierda por estar bajo techo— y mandar a alguien al golfo de Guinea en una
     * emergencia sería peor que no mandarlo a ninguna parte.
     */
    public function cuerpo(Alertas $alerta): string
    {
        $guardia = optional($alerta->usuario)->usu_nmbcom ?? "Usuario {$alerta->al_usu_id}";

        $partes = [
            $guardia . ': ' . $alerta->al_observacion,
        ];

        if ($this->ubicacionEsReal($alerta)) {
            $partes[] = sprintf(
                'Ubicación: https://maps.google.com/?q=%s,%s',
                $alerta->al_lat,
                $alerta->al_lng
            );
        } else {
            $partes[] = 'Sin ubicación: el dispositivo no entregó coordenadas.';
        }

        return implode("\n", $partes);
    }

    private function ubicacionEsReal(Alertas $alerta): bool
    {
        $lat = (float) $alerta->al_lat;
        $lng = (float) $alerta->al_lng;

        return abs($lat) > 0.0001 || abs($lng) > 0.0001;
    }

    /**
     * Quién puede atender esta alerta.
     *
     * Supervisores del local y Consola. La Consola no se acota por local ni por
     * país porque de madrugada es la única que está mirando; el mismo criterio
     * que en `NotificadorVacante::responsables()`.
     *
     * Se consulta con `join` sobre `user_has_institucion` y no con
     * `whereHas('instituciones')`: **esa relación no existe en ninguno de los
     * dos modelos `users`**, y confiar en ella es lo que tuvo roto el
     * escalamiento de alertas.
     *
     * @return int[]
     */
    public function responsables(Alertas $alerta): array
    {
        $supervisores = DB::table('users as u')
            ->join('user_has_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('user_has_institucion as ui', 'ui.ui_usu_id', '=', 'u.id')
            ->where('ui.ui_ins_code', $alerta->al_ins_code)
            ->where('ui.ui_state', 1)
            ->where('u.usu_state', 1)
            ->where('r.name', 'Supervisor')
            ->pluck('u.id');

        $consola = DB::table('users as u')
            ->join('user_has_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('u.usu_state', 1)
            ->whereIn('r.name', ['Consola', 'Administrador'])
            ->pluck('u.id');

        return $consola->merge($supervisores)
            ->unique()
            // Avisarle al propio guardia que él pidió auxilio no aporta nada.
            ->reject(fn ($id) => (int) $id === (int) $alerta->al_usu_id)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Deja constancia del intento, haya salido o no.
     *
     * En una emergencia la pregunta que importa después es «¿el supervisor se
     * enteró?», y sin esto no hay forma de contestarla.
     */
    private function registrar(
        int $usuarioId,
        CanalDeAviso $canal,
        ResultadoDeAviso $resultado,
        string $titulo,
        string $cuerpo,
        Alertas $alerta,
        string $tipo
    ): void {
        try {
            AvisoEnvio::create([
                'ae_usu_id'    => $usuarioId,
                'ae_canal'     => $canal->nombre(),
                'ae_tipo'      => $tipo,
                'ae_titulo'    => $titulo,
                'ae_cuerpo'    => $cuerpo,
                'ae_destino'   => $resultado->destino,
                'ae_direccion' => AvisoEnvio::SALIENTE,
                'ae_resultado' => $resultado->resultado,
                'ae_detalle'   => $resultado->detalle,
                'ae_al_code'   => $alerta->al_code,
            ]);
        } catch (\Throwable $e) {
            // Ni siquiera el registro puede tumbar la operación.
            report($e);
        }
    }

    /**
     * @param int[] $usuarios
     * @return int cuántos avisos salieron por al menos un canal
     */
    private function avisarA(array $usuarios, string $titulo, string $cuerpo, Alertas $alerta, string $tipo): int
    {
        $datos = [
            'tipo'      => $tipo,
            'al_code'   => $alerta->al_code,
            'ins_code'  => $alerta->al_ins_code,
            'prioridad' => $alerta->al_prioridad,
            'pantalla'  => 'Alertas',
            'url'       => $this->urlDeLaAlerta($alerta),
        ];

        $enviados = 0;

        foreach (array_unique($usuarios) as $usuarioId) {
            $llego = false;

            foreach ($this->canales as $canal) {
                try {
                    $resultado = $canal->enviar((int) $usuarioId, $titulo, $cuerpo, $datos);
                } catch (\Throwable $e) {
                    // Un canal caído no puede tumbar al resto ni a la operación.
                    report($e);
                    $resultado = ResultadoDeAviso::fallido($e->getMessage());
                }

                $this->registrar((int) $usuarioId, $canal, $resultado, $titulo, $cuerpo, $alerta, $tipo);

                $llego = $llego || $resultado->ok();
            }

            if ($llego) {
                $enviados++;
            }
        }

        return $enviados;
    }

    /**
     * El enlace a la alerta en el panel.
     *
     * Se arma con `getUrl()` y no a mano porque en Filament 3+ los nombres de
     * ruta llevan el id del panel en el medio, y escribirlos a mano ya rompió
     * dos listados de este proyecto.
     */
    private function urlDeLaAlerta(Alertas $alerta): ?string
    {
        try {
            return \App\Filament\Resources\AlertasResource::getUrl('index');
        } catch (\Throwable $e) {
            // Fuera de una petición del panel (cola, consola) puede no resolver.
            return null;
        }
    }
}

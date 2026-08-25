<?php

namespace App\Services\Avisos;

use App\Services\NotificadorVacante;
use App\Services\VacanteService;
use Carbon\Carbon;
use Modules\Administracion\Models\AvisoEnvio;
use Modules\Administracion\Models\TurnoVacante;
use Modules\MobileApp\Models\users;

/**
 * Interpreta lo que el guardia contesta por WhatsApp.
 *
 * El guardia que puede cubrir un turno está **franco, en su casa**. La app vive
 * en las tablets de los puestos, así que no la tiene: WhatsApp es el único
 * camino hasta él, y su respuesta tiene que entrar al sistema sola. Si alguien
 * tuviera que leer los mensajes y cargarlos a mano, a las tres de la mañana no
 * pasaría.
 */
class RespuestaWhatsapp
{
    /** Cuánto hacia atrás se busca la convocatoria que originó la respuesta. */
    private const HORAS_DE_VIGENCIA = 24;

    private const AFIRMATIVAS = ['si', 'sí', 'sii', 'acepto', 'ok', 'okey', 'dale', 'voy', 'listo', 'yo', 'confirmo', '1'];
    private const NEGATIVAS   = ['no', 'nel', 'nop', 'imposible', 'nopuedo', '2'];

    public function __construct(
        private VacanteService $vacantes,
        private NotificadorVacante $notificador,
        private EvolutionApi $api
    ) {
    }

    /**
     * @return array{estado: string, detalle: string}
     */
    public function procesar(string $numeroCrudo, string $texto): array
    {
        $numero = NumeroWhatsapp::normalizar($numeroCrudo);
        $guardia = $this->buscarGuardia($numero);

        if (!$guardia) {
            // Un desconocido escribiendo al número de la empresa no es un error:
            // simplemente no hay nada que hacer con eso.
            return ['estado' => 'desconocido', 'detalle' => 'No hay un usuario con ese número'];
        }

        $intencion = $this->intencion($texto);
        $codigo    = $this->codigo($texto);
        $ofertas   = $this->ofertasVigentes((int) $guardia->id);

        if ($ofertas->isEmpty()) {
            return $this->responder($numero, 'No tiene ninguna convocatoria abierta en este momento.', 'sin_ofertas');
        }

        if ($intencion === 'desconocida') {
            return $this->responder(
                $numero,
                "No entendí la respuesta.\nResponda SI seguido del número del turno, por ejemplo: SI {$ofertas->first()->tv_id}",
                'no_entendida'
            );
        }

        $vacante = $this->elegirVacante($ofertas, $codigo);

        if (!$vacante) {
            $lista = $ofertas->map(fn ($v) => "{$v->tv_id}: {$v->descripcion}")->implode("\n");

            return $this->responder(
                $numero,
                "Tiene más de un turno ofrecido. Indique cuál:\n{$lista}",
                'ambigua'
            );
        }

        if ($intencion === 'negativa') {
            $this->registrar($guardia->id, $vacante, 'respuesta_negativa', 'El guardia respondió que no puede');

            return $this->responder($numero, 'Entendido, gracias por avisar.', 'negativa');
        }

        return $this->aceptar($guardia, $vacante, $numero);
    }

    // ── Pasos ──

    private function aceptar(users $guardia, TurnoVacante $vacante, string $numero): array
    {
        if (!$vacante->admitePostulaciones()) {
            return $this->responder($numero, 'Ese turno ya fue cubierto por otro guardia. Gracias igual.', 'ya_cubierta');
        }

        $motivo = $this->vacantes->motivoParaNoCubrir($vacante, (int) $guardia->id);

        if ($motivo !== null) {
            // Se le dice el motivo: "ya tiene un turno a esa hora" es
            // información útil, "no se pudo" no lo es.
            return $this->responder($numero, "No es posible asignarle ese turno: {$motivo}", 'no_elegible');
        }

        $this->vacantes->postular($vacante, (int) $guardia->id, 'wa-' . $vacante->tv_id . '-' . $guardia->id);

        // La Consola y el Líder tienen que enterarse ahora, no cuando alguien
        // entre al panel a mirar.
        $this->notificador->postulacionRecibida($vacante, (int) $guardia->id);

        return $this->responder(
            $numero,
            'Recibido. La central le confirmará si queda asignado al turno.',
            'aceptada'
        );
    }

    /** @return \Illuminate\Support\Collection<int, TurnoVacante> */
    private function ofertasVigentes(int $usuarioId)
    {
        $ids = AvisoEnvio::where('ae_usu_id', $usuarioId)
            ->where('ae_canal', 'whatsapp')
            ->where('ae_resultado', AvisoEnvio::ENVIADO)
            ->whereIn('ae_tipo', ['vacante_abierta', 'vacante_escalada'])
            ->where('created_at', '>=', Carbon::now()->subHours(self::HORAS_DE_VIGENCIA))
            ->pluck('ae_tv_id')
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return TurnoVacante::with(['puesto', 'institucion'])
            ->whereIn('tv_id', $ids)
            ->abiertas()
            ->get()
            ->filter(fn (TurnoVacante $v) => $v->admitePostulaciones())
            ->values();
    }

    private function elegirVacante($ofertas, ?int $codigo): ?TurnoVacante
    {
        if ($codigo !== null) {
            return $ofertas->firstWhere('tv_id', $codigo);
        }

        // Sin código solo se puede decidir si hay una sola oferta: adivinar cuál
        // aceptó mandaría a alguien al puesto equivocado.
        return $ofertas->count() === 1 ? $ofertas->first() : null;
    }

    private function buscarGuardia(?string $numero): ?users
    {
        if (!$numero) {
            return null;
        }

        // Los números se guardan en varios formatos; se compara el normalizado.
        return users::where('usu_state', 1)
            ->whereNotNull('usu_whatsapp')
            ->get()
            ->first(fn (users $u) => NumeroWhatsapp::normalizar($u->usu_whatsapp) === $numero);
    }

    private function intencion(string $texto): string
    {
        $limpio = $this->limpiar($texto);

        foreach (self::AFIRMATIVAS as $palabra) {
            if ($limpio === $palabra || str_starts_with($limpio, $palabra . ' ') || str_starts_with($limpio, $palabra)) {
                return 'afirmativa';
            }
        }

        foreach (self::NEGATIVAS as $palabra) {
            if ($limpio === $palabra || str_starts_with($limpio, $palabra)) {
                return 'negativa';
            }
        }

        return 'desconocida';
    }

    private function codigo(string $texto): ?int
    {
        return preg_match('/\b(\d{1,9})\b/', $texto, $m) ? (int) $m[1] : null;
    }

    private function limpiar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return preg_replace('/[^a-z0-9 ]/', '', $texto);
    }

    /** @return array{estado: string, detalle: string} */
    private function responder(string $numero, string $texto, string $estado): array
    {
        $this->api->enviarTexto($numero, $texto);

        return ['estado' => $estado, 'detalle' => $texto];
    }

    private function registrar(int $usuarioId, TurnoVacante $vacante, string $tipo, string $detalle): void
    {
        AvisoEnvio::create([
            'ae_usu_id'    => $usuarioId,
            'ae_canal'     => 'whatsapp',
            'ae_tipo'      => $tipo,
            'ae_titulo'    => 'Respuesta del guardia',
            'ae_cuerpo'    => $detalle,
            'ae_resultado' => AvisoEnvio::ENVIADO,
            'ae_detalle'   => $detalle,
            'ae_tv_id'     => $vacante->tv_id,
        ]);
    }
}

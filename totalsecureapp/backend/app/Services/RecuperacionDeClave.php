<?php

namespace App\Services;

use App\Services\Avisos\CanalWhatsApp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;

/**
 * Recuperar la clave sin que eso sea una puerta para entrar a cualquier cuenta.
 *
 * ⚠️ **Lo que habia antes no era un flujo de recuperacion, era una toma de
 * cuentas publicada en internet.** `POST /api/procesar_paswchg` cambiaba la
 * contrasena de cualquier usuario con solo mandar su `user_id` -- un entero
 * secuencial -- sin token y sin autenticacion. Y `solicitud_paswchg` devolvia
 * ese `user_id` junto con el token a cambio de una cedula, que en Ecuador no es
 * un dato secreto.
 *
 * Los metodos reciben `Model` y no un `users` concreto porque **este proyecto
 * tiene dos modelos para la misma tabla**: `Modules\Acceso\Models\users` (el
 * portal y el panel) y `Modules\MobileApp\Models\users` (la API). Los dos flujos
 * de recuperacion pasan por aca, asi que atarlo a uno rompia el otro.
 *
 * Las cuatro reglas que lo vuelven un flujo de verdad:
 *
 * 1. **El codigo se guarda hasheado.** Antes viajaba y se almacenaba en claro en
 *    `remember_token`: quien leyera la base tenia las cuentas.
 * 2. **Caduca.** Antes no vencia nunca.
 * 3. **Se usa una sola vez**, y se invalida al quinto intento fallido, porque un
 *    codigo numerico sin limite se adivina por fuerza bruta.
 * 4. **No vuelve en la respuesta.** Tiene que salir por un canal que solo alcance
 *    al dueno de la cuenta; si no, no es una prueba de nada.
 *
 * ⚠️ **Hoy no hay ningun canal configurado** (el correo apunta a un `mailhog`
 * que no existe y el gateway de WhatsApp esta vacio), asi que en la practica el
 * autoservicio queda sin efecto hasta que se cargue uno desde
 * `/admin/configuracion`. Es a proposito y esta decidido: mientras tanto el
 * supervisor cambia la clave desde el panel, con la accion «Cambiar contrasena»
 * de Usuarios. Un autoservicio que le entrega la cuenta a cualquiera es peor que
 * no tener autoservicio.
 */
class RecuperacionDeClave
{
    /** Minutos que vale un codigo. */
    public const VIGENCIA_MINUTOS = 30;

    /** Intentos fallidos antes de invalidarlo. */
    public const INTENTOS_MAXIMOS = 5;

    public function __construct(private CanalWhatsApp $whatsapp)
    {
    }

    /**
     * Emite un codigo nuevo y lo manda por donde se pueda.
     *
     * @return string[] los canales por los que efectivamente salio
     */
    public function emitir(Model $usuario, ?string $urlBase = null): array
    {
        /*
         * `random_int`, no `rand`. El codigo anterior usaba `rand(1000, 10000000)`,
         * que no es criptografico: su secuencia se puede predecir conociendo
         * unos pocos valores, y los valores anteriores viajaban en la respuesta
         * de un endpoint publico.
         */
        $codigo = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        $usuario->usu_reset_token = hash('sha256', $codigo);
        $usuario->usu_reset_expira = Carbon::now()->addMinutes(self::VIGENCIA_MINUTOS);
        $usuario->usu_reset_intentos = 0;
        $usuario->save();

        $enlace = $urlBase ? rtrim($urlBase, '/') . '/' . $codigo : null;

        $salieron = [];

        if ($this->porWhatsapp($usuario, $codigo, $enlace)) {
            $salieron[] = 'whatsapp';
        }

        if ($this->porCorreo($usuario, $codigo, $enlace)) {
            $salieron[] = 'correo';
        }

        if ($salieron === []) {
            // Sin destinatario no hay recuperacion posible: que quede en el log
            // para que se pueda explicar por que un guardia no recibe nada.
            Log::warning('Recuperacion de clave sin canal de entrega', [
                'usuario' => $usuario->id,
                'tiene_whatsapp' => (bool) $usuario->usu_whatsapp,
                'tiene_email' => (bool) $usuario->usu_email,
            ]);
        }

        return $salieron;
    }

    /**
     * Comprueba el codigo y lo consume.
     *
     * Devuelve `null` si esta bien, o el motivo del rechazo. El motivo es para
     * el log y para el usuario legitimo, **no** dice nunca si la cuenta existe.
     */
    public function verificar(Model $usuario, ?string $codigo): ?string
    {
        if (!$usuario->usu_reset_token || !$usuario->usu_reset_expira) {
            return 'No hay un código pendiente. Solicite uno nuevo.';
        }

        if (Carbon::now()->greaterThan(Carbon::parse($usuario->usu_reset_expira))) {
            $this->invalidar($usuario);

            return 'El código venció. Solicite uno nuevo.';
        }

        if ($usuario->usu_reset_intentos >= self::INTENTOS_MAXIMOS) {
            $this->invalidar($usuario);

            return 'Demasiados intentos. Solicite un código nuevo.';
        }

        /*
         * `hash_equals` y no `==`: comparar cadenas con `==` tarda mas cuanto
         * mas coinciden, y esa diferencia de tiempo alcanza para ir adivinando
         * el codigo caracter por caracter contra un endpoint publico.
         */
        if (!is_string($codigo) || !hash_equals($usuario->usu_reset_token, hash('sha256', $codigo))) {
            $usuario->usu_reset_intentos = $usuario->usu_reset_intentos + 1;
            $usuario->save();

            return 'Código incorrecto.';
        }

        return null;
    }

    /** Un codigo usado no se puede volver a usar. */
    public function invalidar(Model $usuario): void
    {
        $usuario->usu_reset_token = null;
        $usuario->usu_reset_expira = null;
        $usuario->usu_reset_intentos = 0;
        $usuario->save();
    }

    /**
     * WhatsApp es el canal realista de este sistema: la app vive en las tablets
     * de los puestos, no en el telefono del guardia, asi que quien perdio la
     * clave no tiene como recibir un push.
     *
     * No se exige `usu_acepta_whatsapp`: ese consentimiento es para que no le
     * escriban avisos de trabajo al telefono personal, y esto es un codigo de
     * seguridad que el propio usuario acaba de pedir.
     */
    private function porWhatsapp(Model $usuario, string $codigo, ?string $enlace = null): bool
    {
        if (empty($usuario->usu_whatsapp) || empty(config('avisos.whatsapp.url'))) {
            return false;
        }

        try {
            $resultado = $this->whatsapp->enviar(
                $usuario->id,
                'Código de recuperación',
                $this->mensaje($codigo, $enlace),
                ['tipo' => 'reset_password']
            );

            return $resultado->ok();
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function porCorreo(Model $usuario, string $codigo, ?string $enlace = null): bool
    {
        $remitente = config('mail.from.address');

        // Sin remitente configurado el envio falla con «An email must have a
        // "From" or "Sender" header», que es el error que hoy recibe quien pide
        // restablecer su clave. Mejor no intentarlo y decirlo claro.
        if (empty($remitente) || empty($usuario->usu_email) || !filter_var($usuario->usu_email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::raw($this->mensaje($codigo, $enlace), function ($m) use ($usuario) {
                $m->to($usuario->usu_email, $usuario->usu_nmbcom)
                  ->subject('Código de recuperación de contraseña');
            });

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function mensaje(string $codigo, ?string $enlace = null): string
    {
        $texto = sprintf(
            "Su código para cambiar la contraseña es: %s\n\n".
            "Vence en %d minutos y sirve una sola vez.\n",
            $codigo,
            self::VIGENCIA_MINUTOS
        );

        // El flujo del portal manda un enlace; el de la app, solo el código,
        // porque ahí se teclea en la pantalla siguiente.
        if ($enlace) {
            $texto .= "\nTambién puede entrar directamente: " . $enlace . "\n";
        }

        return $texto . "\nSi usted no lo pidió, ignore este mensaje y avise a su supervisor.";
    }
}

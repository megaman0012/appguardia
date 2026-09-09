<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Los ajustes que se editan desde el panel, en vez de por SSH.
 *
 * **Que resuelve.** El correo saliente y WhatsApp salian del `.env`, asi que
 * cambiar el servidor SMTP obligaba a entrar al servidor, editar un archivo y
 * reiniciar el contenedor. Ahora viven en la tabla `configuracion` y los pisa
 * `AjustesServiceProvider` al arrancar.
 *
 * **Lo que NO se mueve al panel.** Solo lo que un administrador puede necesitar
 * cambiar en caliente. La base de datos, `APP_KEY` y `APP_URL` siguen en el
 * `.env`: si se pudieran editar desde el panel, un error dejaria la aplicacion
 * sin forma de arrancar para volver a corregirlo.
 */
class Ajustes
{
    /** Una sola consulta por peticion, y compartida entre peticiones. */
    private const CACHE = 'ajustes.todos';

    /**
     * Las claves que van CIFRADAS en la base.
     *
     * ⚠️ Se cifran con `APP_KEY`, la misma que cifra los QR de las rondas.
     * Rotarla deja estos valores ilegibles: habria que volver a cargarlos.
     */
    public const CIFRADAS = [
        'mail.password',
        'whatsapp.api_key',
    ];

    /**
     * Todo lo editable, con su valor por defecto.
     *
     * El defecto se toma del `.env` para que **encender esto no cambie nada**:
     * mientras nadie edite el formulario, el sistema sigue usando exactamente
     * lo que usaba antes.
     *
     * @return array<string, mixed>
     */
    public static function defectos(): array
    {
        return [
            'mail.host'         => config('mail.mailers.smtp.host'),
            'mail.port'         => config('mail.mailers.smtp.port'),
            'mail.username'     => config('mail.mailers.smtp.username'),
            'mail.password'     => config('mail.mailers.smtp.password'),
            'mail.encryption'   => config('mail.mailers.smtp.encryption'),
            'mail.from_address' => config('mail.from.address'),
            'mail.from_name'    => config('mail.from.name'),

            'whatsapp.url'       => config('avisos.whatsapp.url'),
            'whatsapp.instancia' => config('avisos.whatsapp.instancia'),
            'whatsapp.api_key'   => config('avisos.whatsapp.api_key'),
        ];
    }

    /**
     * Todos los ajustes guardados, ya descifrados.
     *
     * @return array<string, string|null>
     */
    public static function todos(): array
    {
        return Cache::rememberForever(self::CACHE, function (): array {
            $valores = [];

            foreach (DB::table('configuracion')->get() as $fila) {
                $valores[$fila->cf_clave] = $fila->cf_cifrado
                    ? self::descifrar($fila->cf_clave, $fila->cf_valor)
                    : $fila->cf_valor;
            }

            return $valores;
        });
    }

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        $valor = self::todos()[$clave] ?? null;

        // `??` no alcanza: una fila guardada como cadena vacia significa «sin
        // valor» y tiene que caer al defecto igual que una ausente. Si no, un
        // usuario que borra el campo del formulario deja el correo apuntando a
        // un host vacio en vez de volver al del `.env`.
        return blank($valor) ? $defecto : $valor;
    }

    /** @param array<string, mixed> $valores */
    public static function guardar(array $valores, ?int $usuario = null): void
    {
        DB::transaction(function () use ($valores, $usuario) {
            foreach ($valores as $clave => $valor) {
                $cifrado = in_array($clave, self::CIFRADAS, true);

                DB::table('configuracion')->updateOrInsert(
                    ['cf_clave' => $clave],
                    [
                        'cf_valor' => ($cifrado && filled($valor))
                            ? Crypt::encryptString((string) $valor)
                            : ($valor === null ? null : (string) $valor),
                        'cf_cifrado'      => $cifrado && filled($valor),
                        'cf_updated_user' => $usuario,
                        'updated_at'      => now(),
                        'created_at'      => now(),
                    ],
                );
            }
        });

        self::olvidar();
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CACHE);
    }

    /**
     * ¿Se puede leer la tabla?
     *
     * ⚠️ **Esto es lo que evita dejar el proyecto imposible de levantar.** El
     * proveedor lee los ajustes en `boot()`, y `boot()` corre TAMBIEN durante
     * `php artisan migrate` -- o sea antes de que la tabla exista. Sin esta
     * comprobacion, una instalacion limpia no puede correr la migracion que
     * crea la tabla que el proveedor necesita: un huevo y gallina que deja el
     * proyecto muerto y con un mensaje que no explica nada.
     *
     * Y no es hipotetico en este repo: un error en `AppServiceProvider::boot()`
     * ya tumbo la aplicacion entera --panel y API-- dos veces durante la
     * migracion a Filament 3 y 4.
     */
    public static function disponible(): bool
    {
        try {
            return Schema::hasTable('configuracion');
        } catch (\Throwable $e) {
            // Sin base de datos tampoco hay ajustes. No se registra: pasa en
            // cada `artisan` de una instalacion nueva y llenaria el log.
            return false;
        }
    }

    private static function descifrar(string $clave, ?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (\Throwable $e) {
            // Pasa si se roto `APP_KEY`. Devolver null hace que caiga al
            // defecto del `.env`, que es degradarse y no romperse -- pero hay
            // que enterarse, porque significa que la contraseña guardada ya no
            // sirve y hay que volver a cargarla.
            Log::warning("No se pudo descifrar el ajuste «{$clave}». ¿Cambió APP_KEY? Hay que volver a cargarlo desde el panel.");

            return null;
        }
    }
}

<?php

namespace App\Providers;

use App\Support\Ajustes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Pisa la configuracion de correo y WhatsApp con lo guardado en la base.
 *
 * ⚠️ **Todo este `boot()` esta envuelto en un try/catch, y no es exceso de
 * celo.** En este proyecto un error dentro del `boot()` de un proveedor ya
 * tumbo la aplicacion ENTERA --panel y API, con guardias marcando-- dos veces
 * durante la migracion: una por `Filament::registerNavigationGroups()` que
 * murio en Filament 3, y otra por el enum `MaxWidth` que se renombro en
 * Filament 4. Un proveedor que falla no degrada una pantalla: no deja arrancar
 * nada.
 *
 * Aca el riesgo es peor todavia, porque este proveedor **consulta la base de
 * datos al arrancar**. Si la base esta caida, o la tabla no existe porque
 * todavia no se corrio la migracion, sin proteccion el sistema no levanta -- y
 * tampoco se puede correr `php artisan migrate` para arreglarlo, porque
 * `migrate` tambien arranca los proveedores.
 *
 * La regla, entonces: **si algo sale mal, no se pisa nada y sigue valiendo el
 * `.env`**. Peor configuracion, pero aplicacion viva.
 */
class AjustesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            if (!Ajustes::disponible()) {
                return;
            }

            $this->aplicarCorreo();
            $this->aplicarWhatsapp();
        } catch (\Throwable $e) {
            // Se registra pero NO se relanza: mejor el sistema en pie con la
            // configuracion del `.env` que el sistema entero caido.
            Log::warning('No se pudieron aplicar los ajustes guardados: ' . $e->getMessage());
        }
    }

    private function aplicarCorreo(): void
    {
        $mapa = [
            'mail.mailers.smtp.host'       => 'mail.host',
            'mail.mailers.smtp.port'       => 'mail.port',
            'mail.mailers.smtp.username'   => 'mail.username',
            'mail.mailers.smtp.password'   => 'mail.password',
            'mail.mailers.smtp.encryption' => 'mail.encryption',
            'mail.from.address'            => 'mail.from_address',
            'mail.from.name'               => 'mail.from_name',
        ];

        foreach ($mapa as $destino => $clave) {
            $valor = Ajustes::get($clave);

            // Solo se pisa lo que tiene valor guardado. Un campo vacio en el
            // formulario no debe borrar lo que dice el `.env`: debe no opinar.
            if (filled($valor)) {
                config([$destino => $valor]);
            }
        }

        /*
         * ⚠️ **El tiempo de espera importa mas de lo que parece.**
         * `QUEUE_CONNECTION` esta en `sync`, asi que el correo se manda DENTRO
         * de la peticion HTTP. Con mailhog es instantaneo, pero apuntando a un
         * SMTP real que no responde, la persona se queda mirando la pantalla
         * hasta que el sistema operativo corte la conexion -- pueden ser dos
         * minutos.
         *
         * Diez segundos es suficiente para un servidor sano y corto para que un
         * servidor caido no bloquee a nadie. La solucion de fondo es un worker
         * de cola, que sigue pendiente.
         */
        config(['mail.mailers.smtp.timeout' => (int) config('mail.mailers.smtp.timeout', 10) ?: 10]);
    }

    private function aplicarWhatsapp(): void
    {
        foreach ([
            'avisos.whatsapp.url'       => 'whatsapp.url',
            'avisos.whatsapp.instancia' => 'whatsapp.instancia',
            'avisos.whatsapp.api_key'   => 'whatsapp.api_key',
        ] as $destino => $clave) {
            $valor = Ajustes::get($clave);

            if (filled($valor)) {
                config([$destino => $valor]);
            }
        }
    }
}

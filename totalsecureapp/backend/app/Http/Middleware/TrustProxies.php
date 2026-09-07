<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = [
        // Rangos privados, no una IP fija.
        //
        // Aqui habia una sola IP, '10.72.80.119', de una red que este servidor
        // no tiene: quedo de la instalacion original. Con ella, ningun proxy era
        // de confianza y las cabeceras X-Forwarded-* se ignoraban.
        //
        // La cadena real es: nginx del host (127.0.0.1:3031) -> docker-proxy ->
        // ts_nginx -> php-fpm. PHP nunca ve al cliente: ve a ts_nginx, con una
        // IP de la red docker que CAMBIA al recrear el stack. Por eso van rangos
        // y no direcciones.
        //
        // Sin esto: $request->ip() devuelve la IP del contenedor para todos (los
        // logs de trafico dejan de servir para nada) y, en cuanto se ponga
        // HTTPS, url() generara enlaces http:// porque no se cree el
        // X-Forwarded-Proto -> contenido mixto y el correo de cambio de
        // contrasena apuntando a http.
        //
        // Contrapartida: quien alcance el 3031 directo puede falsear su IP en el
        // log. Se cierra publicando el 3031 solo en 127.0.0.1 (ver
        // DESPLIEGUE-IP-Y-PUERTO-80.md); mientras siga abierto, el dato afecta
        // al log, no a la autenticacion.
        '127.0.0.1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}

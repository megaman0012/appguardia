<?php
/**
 * ¿Sigue php-fpm aceptando trabajo?
 *
 * Lo usa el healthcheck del contenedor. Existe como archivo, y no como una linea
 * dentro del `docker-compose.yml`, por dos razones concretas:
 *
 *  - Un comando PHP en el YAML necesita escapar cada `$` como `$$`, y eso se
 *    rompe en silencio: el healthcheck pasaria a ejecutar algo que no es lo que
 *    se escribio, y un healthcheck roto **siempre da sano**.
 *  - El `sh` de esta imagen es dash, que no soporta `/dev/tcp`, y no hay `nc`,
 *    `curl` ni `cgi-fcgi` instalados. Abrir el socket desde PHP es lo unico
 *    disponible.
 *
 * Abrir el puerto 9000 prueba lo que importa: que el master sigue aceptando
 * conexiones. Un `php -v` solo probaria que el binario existe.
 */

$conexion = @fsockopen('127.0.0.1', 9000, $errno, $errstr, 2);

if ($conexion === false) {
    fwrite(STDERR, "php-fpm no acepta conexiones en 9000: {$errstr} ({$errno})\n");
    exit(1);
}

fclose($conexion);
exit(0);

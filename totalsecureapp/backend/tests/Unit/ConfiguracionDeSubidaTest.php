<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Que el proyecto DECLARE limites de subida suficientes.
 *
 * ⚠️ El marcaje biometrico estuvo roto en produccion por esto y ningun test lo
 * veia: la imagen de PHP trae `upload_max_filesize = 2M` de fabrica, la camara
 * de una tablet saca fotos de 3 a 5 MB, y **PHP descartaba el archivo antes de
 * que Laravel lo viera**. El guardia recibia «el archivo no se pudo cargar», un
 * mensaje que no menciona el tamaño.
 *
 * El valor efectivo de php-fpm no se puede comprobar desde PHPUnit --la suite
 * corre por CLI, con otra configuracion-- asi que lo que se fija aca es lo que
 * el proyecto declara y monta. Es la diferencia entre «funciona en mi maquina» y
 * «el contenedor de produccion lo va a tomar».
 */
class ConfiguracionDeSubidaTest extends TestCase
{
    private function ini(): string
    {
        $ruta = base_path('docker/php/uploads.ini');

        $this->assertFileExists(
            $ruta,
            'Falta docker/php/uploads.ini: sin el, PHP queda con upload_max_filesize = 2M '
            . 'y las fotos de camara no se pueden subir.'
        );

        return file_get_contents($ruta);
    }

    private function mb(string $ini, string $clave): int
    {
        $this->assertMatchesRegularExpression(
            "/^{$clave}\s*=\s*(\d+)M/m",
            $ini,
            "uploads.ini no declara {$clave} en MB."
        );

        preg_match("/^{$clave}\s*=\s*(\d+)M/m", $ini, $m);

        return (int) $m[1];
    }

    /** Una foto de camara de tablet ronda los 5 MB sin comprimir. */
    public function test_el_limite_de_subida_admite_una_foto_de_camara(): void
    {
        $this->assertGreaterThanOrEqual(
            8,
            $this->mb($this->ini(), 'upload_max_filesize'),
            'upload_max_filesize es menor que una foto de camara: el marcaje va a fallar.'
        );
    }

    /**
     * `post_max_size` engloba TODO el cuerpo, no solo el archivo: si no es mayor
     * que `upload_max_filesize`, el limite util es el mas chico de los dos y
     * subir el primero no sirve de nada.
     */
    public function test_el_cuerpo_admite_mas_que_el_archivo_solo(): void
    {
        $ini = $this->ini();

        $this->assertGreaterThan(
            $this->mb($ini, 'upload_max_filesize'),
            $this->mb($ini, 'post_max_size'),
            'post_max_size debe superar a upload_max_filesize.'
        );
    }

    /**
     * De nada sirve el archivo si el contenedor no lo monta: es exactamente el
     * estado en el que estuvo el proyecto hasta que el marcaje fallo.
     */
    public function test_el_compose_monta_la_configuracion(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertStringContainsString(
            'docker/php/uploads.ini',
            $compose,
            'El compose no monta uploads.ini: el contenedor seguira con los 2M de fabrica.'
        );
    }

    /** nginx tiene que dejar pasar al menos lo mismo que acepta PHP. */
    public function test_nginx_no_es_mas_estricto_que_php(): void
    {
        foreach (['docker/nginx/default.conf', 'deploy/nginx-host-totalsecureapp.conf'] as $archivo) {
            $conf = file_get_contents(base_path($archivo));

            $this->assertMatchesRegularExpression(
                '/client_max_body_size\s+(\d+)[mM]/',
                $conf,
                "{$archivo} no declara client_max_body_size: nginx corta en 1 MB por defecto."
            );

            preg_match('/client_max_body_size\s+(\d+)[mM]/', $conf, $m);

            $this->assertGreaterThanOrEqual(
                $this->mb($this->ini(), 'post_max_size'),
                (int) $m[1],
                "{$archivo} corta el cuerpo antes que PHP."
            );
        }
    }
}

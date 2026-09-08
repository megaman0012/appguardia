<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deduce que «locales» son en realidad PUESTOS de un mismo sitio.
 *
 * **De donde viene el problema.** v1 no tenia puestos de trabajo, solo locales.
 * Para operar un hospital con cinco garitas, quien cargo los datos creo cinco
 * locales: «HOSPITAL SEMEDIC - LOBBY», «- PLUMA», «- EMERGENCIA»… El resultado
 * es que hoy hay 137 locales donde en realidad hay bastantes menos sitios, y la
 * tabla `puesto` esta vacia -- por eso el cuadrante de turnos no tiene nada que
 * mostrar.
 *
 * Este comando NO modifica nada. Solo mide, y deja ver en que se apoya cada
 * agrupacion, porque ninguna señal alcanza sola:
 *
 *  - **La direccion NO alcanza.** En el cliente JBGYE, «Av. Democracia» junta
 *    dos hospitales distintos (Alfredo Paulson y Roberto Gilbert) mas una
 *    garita de un tercer complejo. Y «Av. de las Americas» junta cuatro sitios
 *    sin relacion.
 *  - **La razon social SI dice el sitio, a veces.** Los locales de JBGYE llevan
 *    «Hospital Alfredo Paulson» o «Hospital Roberto Gilbert» ahi, y eso separa
 *    limpio lo que la direccion mezcla. Pero en 73 de 137 solo repite el nombre
 *    del cliente, y en varios dice «Total Security Company», que es la empresa
 *    de guardias: relleno.
 *  - **El nombre con guion es una pista fuerte** («HOSPITAL SEMEDIC - LOBBY»),
 *    pero no todos la usan: «Cementerio Puerta 10» o «Hospital Luis Vernaza
 *    Garita 3» no llevan guion.
 *  - **La distancia entre marcadores es la señal objetiva.** Los cinco puestos
 *    de Semedic estan a menos de 70 m entre si; «SEMEDIC - CASA DEL DR. ISLA
 *    MOCOLI», que comparte direccion, esta a 6 km. Ahi se cae la agrupacion por
 *    direccion y se sostiene la de GPS.
 *
 * ⚠️ **Las coordenadas hay que normalizarlas antes de medir.** 61 de 110
 * marcadores activos vienen de v1 con el signo perdido (latitud positiva y
 * longitud positiva). En Ecuador la longitud es SIEMPRE negativa y la latitud
 * nunca pasa de +1.5; sin corregir eso, las distancias no significan nada.
 */
class AnalizarPuestosCommand extends Command
{
    protected $signature = 'puestos:analizar
                            {--metros=300 : Radio para considerar que dos locales son el mismo sitio}
                            {--csv= : Ruta donde volcar el detalle}';

    protected $description = 'Deduce que locales son en realidad puestos de un mismo sitio (solo lee)';

    /** Relleno que NO nombra un sitio. */
    private const RELLENO = ['TOTAL SECURITY COMPANY', 'TOTAL PACIFIC GROUP'];

    /**
     * Diametro maximo creible para un sitio, en metros.
     *
     * El mas grande que hay en esta data es el Cementerio General de Guayaquil
     * con 995 m entre sus puertas. 1.200 m deja margen sin habilitar grupos
     * que cruzan la ciudad.
     */
    private const DIAMETRO_MAXIMO = 1200;

    public function handle(): int
    {
        $radio = (int) $this->option('metros');

        $locales = $this->cargarLocales();
        $this->info(sprintf('Locales activos: %d  (con coordenada: %d)',
            count($locales), count(array_filter($locales, fn ($l) => $l->lat !== null))));
        $this->newLine();

        $grupos = $this->agrupar($locales, $radio);

        $this->reportar($grupos, $radio);

        if ($this->option('csv')) {
            $this->volcarCsv($grupos, $this->option('csv'));
        }

        return self::SUCCESS;
    }

    /** @return array<int,object> */
    private function cargarLocales(): array
    {
        $filas = DB::select("
            SELECT i.ins_code, i.ins_descripcion, i.ins_direccion, i.ins_razon_social,
                   i.ins_cliente_id, o.org_descripcion AS cliente,
                   i.ins_cd_id, c.cd_nombre AS ciudad,
                   m.im_lat, m.im_lng
            FROM organizacion_institucion i
            LEFT JOIN organizacion o ON o.org_code = i.ins_cliente_id
            LEFT JOIN ciudad c ON c.cd_id = i.ins_cd_id
            LEFT JOIN (
                SELECT DISTINCT ON (im_ins_code) im_ins_code, im_lat, im_lng
                FROM institucion_marcadores
                WHERE im_estado AND im_lat ~ '^-?[0-9.]+$' AND im_lng ~ '^-?[0-9.]+$'
                ORDER BY im_ins_code, im_code
            ) m ON m.im_ins_code = i.ins_code
            WHERE i.ins_estado
            ORDER BY i.ins_cliente_id, i.ins_descripcion
        ");

        return array_map(function ($f) {
            list($lat, $lng) = $this->normalizar($f->im_lat, $f->im_lng);

            return (object) [
                'code'      => (int) $f->ins_code,
                'nombre'    => (string) $f->ins_descripcion,
                'direccion' => (string) $f->ins_direccion,
                'razon'     => (string) $f->ins_razon_social,
                'clienteId' => (int) $f->ins_cliente_id,
                'cliente'   => (string) ($f->cliente ?? 's/cliente'),
                'ciudadId'  => (int) ($f->ins_cd_id ?? 0),
                'ciudad'    => (string) ($f->ciudad ?? 's/ciudad'),
                'lat'       => $lat,
                'lng'       => $lng,
                'sitio'     => $this->sitioSegunRazon($f),
                'prefijo'   => $this->prefijo((string) $f->ins_descripcion),
            ];
        }, $filas);
    }

    /**
     * Corrige el signo perdido en el traspaso de v1.
     *
     * Ecuador continental va de -5.0 a +1.5 de latitud y de -81.1 a -75.2 de
     * longitud. Una longitud positiva es imposible. Una latitud mayor que +1.5
     * tampoco existe -- pero +0.33 SI: es Ibarra, al norte del ecuador, y ahi el
     * signo positivo es correcto. Por eso no se puede hacer `-abs()` a ciegas.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function normalizar($lat, $lng): array
    {
        if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
            return [null, null];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat === 0.0 && $lng === 0.0) {
            return [null, null];
        }

        if ($lng > 0) {
            $lng = -$lng;
        }

        if ($lat > 1.5) {
            $lat = -$lat;
        }

        return [$lat, $lng];
    }

    /** El sitio segun la razon social, o null si ahi solo hay relleno. */
    private function sitioSegunRazon($fila): ?string
    {
        $razon = strtoupper(trim((string) $fila->ins_razon_social));
        $cliente = strtoupper(trim((string) ($fila->cliente ?? '')));

        if ($razon === '' || in_array($razon, self::RELLENO, true) || $razon === $cliente) {
            return null;
        }

        return $razon;
    }

    /**
     * Prefijo del nombre: lo que va antes del guion, o las dos primeras
     * palabras largas. «HOSPITAL SEMEDIC - LOBBY» da «HOSPITAL SEMEDIC»;
     * «Cementerio Puerta 10» da «CEMENTERIO PUERTA».
     */
    private function prefijo(string $nombre): string
    {
        $n = strtoupper(trim($nombre));

        foreach ([' - ', ' -', '- '] as $sep) {
            $pos = strpos($n, $sep);
            if ($pos !== false && $pos > 2) {
                return trim(substr($n, 0, $pos));
            }
        }

        $palabras = preg_split('/\s+/', $n);
        $utiles = array_values(array_filter($palabras, fn ($p) => mb_strlen($p) > 2 && !is_numeric($p)));

        return trim(implode(' ', array_slice($utiles, 0, 2)));
    }

    /**
     * Agrupa por **nombre de sitio**, con el GPS como corroboracion.
     *
     * ⚠️ **Un radio unico no puede funcionar, y lo probe de dos formas.**
     * Primero con union transitiva a 300 m: encadeno 34 locales del centro de
     * Guayaquil en un grupo de 1.211 m -- Cementerio General, Hospital Luis
     * Vernaza, el INC y dos hospitales de la Junta, que son vecinos pero no el
     * mismo sitio. Despues con enlace completo a 150 m: dejo de encadenar, pero
     * partio sitios reales y siguio juntando cosas ajenas.
     *
     * La razon es que **el tamaño de un sitio depende del sitio**. Los cinco
     * puestos del Hospital Semedic caben en 70 m; las once puertas del
     * Cementerio General ocupan 600 m porque el cementerio mide eso; un
     * aeropuerto, kilometros. Y al reves: «JBGYE HOGAR CORAZON DE JESUS» esta a
     * 60 m del cementerio y es otra institucion. La cercania no implica mismo
     * sitio ni la lejania implica sitios distintos.
     *
     * Asi que el nombre manda. El catalogo de sitios sale de la propia data:
     *
     *  1. Las razones sociales que nombran un sitio («Hospital Alfredo
     *     Paulson», «Hospital Roberto Gilbert»), descartando el relleno.
     *  2. Los arranques de nombre que se repiten dentro de un cliente
     *     («HOSPITAL SEMEDIC», «Cementerio», «Hospital Luis Vernaza»,
     *     «SWISSPORT»).
     *
     * Y el GPS queda como control: el reporte muestra el diametro de cada
     * grupo, y un diametro absurdo delata una agrupacion mal hecha o una
     * coordenada mal cargada.
     *
     * @param  array<int,object>  $locales
     * @return array<int,array<int,object>>
     */
    private function agrupar(array $locales, int $radio): array
    {
        // ⚠️ **La ciudad es una restriccion dura.** Dos locales del mismo
        // cliente en ciudades distintas no pueden ser el mismo sitio, y sin
        // esto «LA FABRIL» agrupaba Ambato con Ibarra: 9 locales y **315 km**
        // de diametro. Los 130 locales activos tienen ciudad normalizada, asi
        // que no hay que adivinarla.
        $porClienteYCiudad = [];
        foreach ($locales as $l) {
            $porClienteYCiudad[$l->clienteId . '|' . $l->ciudadId][] = $l;
        }

        $grupos = [];

        foreach ($porClienteYCiudad as $delSitio) {
            $catalogo = $this->catalogo($delSitio);

            $bolsas = [];
            $huerfanos = [];

            foreach ($delSitio as $l) {
                $sitio = $this->sitioDe($l, $catalogo);

                if ($sitio === null) {
                    $huerfanos[] = $l;
                    continue;
                }

                $bolsas[$sitio][] = $l;
            }

            foreach ($bolsas as $bolsa) {
                if (count($bolsa) < 2) {
                    continue;
                }

                // ⚠️ **El nombre propone, el GPS veta.** Un sitio real no mide
                // kilometros: el Cementerio General, que es el mas grande que
                // hay aca, ocupa 995 m. Si un grupo por nombre pasa el tope, el
                // nombre agrupo cosas distintas y se rearma por distancia.
                $diam = $this->diametro($bolsa);

                if ($diam <= self::DIAMETRO_MAXIMO) {
                    $grupos[] = $bolsa;
                    continue;
                }

                $this->line(sprintf(
                    '   <fg=yellow>·</> «%s» descartado por GPS: %s m entre los más lejanos, se rearma por distancia',
                    $this->nombreDelSitio($bolsa), number_format($diam, 0, ',', '.')
                ));

                foreach ($this->porGps(array_filter($bolsa, fn ($l) => $l->lat !== null), 250) as $g) {
                    if (count($g) > 1) {
                        $grupos[] = $g;
                    }
                }
            }

            // A los que el nombre no agrupa les queda el GPS, ya acotado a una
            // sola ciudad: con enlace completo y 250 m de diametro no puede
            // encadenar medio Guayaquil.
            foreach ($this->porGps(array_filter($huerfanos, fn ($l) => $l->lat !== null), 250) as $g) {
                if (count($g) > 1) {
                    $grupos[] = $g;
                }
            }
        }

        usort($grupos, fn ($a, $b) => count($b) <=> count($a));

        return $grupos;
    }

    /**
     * Nombres de sitio que aparecen en los locales de un cliente.
     *
     * Se ordenan de mas largo a mas corto para que gane el mas especifico:
     * «HOSPITAL LUIS VERNAZA» antes que «HOSPITAL», si no todos los hospitales
     * del cliente caerian en la misma bolsa.
     *
     * @param  array<int,object>  $delCliente
     * @return array<int,string>
     */
    private function catalogo(array $delCliente): array
    {
        $cliente = $this->plano($delCliente[0]->cliente);

        // ── Prioridad A: la razon social, cuando nombra un sitio ──
        //
        // Es un campo que alguien lleno a proposito, no una inferencia. En
        // JBGYE dice «Hospital Alfredo Paulson» u «Hospital Roberto Gilbert», y
        // eso es exactamente el sitio.
        $razones = [];
        foreach ($delCliente as $l) {
            if ($l->sitio !== null && !$this->esGenerico($l->sitio)) {
                $razones[$l->sitio] = true;
            }
        }

        // ── Prioridad B: arranques de nombre que se repiten ──
        $prefijos = [];
        for ($palabras = 4; $palabras >= 1; $palabras--) {
            $cuenta = [];
            foreach ($delCliente as $l) {
                $clave = $this->arranque($l->nombre, $palabras);

                // Una sola palabra vale solo si es larga y especifica:
                // «CEMENTERIO» nombra un sitio, «PLAZA» no. Sin esta puerta, el
                // Cementerio General quedaba partido en «CEMENTERIO PUERTA» y
                // «CEMENTERIO SALA».
                $minimo = $palabras === 1 ? 8 : 6;

                if ($clave !== '' && mb_strlen($clave) >= $minimo) {
                    $cuenta[$clave] = ($cuenta[$clave] ?? 0) + 1;
                }
            }
            foreach ($cuenta as $clave => $n) {
                if ($n > 1) {
                    $prefijos[$clave] = $n;
                }
            }
        }

        $filtrar = fn ($s) => $this->plano($s) !== $cliente
            && !str_contains($cliente, $this->plano($s))
            && !$this->esGenerico($s);

        $listaRazones = array_values(array_filter(array_keys($razones), $filtrar));

        $listaPrefijos = array_filter($prefijos, fn ($n, $s) => $filtrar($s), ARRAY_FILTER_USE_BOTH);

        // Entre prefijos gana el que agrupa MAS locales; a igualdad, el mas
        // largo. Ni «el mas corto» ni «el mas largo» sirven solos: el mas
        // especifico partia el Hospital Luis Vernaza en tres, y el mas corto
        // dejaba que «INGRESO PEATONAL» juntara dos hospitales distintos.
        uksort($listaPrefijos, function ($a, $b) use ($listaPrefijos) {
            return [$listaPrefijos[$b], mb_strlen($b)] <=> [$listaPrefijos[$a], mb_strlen($a)];
        });

        return [$listaRazones, array_keys($listaPrefijos)];
    }

    /**
     * Abreviaturas y palabras que no nombran un sitio.
     *
     * Se comprueba que TODAS las palabras del candidato sean genericas: asi
     * «CENTRO NEGOCIOS» sobrevive (Negocios no esta en la lista) pero «JBGYE
     * C.C.» no.
     */
    private function esGenerico(string $candidato): bool
    {
        static $genericos = [
            'JBG', 'JBGYE', 'UE', 'U.E', 'U.E.', 'CC', 'C.C', 'C.C.', 'PLAZA',
            'GARITA', 'OFICINA', 'INGRESO', 'ENTRADA', 'PUNTO', 'DE', 'DEL',
            'LA', 'EL', 'LOS', 'LAS', 'Y', 'ATO', 'PDS', 'LN',
        ];

        $palabras = preg_split('/\s+/', $this->plano($candidato));

        foreach ($palabras as $p) {
            if ($p !== '' && !in_array($p, $genericos, true)) {
                return false;
            }
        }

        return true;
    }

    /** Las primeras $n palabras utiles del nombre, en mayusculas y sin tildes. */
    private function arranque(string $nombre, int $n): string
    {
        $limpio = $this->plano($nombre);
        $palabras = preg_split('/\s+/', trim($limpio));
        $utiles = array_values(array_filter($palabras, fn ($p) => $p !== '' && $p !== '-'));

        return trim(implode(' ', array_slice($utiles, 0, $n)));
    }

    /**
     * A que sitio pertenece un local, en tres pasos:
     *
     *  1. Su propia razon social, si nombra un sitio. Es un dato explicito.
     *  2. Un nombre de sitio de OTRO local que aparezca dentro de su nombre.
     *     Asi entra «Parqueadero del Hospital Alfredo Paulson», cuya razon
     *     social dice solo «JBGYE» pero cuyo nombre trae el sitio.
     *  3. El arranque de nombre repetido que agrupe mas locales.
     *
     * Si nada aplica, devuelve null y el local se resuelve por GPS.
     *
     * @param  array{0: array<int,string>, 1: array<int,string>}  $catalogo
     */
    private function sitioDe(object $l, array $catalogo): ?string
    {
        list($razones, $prefijos) = $catalogo;

        if ($l->sitio !== null && in_array($l->sitio, $razones, true)) {
            return $l->sitio;
        }

        $enNombre = $this->plano($l->nombre);

        foreach ($razones as $sitio) {
            if (str_contains($enNombre, $this->plano($sitio))) {
                return $sitio;
            }
        }

        foreach ($prefijos as $sitio) {
            if (str_contains($enNombre, $this->plano($sitio))) {
                return $sitio;
            }
        }

        return null;
    }

    /** Mayusculas, sin tildes y con espacios normalizados, para comparar. */
    private function plano(string $t): string
    {
        $t = mb_strtoupper(trim($t));
        $t = strtr($t, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ñ' => 'N', 'Ü' => 'U',
        ]);

        return preg_replace('/\s+/', ' ', $t);
    }

    /**
     * Enlace completo por distancia, para los locales que el nombre no agrupa.
     * Empieza con cada uno en su grupo y une el par mas cercano mientras el
     * diametro resultante no pase del radio.
     *
     * El enlace completo (y no la union transitiva) es lo que evita encadenar:
     * con «A cerca de B» y «B cerca de C» se unian A y C aunque estuvieran
     * lejos, y en el centro de Guayaquil eso llego a 34 locales en un grupo.
     *
     * @param  array<int,object>  $locales
     * @return array<int,array<int,object>>
     */
    private function porGps(array $locales, int $radio): array
    {
        $grupos = array_map(fn ($l) => [$l], array_values($locales));

        while (true) {
            $mejor = null;
            $mejorDiam = INF;

            foreach ($grupos as $i => $a) {
                foreach ($grupos as $j => $b) {
                    if ($j <= $i) {
                        continue;
                    }

                    $diam = $this->diametro(array_merge($a, $b));
                    if ($diam <= $radio && $diam < $mejorDiam) {
                        $mejorDiam = $diam;
                        $mejor = [$i, $j];
                    }
                }
            }

            if ($mejor === null) {
                break;
            }

            list($i, $j) = $mejor;
            $grupos[$i] = array_merge($grupos[$i], $grupos[$j]);
            unset($grupos[$j]);
            $grupos = array_values($grupos);
        }

        return $grupos;
    }

    /** Distancia entre los dos miembros mas lejanos del grupo. */
    private function diametro(array $g): float
    {
        $conGps = array_values(array_filter($g, fn ($l) => $l->lat !== null));
        $max = 0.0;

        foreach ($conGps as $i => $a) {
            foreach ($conGps as $j => $b) {
                if ($j > $i) {
                    $max = max($max, $this->distancia($a->lat, $a->lng, $b->lat, $b->lng));
                }
            }
        }

        return $max;
    }

    private function distancia(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @param array<int,array<int,object>> $grupos */
    private function reportar(array $grupos, int $radio): void
    {
        $enGrupos = array_sum(array_map('count', $grupos));

        $this->line("<options=bold>Sitios candidatos (radio {$radio} m)</>");
        $this->newLine();

        foreach ($grupos as $k => $g) {
            $sitio = $this->nombreDelSitio($g);
            $senales = $this->senales($g, $radio);

            $this->line(sprintf('<fg=cyan>%2d.</> <options=bold>%s</> — cliente %s · %d locales · %s',
                $k + 1, $sitio, $g[0]->cliente, count($g), $senales));

            foreach ($g as $l) {
                $dep = $this->dependencias($l->code);
                $this->line(sprintf('      %4d  %-46s %s',
                    $l->code, mb_strimwidth($l->nombre, 0, 46, '…'), $dep));
            }
            $this->newLine();
        }

        $this->line(sprintf(
            '<options=bold>Resumen:</> %d sitios agrupan %d locales. Quedarian %d locales sueltos.',
            count($grupos), $enGrupos, 130 - $enGrupos
        ));
    }

    /** @param array<int,object> $g */
    private function nombreDelSitio(array $g): string
    {
        foreach ($g as $l) {
            if ($l->sitio !== null) {
                return $l->sitio;
            }
        }

        $prefijos = array_count_values(array_map(fn ($l) => $l->prefijo, $g));
        arsort($prefijos);

        return (string) array_key_first($prefijos);
    }

    /** En que se apoya la agrupacion. @param array<int,object> $g */
    private function senales(array $g, int $radio): string
    {
        $partes = [];

        $sitios = array_unique(array_filter(array_map(fn ($l) => $l->sitio, $g)));
        if (count($sitios) === 1) {
            $partes[] = 'razón social';
        }

        $prefijos = array_unique(array_map(fn ($l) => $l->prefijo, $g));
        if (count($prefijos) === 1) {
            $partes[] = 'nombre';
        }

        $conCoord = array_values(array_filter($g, fn ($l) => $l->lat !== null));
        if (count($conCoord) > 1) {
            $max = 0.0;
            foreach ($conCoord as $i => $a) {
                foreach ($conCoord as $j => $b) {
                    if ($j > $i) {
                        $max = max($max, $this->distancia($a->lat, $a->lng, $b->lat, $b->lng));
                    }
                }
            }
            $partes[] = sprintf('GPS (%d m entre los más lejanos)', round($max));
        } elseif (count($conCoord) === 1) {
            $partes[] = 'GPS de uno solo';
        } else {
            $partes[] = '<fg=yellow>sin GPS</>';
        }

        $dirs = array_unique(array_map(fn ($l) => strtoupper(trim($l->direccion)), $g));
        if (count($dirs) === 1) {
            $partes[] = 'dirección';
        }

        return implode(' + ', $partes);
    }

    /** Cuanto cuelga de este local: es el costo de reestructurar. */
    private function dependencias(int $insCode): string
    {
        $c = DB::selectOne("
            SELECT
              (SELECT count(*) FROM user_has_institucion WHERE ui_ins_code = ? AND ui_state = 1) AS guardias,
              (SELECT count(*) FROM institucion_marcadores WHERE im_ins_code = ?) AS marcadores,
              (SELECT count(*) FROM ronda_detalle       WHERE rd_ins_code  = ?) AS rondas,
              (SELECT count(*) FROM user_has_biometria  WHERE bio_ins_code = ?) AS marcajes,
              (SELECT count(*) FROM acceso              WHERE ac_ins_code  = ?) AS accesos
        ", [$insCode, $insCode, $insCode, $insCode, $insCode]);

        return sprintf('%2d guardias · %d QR · %5d rondas · %5d marcajes · %5d accesos',
            $c->guardias, $c->marcadores, $c->rondas, $c->marcajes, $c->accesos);
    }

    /** @param array<int,array<int,object>> $grupos */
    private function volcarCsv(array $grupos, string $ruta): void
    {
        $fh = fopen($ruta, 'w');
        fputcsv($fh, ['sitio_propuesto', 'cliente', 'ins_code', 'local', 'razon_social', 'direccion', 'lat', 'lng']);

        foreach ($grupos as $g) {
            $sitio = $this->nombreDelSitio($g);
            foreach ($g as $l) {
                fputcsv($fh, [$sitio, $l->cliente, $l->code, $l->nombre, $l->razon, $l->direccion, $l->lat, $l->lng]);
            }
        }

        fclose($fh);
        $this->info("Detalle en {$ruta}");
    }
}

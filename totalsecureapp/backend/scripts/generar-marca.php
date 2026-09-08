<?php
/**
 * Genera los archivos de marca de la app a partir del logo original.
 *
 * Uso (desde `totalsecureapp/backend`):
 *
 *   docker compose exec -u 1000 backend php -d memory_limit=1G \
 *     /var/www/scripts/generar-marca.php
 *
 * Deja todo en `scripts/marca/salida/` y NO instala nada: los archivos se
 * copian a mano a `assets/`, `android/app/src/main/res/` y
 * `public/images/logo.png`. Se hace en dos pasos a proposito, para poder
 * mirar el resultado antes de reemplazar 28 archivos versionados.
 *
 * Corre dentro del contenedor de PHP porque el servidor no tiene ImageMagick ni
 * Pillow ni sharp; el contenedor trae GD con PNG (sin WebP).
 *
 * Hay que hacerlo a mano porque `android/` esta versionado: el prebuild de Expo
 * NO vuelve a correr sus plugins sobre un proyecto que ya tiene la carpeta
 * nativa, asi que cambiar `assets/` y `app.json` no regenera ni los mipmaps ni
 * los splash por densidad. Esa fue exactamente la razon por la que el primer
 * APK salio con el splash viejo.
 *
 * Zonas seguras que respeta:
 *  - Icono adaptativo (Android 8+): el lanzador recorta el canvas de 108dp a una
 *    mascara (circulo, squircle, etc.) y solo garantiza los 66dp centrales. El
 *    arte va al 60% para no quedar mordido en ningun lanzador.
 *  - Splash de Android 12+: `windowSplashScreenAnimatedIcon` tambien se recorta
 *    en circulo. Mismo 60%.
 */

const ORIGEN = __DIR__ . '/marca/logo-origen.png';
const SALIDA = __DIR__ . '/marca/salida';

/**
 * Recorta el arte y le quita el fondo.
 *
 * El fondo NO se detecta con «todo lo blanco es fondo»: el hueco entre el
 * escudo y la S tambien es blanco. Se rellena desde los cuatro bordes hacia
 * adentro, asi que solo se vuelve transparente el blanco que se puede alcanzar
 * desde afuera. (En este logo ese hueco SI esta conectado con el exterior por
 * la banda diagonal, asi que termina transparente igual -- pero el metodo no
 * depende de esa casualidad.)
 */
function recortar(string $ruta): array
{
    $src = imagecreatefrompng($ruta);
    $w = imagesx($src);
    $h = imagesy($src);

    $esFondo = function (int $x, int $y) use ($src): bool {
        $c = imagecolorat($src, $x, $y);
        if ((($c >> 24) & 0x7F) > 100) {
            return true;
        }

        // 245 tolera el antialias del borde sin comerse el arte.
        return ((($c >> 16) & 0xFF) > 245) && ((($c >> 8) & 0xFF) > 245) && (($c & 0xFF) > 245);
    };

    $fuera = array_fill(0, $w * $h, false);

    // La cola guarda el indice plano del pixel, no un par [x, y]: con 2,4
    // millones de pixeles un arreglo de arreglos agota la memoria de PHP.
    $cola = [];

    for ($x = 0; $x < $w; $x++) {
        foreach ([0, $h - 1] as $y) {
            $k = $y * $w + $x;
            if (!$fuera[$k] && $esFondo($x, $y)) { $fuera[$k] = true; $cola[] = $k; }
        }
    }
    for ($y = 0; $y < $h; $y++) {
        foreach ([0, $w - 1] as $x) {
            $k = $y * $w + $x;
            if (!$fuera[$k] && $esFondo($x, $y)) { $fuera[$k] = true; $cola[] = $k; }
        }
    }

    $i = 0;
    $total = count($cola);

    while ($i < $total) {
        $k = $cola[$i++];
        $x = $k % $w;
        $y = intdiv($k, $w);

        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as list($dx, $dy)) {
            $nx = $x + $dx;
            $ny = $y + $dy;
            if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;

            $nk = $ny * $w + $nx;
            if ($fuera[$nk] || !$esFondo($nx, $ny)) continue;

            $fuera[$nk] = true;
            $cola[] = $nk;
            $total++;
        }

        // Se libera lo ya recorrido: si no, quedan en memoria el millon y medio
        // de pixeles de fondo a la vez.
        if ($i === 200000) {
            $cola = array_slice($cola, $i);
            $total -= $i;
            $i = 0;
        }
    }

    $x1 = $w; $y1 = $h; $x2 = -1; $y2 = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ($fuera[$y * $w + $x]) continue;
            if ($x < $x1) $x1 = $x;
            if ($y < $y1) $y1 = $y;
            if ($x > $x2) $x2 = $x;
            if ($y > $y2) $y2 = $y;
        }
    }

    if ($x2 < 0) {
        throw new RuntimeException('El origen parece estar vacio');
    }

    $aw = $x2 - $x1 + 1;
    $ah = $y2 - $y1 + 1;

    // Se cuadra la caja: asi todas las salidas mantienen la proporcion sin
    // deformar el escudo.
    $lado = max($aw, $ah);

    $arte = imagecreatetruecolor($lado, $lado);
    imagealphablending($arte, false);
    imagesavealpha($arte, true);
    imagefill($arte, 0, 0, imagecolorallocatealpha($arte, 0, 0, 0, 127));

    $ox = intdiv($lado - $aw, 2);
    $oy = intdiv($lado - $ah, 2);

    for ($y = 0; $y < $ah; $y++) {
        for ($x = 0; $x < $aw; $x++) {
            $sx = $x1 + $x;
            $sy = $y1 + $y;
            if ($fuera[$sy * $w + $sx]) continue;

            $c = imagecolorat($src, $sx, $sy);
            imagesetpixel($arte, $ox + $x, $oy + $y, imagecolorallocatealpha(
                $arte, ($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, 0
            ));
        }
    }

    imagedestroy($src);

    return [$arte, $lado, $aw, $ah];
}

/** Convierte el blanco del arte recortado en transparencia. */
function silueta($arte, int $lado, bool $soloBlanco = true)
{
    $out = imagecreatetruecolor($lado, $lado);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    for ($y = 0; $y < $lado; $y++) {
        for ($x = 0; $x < $lado; $x++) {
            $c = imagecolorat($arte, $x, $y);
            $a = ($c >> 24) & 0x7F;
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8) & 0xFF;
            $b = $c & 0xFF;

            if ($a > 100) {
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 0, 0, 0, 127));
                continue;
            }

            // Para el icono monocromo de notificaciones Android descarta el
            // color y aplica su propio tinte: lo que importa es la forma, y
            // tiene que venir en blanco opaco.
            $color = $soloBlanco
                ? imagecolorallocatealpha($out, 255, 255, 255, 0)
                : imagecolorallocatealpha($out, $r, $g, $b, 0);

            imagesetpixel($out, $x, $y, $color);
        }
    }

    return $out;
}

/**
 * @param string      $forma  'cuadrado' | 'circulo' | 'ninguna'
 * @param float       $ocupa  Fraccion del lienzo que ocupa el arte.
 */
function componer($arte, int $ladoArte, string $destino, int $lado, float $ocupa, string $forma): void
{
    $lienzo = imagecreatetruecolor($lado, $lado);
    imagealphablending($lienzo, false);
    imagesavealpha($lienzo, true);
    imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
    imagealphablending($lienzo, true);

    $blanco = imagecolorallocatealpha($lienzo, 255, 255, 255, 0);

    if ($forma === 'cuadrado') {
        imagefilledrectangle($lienzo, 0, 0, $lado - 1, $lado - 1, $blanco);
    } elseif ($forma === 'circulo') {
        imageantialias($lienzo, true);
        imagefilledellipse($lienzo, intdiv($lado, 2), intdiv($lado, 2), $lado - 1, $lado - 1, $blanco);
    }

    $tam = (int) round($lado * $ocupa);
    $off = intdiv($lado - $tam, 2);

    imagecopyresampled($lienzo, $arte, $off, $off, 0, 0, $tam, $tam, $ladoArte, $ladoArte);

    imagepng($lienzo, $destino, 9);
    imagedestroy($lienzo);
}

list($arte, $lado, $aw, $ah) = recortar(ORIGEN);
$mono = silueta($arte, $lado);

printf("origen recortado: arte de %dx%d en un lienzo de %d\n", $aw, $ah, $lado);

if (!is_dir(SALIDA)) {
    mkdir(SALIDA, 0775, true);
}

$tareas = [
    // ── assets/ de Expo ──
    ['assets/icon.png',                     1024, 0.82, 'cuadrado', false],
    ['assets/android-icon-foreground.png',   512, 0.60, 'ninguna',  false],
    ['assets/android-icon-background.png',   512, 0.00, 'cuadrado', false],
    ['assets/android-icon-monochrome.png',   432, 0.60, 'ninguna',  true],
    ['assets/favicon.png',                    48, 0.88, 'cuadrado', false],
    ['assets/splash-logo.png',              1024, 0.60, 'ninguna',  false],
    ['assets/splash-icon.png',              1024, 0.60, 'ninguna',  false],

    // ── Splash por densidad (Android 12+ lo recorta en circulo) ──
    ['res/drawable-mdpi/splashscreen_logo.png',    288, 0.60, 'ninguna', false],
    ['res/drawable-hdpi/splashscreen_logo.png',    432, 0.60, 'ninguna', false],
    ['res/drawable-xhdpi/splashscreen_logo.png',   576, 0.60, 'ninguna', false],
    ['res/drawable-xxhdpi/splashscreen_logo.png',  864, 0.60, 'ninguna', false],
    ['res/drawable-xxxhdpi/splashscreen_logo.png',1152, 0.60, 'ninguna', false],

    // ── Icono adaptativo: capa de frente, 60% por la mascara del lanzador ──
    ['res/mipmap-mdpi/ic_launcher_foreground.webp',    108, 0.60, 'ninguna', false],
    ['res/mipmap-hdpi/ic_launcher_foreground.webp',    162, 0.60, 'ninguna', false],
    ['res/mipmap-xhdpi/ic_launcher_foreground.webp',   216, 0.60, 'ninguna', false],
    ['res/mipmap-xxhdpi/ic_launcher_foreground.webp',  324, 0.60, 'ninguna', false],
    ['res/mipmap-xxxhdpi/ic_launcher_foreground.webp', 432, 0.60, 'ninguna', false],

    // ── Iconos heredados (Android 7 y anteriores): no hay mascara ──
    ['res/mipmap-mdpi/ic_launcher.webp',     48, 0.82, 'cuadrado', false],
    ['res/mipmap-hdpi/ic_launcher.webp',     72, 0.82, 'cuadrado', false],
    ['res/mipmap-xhdpi/ic_launcher.webp',    96, 0.82, 'cuadrado', false],
    ['res/mipmap-xxhdpi/ic_launcher.webp',  144, 0.82, 'cuadrado', false],
    ['res/mipmap-xxxhdpi/ic_launcher.webp', 192, 0.82, 'cuadrado', false],

    ['res/mipmap-mdpi/ic_launcher_round.webp',     48, 0.72, 'circulo', false],
    ['res/mipmap-hdpi/ic_launcher_round.webp',     72, 0.72, 'circulo', false],
    ['res/mipmap-xhdpi/ic_launcher_round.webp',    96, 0.72, 'circulo', false],
    ['res/mipmap-xxhdpi/ic_launcher_round.webp',  144, 0.72, 'circulo', false],
    ['res/mipmap-xxxhdpi/ic_launcher_round.webp', 192, 0.72, 'circulo', false],

    // ── Panel web ──
    ['panel/logo.png', 512, 0.94, 'ninguna', false],
];

foreach ($tareas as list($rel, $tam, $ocupa, $forma, $esMono)) {
    $destino = SALIDA . '/' . $rel;
    $dir = dirname($destino);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    componer($esMono ? $mono : $arte, $lado, $destino, $tam, $ocupa, $forma);
    printf("  %-52s %4dpx\n", $rel, $tam);
}

echo "listo\n";

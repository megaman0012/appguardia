/**
 * Compilación del tema del panel.
 *
 * ⚠️ Este proyecto viene de Laravel 8 y su `package.json` traía `laravel-mix`,
 * que no se usa para nada: no había ningún CSS que compilar. Vite se agrega
 * SOLO para el tema de Filament; el resto del panel sigue sirviéndose de la
 * hoja precompilada que publica `php artisan filament:assets`.
 *
 * ⚠️ **El resultado (`public/build/`) va versionado.** Acá el árbol de trabajo
 * *es* producción y desplegar es `git pull`: si el panel dependiera de que
 * `npm run build` corra bien en el servidor, un fallo de compilación lo dejaría
 * sin manifiesto, y eso no es «sin estilos», es un error 500. Con el build
 * versionado, `npm` solo hace falta para cambiar el tema, no para servirlo.
 *
 * Al tocar `resources/css/filament/admin/theme.css`:
 *
 *     npm run build && git add public/build resources/css
 */
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});

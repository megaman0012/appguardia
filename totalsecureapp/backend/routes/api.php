<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
 * ⚠️ Aca vivia la ruta de ejemplo de Laravel, `GET /api/user`, y **devolvia
 * 500**: el archivo importa `Illuminate\Support\Facades\Route` pero nunca
 * `Illuminate\Http\Request`, asi que el `Request $request` del cierre resolvia
 * al **alias de la fachada** y `Request::user()` no existe.
 *
 * Se borro en vez de arreglarse: no la usa nadie -- el APK no la tiene en
 * `constants.ts` --, el perfil del usuario sale por `/api/seleccionar_perfil` y
 * `/api/procesar_perfil`, y una ruta de andamiaje que responde 500 es peor que
 * no tenerla.
 *
 * La encontro `ApiRespondeTest`, la prueba de humo de la Etapa 0 de la
 * migracion, en su primera corrida.
 */

/*
 * `GET /api/test-cors` tambien se fue: era una prueba manual de CORS de cuando
 * se configuro el portal. La configuracion vive en `config/cors.php` y
 * `App\Services\CorsService`, y el portal se verifica con sus propios tests.
 */

/*
 * Webhook de Evolution: los mensajes que los guardias responden por WhatsApp.
 *
 * Va fuera de auth:sanctum a propósito —Evolution no tiene token de usuario— y
 * se protege con el token del path (WHATSAPP_WEBHOOK_TOKEN). Sin ese valor en el
 * .env, la ruta responde 404.
 */
Route::post('/whatsapp/webhook/{token}', [\App\Http\Controllers\WhatsappWebhookController::class, 'recibir'])
    ->name('whatsapp.webhook');

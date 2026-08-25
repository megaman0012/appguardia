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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/test-cors', function () {
    return response()->json(['message' => 'CORS is working!', 'timestamp' => now()]);
});

/*
 * Webhook de Evolution: los mensajes que los guardias responden por WhatsApp.
 *
 * Va fuera de auth:sanctum a propósito —Evolution no tiene token de usuario— y
 * se protege con el token del path (WHATSAPP_WEBHOOK_TOKEN). Sin ese valor en el
 * .env, la ruta responde 404.
 */
Route::post('/whatsapp/webhook/{token}', [\App\Http\Controllers\WhatsappWebhookController::class, 'recibir'])
    ->name('whatsapp.webhook');

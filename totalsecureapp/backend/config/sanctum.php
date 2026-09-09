<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do
    | not expire. This won't tweak the lifetime of first-party sessions.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Vida del token de la app movil (en SEGUNDOS)
    |--------------------------------------------------------------------------
    |
    | ⚠️ El `(int)` NO es cosmetico. Esto vivia como `env('TOKEN_EXPIRE_IN')`
    | dentro del controlador, y `env()` devuelve **texto**: en produccion valia
    | la cadena '3600'. Carbon 2 la aceptaba; Carbon 3 exige `int|float` y lanza
    | TypeError, asi que despues de subir a Laravel 13 **el login de la app movil
    | devolvia 500 y ningun guardia podia entrar**.
    |
    | Los 391 tests pasaban igual, y por una razon que vale recordar: la variable
    | NO estaba declarada en el entorno de pruebas, asi que ahi `env()` devolvia
    | `null`, y PHP convierte `null` a 0 para un parametro `int|float` sin error.
    | El test pasaba *porque* al entorno de pruebas le faltaba la variable. Ya
    | esta declarada en phpunit.xml para que no se repita.
    |
    | El otro motivo para traerlo aca: `env()` fuera de `config/` devuelve `null`
    | en cuanto alguien corra `config:cache`. Hoy nadie lo corre en este
    | proyecto, pero es una bomba con temporizador.
    |
    */
    'expiracion_token_movil' => (int) env('TOKEN_EXPIRE_IN', 3600),

];

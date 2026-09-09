<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes editables desde el panel: correo saliente y WhatsApp.
 *
 * **Por que no se reusa `parametros`.** Existe y tiene la misma forma
 * (descripcion/valor), pero guarda **la clave con la que se cifran los QR** --
 * la fila `access`. Mezclar ahi un formulario que el panel escribe y muestra es
 * pedir que alguien edite o borre esa fila sin saber que apaga los 118 codigos
 * pegados en las garitas. Tabla aparte.
 *
 * **`cf_cifrado`** marca los valores que van cifrados en la base: hoy solo la
 * contraseña SMTP y la clave de la API de WhatsApp. No alcanza con saber cual
 * es la columna, porque el descifrado tiene que decidirse fila por fila al
 * leer.
 *
 * ⚠️ Se cifra con `APP_KEY`, **la misma que cifra los QR**. Rotarla dejaria
 * estos valores ilegibles ademas de invalidar los codigos impresos: si algun
 * dia hay que rotarla, estas filas se vuelven a cargar a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion', function (Blueprint $table) {
            $table->id('cf_id');

            // La clave con la que el codigo pide el valor, por ejemplo
            // `mail.host`. Unica: es el identificador real de la fila.
            $table->string('cf_clave', 80)->unique();

            // `text` y no `varchar`: un valor cifrado ocupa bastante mas que el
            // original, y una contraseña larga se pasaria de 255.
            $table->text('cf_valor')->nullable();

            $table->boolean('cf_cifrado')->default(false);

            $table->bigInteger('cf_updated_user')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
    }
};

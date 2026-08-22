<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soporte offline-first (Fase 7).
     *
     * client_uuid: idempotency key generada en el dispositivo. Es unique para que
     * un reintento del mismo registro no cree un duplicado. Nullable porque en
     * Postgres varios NULL no colisionan, asi que las filas historicas y los
     * clientes que no lo envian siguen funcionando.
     *
     * sincronizado_en: momento en que el servidor recibio el registro. Comparado
     * contra la fecha del evento (que la envia el dispositivo) da la latencia de
     * sincronizacion real en campo.
     */
    private array $tablas = [
        'user_has_biometria' => 'bio',
        'ronda_detalle'      => 'rd',
        'acceso'             => 'ac',
        'novedad'            => 'nv',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla => $prefijo) {
            Schema::table($tabla, function (Blueprint $table) use ($prefijo) {
                $table->string($prefijo . '_client_uuid', 36)->nullable()->unique();
                $table->timestamp($prefijo . '_sincronizado_en')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla => $prefijo) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $prefijo) {
                $table->dropUnique($tabla . '_' . $prefijo . '_client_uuid_unique');
                $table->dropColumn([$prefijo . '_client_uuid', $prefijo . '_sincronizado_en']);
            });
        }
    }
};

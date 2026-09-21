<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se elimina `inv_stock_cliente`.
 *
 * Se creo el 2026-09-19 para responder «¿se le repartio a un cliente mas equipo
 * del que tiene asignado?». Al revisarlo con la operacion quedo claro que
 * **modela algo que este departamento no hace**:
 *
 *   «El departamento de operaciones no maneja stock ni bodega, solo tiene o no
 *    tiene, porque el equipo nuevo o dañado debe entregarlo a otros
 *    departamentos.»
 *
 * Con eso se caen las tres lecturas posibles de la tabla: no es stock de bodega
 * (no hay bodega), no es la cifra del contrato (tampoco es de ellos), y como
 * numero derivable ya se sabia que era «numero de locales x 1».
 *
 * Nunca llego a tener una sola fila, asi que no se pierde nada. El «asignado»
 * del resumen pasa a **derivarse del kit de cada puesto**, que no hay que
 * mantener a mano y siempre esta al dia.
 *
 * La bajada la vuelve a crear igual, por si hiciera falta reconstruir el
 * historico de migraciones en un clon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('inv_stock_cliente');
    }

    public function down(): void
    {
        Schema::create('inv_stock_cliente', function (Blueprint $table) {
            $table->id('isc_id');
            $table->bigInteger('isc_org_code');
            $table->unsignedBigInteger('isc_producto_id');
            $table->decimal('isc_cantidad', 12, 2)->default(0);
            $table->text('isc_observacion')->nullable();
            $table->boolean('isc_activo')->default(true);
            $table->timestamp('isc_created_at')->nullable();
            $table->bigInteger('isc_created_user')->nullable();
            $table->timestamp('isc_updated_at')->nullable();
            $table->bigInteger('isc_updated_user')->nullable();

            $table->unique(['isc_org_code', 'isc_producto_id'], 'uk_stock_cliente_producto');
            $table->index('isc_org_code', 'idx_stock_cliente_org');
            $table->index('isc_producto_id', 'idx_stock_cliente_producto');

            $table->foreign('isc_org_code')
                ->references('org_code')->on('organizacion')->cascadeOnDelete();
            $table->foreign('isc_producto_id')
                ->references('ipc_id')->on('inv_producto_catalogo')->cascadeOnDelete();
        });
    }
};

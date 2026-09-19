<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuantos equipos tiene asignados cada cliente, por producto.
 *
 * **Entrega 2 de la reestructuracion del inventario.** La 1 dejo el catalogo
 * global (532 filas -> 4 productos). Esta agrega el nivel que faltaba en medio:
 *
 *     Producto (catalogo global)     Camara corporal
 *        └── Stock por cliente       JBGYE: 5            <- esta tabla
 *               └── Lista por local  Garita Norte: 2
 *                                    Garita Sur:   3
 *                                    ─────────────
 *                                    suma 5 <= 5  cuadra
 *
 * **Que habilita, y es lo que no se podia responder antes:** si a un cliente se
 * le repartio mas equipo del que tiene asignado. Hoy las listas de cada local se
 * llenan sin nada contra que contrastar, asi que una sobreasignacion es
 * invisible hasta que alguien va a buscar la camara y no esta.
 *
 * ⚠️ **Es una cantidad declarada, no un contador.** No se mueve sola con las
 * recepciones ni las devoluciones: representa lo que la empresa le asigno al
 * cliente. `inv_producto_catalogo.ipc_stock_actual` existe desde antes y esta en
 * cero en todas las filas; son cosas distintas y conviene no mezclarlas -- si
 * algun dia se usa, sera el stock fisico en bodega, no lo asignado.
 *
 * El unico es (cliente, producto): un cliente tiene **una** cifra por producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_stock_cliente', function (Blueprint $table) {
            $table->id('isc_id');

            $table->bigInteger('isc_org_code');
            $table->unsignedBigInteger('isc_producto_id');

            // Decimal y no entero: hay productos que se miden (metros de cinta,
            // litros). Los cuatro de hoy son unidades, pero el tipo sigue al de
            // `lia_cantidad_default`, con el que se compara.
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
                ->references('org_code')->on('organizacion')
                ->cascadeOnDelete();

            $table->foreign('isc_producto_id')
                ->references('ipc_id')->on('inv_producto_catalogo')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_stock_cliente');
    }
};

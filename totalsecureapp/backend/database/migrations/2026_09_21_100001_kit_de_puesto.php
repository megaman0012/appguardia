<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El kit de puesto: la plantilla de la que salen las listas de inventario.
 *
 * **El problema, medido:** hay 132 listas y **130 son identicas entre si** --los
 * mismos 4 productos, cantidad 1--. Es una sola plantilla copiada 132 veces, la
 * misma enfermedad que tenia el catalogo (532 filas que eran 4 productos), un
 * nivel mas arriba. Dar inventario a un local nuevo eran ~22 interacciones, y
 * agregar un producto a todos, 132 ediciones.
 *
 * Los nombres ya delataban la duplicacion manual: junto a `SEGURIDAD FISICA`
 * (122) conviven `SEGURIDA FISICA` (3, errata) y `SEGURIDAD FISICA 1` (1).
 *
 * ⚠️ **Esto es puramente ADITIVO, y es deliberado.** `inv_lista` e
 * `inv_lista_item` **conservan su forma exacta**: el kit gobierna como se
 * *crean y actualizan* las listas, no como se *leen*. Asi `/inventario/listbyinst`,
 * la app del guardia y los 23.799 movimientos no se enteran de nada. Se
 * descarto el diseño alternativo --que la lista no guardara items y se
 * calcularan del kit en vivo-- justamente porque cambiaba lo que devuelve la
 * API y obligaba a tocar el camino operativo, que funciona bien.
 *
 * `li_modificada` es lo que hace util la excepcion: un local puede apartarse del
 * kit, y entonces **queda marcado y deja de recibir los cambios del kit**. Hoy
 * un puesto distinto es indistinguible del resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_kit', function (Blueprint $table) {
            $table->id('ki_id');

            $table->string('ki_nombre', 150)->unique();
            $table->text('ki_descripcion')->nullable();
            $table->boolean('ki_activo')->default(true);

            $table->timestamp('ki_created_at')->nullable();
            $table->bigInteger('ki_created_user')->nullable();
            $table->timestamp('ki_updated_at')->nullable();
            $table->bigInteger('ki_updated_user')->nullable();
        });

        Schema::create('inv_kit_item', function (Blueprint $table) {
            $table->id('kii_id');

            $table->unsignedBigInteger('kii_ki_id');
            $table->unsignedBigInteger('kii_producto_id');

            // Decimal como `lia_cantidad_default`, con el que se compara.
            $table->decimal('kii_cantidad', 12, 2)->default(1);

            $table->timestamp('kii_created_at')->nullable();
            $table->timestamp('kii_updated_at')->nullable();

            $table->unique(['kii_ki_id', 'kii_producto_id'], 'uk_kit_producto');

            $table->foreign('kii_ki_id')
                ->references('ki_id')->on('inv_kit')->cascadeOnDelete();

            $table->foreign('kii_producto_id')
                ->references('ipc_id')->on('inv_producto_catalogo')->cascadeOnDelete();
        });

        Schema::table('inv_lista', function (Blueprint $table) {
            // Nulo = lista suelta, no salio de ningun kit.
            $table->unsignedBigInteger('li_kit_id')->nullable()->after('li_ins_code');

            /*
             * Se aparto del kit: alguien cambio cantidades, quito o agrego
             * productos. Deja de recibir los cambios del kit y se ve como
             * excepcion en el listado.
             */
            $table->boolean('li_modificada')->default(false)->after('li_kit_id');

            /*
             * ⚠️ **Huella del kit EN EL MOMENTO EN QUE SE APLICO**, y es lo que
             * hace que todo esto funcione.
             *
             * La tentacion es calcular «modificada» comparando la lista contra
             * el kit actual. Eso se rompe en cuanto alguien **agrega un producto
             * al kit**: de golpe las 132 listas difieren del kit, todas quedan
             * marcadas como excepcion, y el cambio **no se propaga a ninguna**
             * -- exactamente al reves de lo que se busca. Lo cazo un test.
             *
             * Guardando lo que el kit decia al sincronizar, la comparacion
             * responde la pregunta correcta: «¿alguien toco ESTA lista desde la
             * ultima vez?», que es independiente de que el kit haya cambiado
             * despues.
             */
            $table->string('li_kit_huella', 64)->nullable()->after('li_modificada');

            $table->index('li_kit_id', 'idx_lista_kit');

            $table->foreign('li_kit_id')
                ->references('ki_id')->on('inv_kit')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inv_lista', function (Blueprint $table) {
            $table->dropForeign(['li_kit_id']);
            $table->dropIndex('idx_lista_kit');
            $table->dropColumn(['li_kit_id', 'li_modificada', 'li_kit_huella']);
        });

        Schema::dropIfExists('inv_kit_item');
        Schema::dropIfExists('inv_kit');
    }
};

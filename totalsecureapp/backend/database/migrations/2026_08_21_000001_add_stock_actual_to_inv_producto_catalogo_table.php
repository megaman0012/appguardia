<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockActualToInvProductoCatalogoTable extends Migration
{
    public function up(): void
    {
        Schema::table('inv_producto_catalogo', function (Blueprint $table) {
            $table->decimal('ipc_stock_actual', 10, 2)->default(0)->after('ipc_activo');
            $table->index('ipc_stock_actual', 'idx_producto_catalogo_stock');
        });
    }

    public function down(): void
    {
        Schema::table('inv_producto_catalogo', function (Blueprint $table) {
            $table->dropIndex('idx_producto_catalogo_stock');
            $table->dropColumn('ipc_stock_actual');
        });
    }
}

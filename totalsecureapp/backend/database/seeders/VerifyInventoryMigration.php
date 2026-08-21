<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VerifyInventoryMigration extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== Verificación de Migración de Inventario ===');
        
        $this->verificarProductos();
        $this->verificarListas();
        $this->verificarItems();
        $this->verificarMovimientos();
        $this->verificarDetalles();
        $this->verificarStock();
        
        $this->command->info('=== Verificación completada ===');
    }

    private function verificarProductos(): void
    {
        $viejos = DB::table('inv_productos')->count();
        $nuevos = DB::table('inv_producto_catalogo')->count();
        
        $this->command->info("Productos: {$viejos} originales → {$nuevos} migrados");
        
        if ($nuevos < $viejos) {
            $this->command->warn("  ⚠️ Se perdieron productos en la migración");
        }
    }

    private function verificarListas(): void
    {
        $viejas = DB::table('inv_listas_productos')->count();
        $nuevas = DB::table('inv_lista')->count();
        
        $this->command->info("Listas: {$viejas} originales → {$nuevas} migradas");
        
        if ($nuevas != $viejas) {
            $this->command->warn("  ⚠️ Diferencia en cantidad de listas");
        }
    }

    private function verificarItems(): void
    {
        $viejos = DB::table('inv_lista_producto_items')->count();
        $nuevos = DB::table('inv_lista_item')->count();
        
        $this->command->info("Items: {$viejos} originales → {$nuevos} migrados");
        
        if ($nuevos < $viejos) {
            $this->command->warn("  ⚠️ Se perdieron items en la migración");
        }
    }

    private function verificarMovimientos(): void
    {
        $viejos = DB::table('inv_movimientos')->count();
        $nuevos = DB::table('inv_movimiento_cabecera')->count();
        
        $this->command->info("Movimientos: {$viejos} originales → {$nuevos} migrados");
        
        if ($nuevos != $viejos) {
            $this->command->warn("  ⚠️ Diferencia en cantidad de movimientos");
        }
    }

    private function verificarDetalles(): void
    {
        $viejos = DB::table('inv_movimiento_detalles')->count();
        $nuevos = DB::table('inv_movimiento_detalle')->count();
        
        $this->command->info("Detalles: {$viejos} originales → {$nuevos} migrados");
        
        if ($nuevos < $viejos) {
            $this->command->warn("  ⚠️ Se perdieron detalles en la migración");
        }
    }

    private function verificarStock(): void
    {
        $this->command->info("Verificando stock...");
        
        $productos = DB::table('inv_producto_catalogo')
            ->where('ipc_activo', true)
            ->get();
        
        foreach ($productos as $prod) {
            $recepciones = DB::table('inv_movimiento_detalle')
                ->join('inv_movimiento_cabecera', 'md_movimiento_id', '=', 'mc_id')
                ->where('md_producto_id', $prod->ipc_id)
                ->where('mc_tipo', 'recepcion')
                ->where('mc_estado', 'completado')
                ->sum('md_cantidad_real');
            
            $devoluciones = DB::table('inv_movimiento_detalle')
                ->join('inv_movimiento_cabecera', 'md_movimiento_id', '=', 'mc_id')
                ->where('md_producto_id', $prod->ipc_id)
                ->where('mc_tipo', 'devolucion')
                ->where('mc_estado', 'completado')
                ->sum('md_cantidad_real');
            
            $stockCalculado = $recepciones - $devoluciones;
            
            $this->command->info(
                "  {$prod->ipc_nombre}: Stock={$stockCalculado}, Recepciones={$recepciones}, Devoluciones={$devoluciones}"
            );
        }
    }
}

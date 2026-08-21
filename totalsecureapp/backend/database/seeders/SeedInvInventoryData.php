<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeedInvInventoryData extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== Migración de Inventario ===');
        
        $this->migrarProductos();
        $this->migrarListas();
        $this->migrarItems();
        $this->migrarMovimientos();
        $this->migrarDetalles();
        $this->actualizarStock();
        
        $this->command->info('=== Migración completada ===');
    }

    private function migrarProductos(): void
    {
        $this->command->info('Migrando productos...');
        
        $productos = DB::table('inv_productos')->get();
        $count = 0;
        
        foreach ($productos as $prod) {
            $instituciones = DB::table('inv_lista_producto_items')
                ->join('inv_listas_productos', 'lpi_lp_id', '=', 'lp_id')
                ->where('lpi_pr_id', $prod->pr_id)
                ->distinct()
                ->pluck('lp_ins_code');

            if ($instituciones->isEmpty()) {
                $instituciones = collect([1]);
            }

            foreach ($instituciones as $insCode) {
                $existe = DB::table('inv_producto_catalogo')
                    ->where('ipc_ins_code', $insCode)
                    ->where('ipc_nombre', $prod->pr_nombre)
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('inv_producto_catalogo')->insert([
                    'ipc_ins_code'       => $insCode,
                    'ipc_nombre'         => $prod->pr_nombre,
                    'ipc_descripcion'    => $prod->pr_descripcion,
                    'ipc_especificacion' => $prod->pr_especificacion,
                    'ipc_activo'         => $prod->pr_estado == 1,
                    'ipc_created_at'     => $prod->pr_created_at ?? now(),
                    'ipc_created_user'   => $prod->pr_created_user ?? null,
                    'ipc_updated_at'     => $prod->pr_updated_at ?? now(),
                    'ipc_updated_user'   => $prod->pr_updated_user ?? null,
                ]);
                $count++;
            }
        }
        
        $this->command->info("  → {$count} productos migrados");
    }

    private function migrarListas(): void
    {
        $this->command->info('Migrando listas...');
        
        $listas = DB::table('inv_listas_productos')->get();
        $count = 0;
        
        foreach ($listas as $lista) {
            $existe = DB::table('inv_lista')->where('li_id', $lista->lp_id)->exists();

            if ($existe) {
                continue;
            }

            DB::table('inv_lista')->insert([
                'li_id'            => $lista->lp_id,
                'li_ins_code'      => $lista->lp_ins_code,
                'li_nombre'        => $lista->lp_nombre,
                'li_descripcion'   => $lista->lp_descripcion,
                'li_activo'        => $lista->lp_estado == 1,
                'li_created_at'    => $lista->lp_created_at ?? now(),
                'li_created_user'  => $lista->lp_created_user ?? null,
                'li_updated_at'    => $lista->lp_updated_at ?? now(),
                'li_updated_user'  => $lista->lp_updated_user ?? null,
            ]);
            $count++;
        }
        
        $this->command->info("  → {$count} listas migradas");
    }

    private function migrarItems(): void
    {
        $this->command->info('Migrando items de lista...');
        
        $items = DB::table('inv_lista_producto_items')->get();
        $count = 0;
        
        foreach ($items as $item) {
            $lista = DB::table('inv_listas_productos')
                ->where('lp_id', $item->lpi_lp_id)
                ->first();
            
            if ($lista) {
                $productoCatalogo = DB::table('inv_producto_catalogo')
                    ->where('ipc_ins_code', $lista->lp_ins_code)
                    ->where('ipc_nombre', function($q) use ($item) {
                        $q->select('pr_nombre')
                          ->from('inv_productos')
                          ->where('pr_id', $item->lpi_pr_id);
                    })
                    ->first();
                
                if ($productoCatalogo) {
                    $existe = DB::table('inv_lista_item')
                        ->where('lia_lista_id', $item->lpi_lp_id)
                        ->where('lia_producto_id', $productoCatalogo->ipc_id)
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    DB::table('inv_lista_item')->insert([
                        'lia_lista_id'          => $item->lpi_lp_id,
                        'lia_producto_id'       => $productoCatalogo->ipc_id,
                        'lia_cantidad_default'  => $item->lpi_cantidad,
                        'lia_activo'            => $item->lpi_estado == 1,
                        'lia_created_at'        => $item->lpi_created_at ?? now(),
                        'lia_created_user'      => $item->lpi_created_user ?? null,
                        'lia_updated_at'        => $item->lpi_updated_at ?? now(),
                        'lia_updated_user'      => $item->lpi_updated_user ?? null,
                    ]);
                    $count++;
                }
            }
        }
        
        $this->command->info("  → {$count} items migrados");
    }

    private function migrarMovimientos(): void
    {
        $this->command->info('Migrando movimientos...');
        
        $movimientos = DB::table('inv_movimientos')->get();
        $count = 0;
        
        foreach ($movimientos as $mov) {
            $tipo = match(strtolower($mov->mov_tipo)) {
                'recepcion' => 'recepcion',
                'devolucion' => 'devolucion',
                default => 'recepcion',
            };
            
            $estado = match($mov->mov_estado) {
                1 => 'completado',
                0 => 'pendiente',
                default => 'pendiente',
            };
            
            $existe = DB::table('inv_movimiento_cabecera')->where('mc_id', $mov->mov_id)->exists();

            if ($existe) {
                continue;
            }

            DB::table('inv_movimiento_cabecera')->insert([
                'mc_id'             => $mov->mov_id,
                'mc_ins_code'       => $mov->mov_ins_code,
                'mc_lista_id'       => $mov->mov_lp_id,
                'mc_tipo'           => $tipo,
                'mc_usuario_id'     => $mov->mov_recep_user ?? $mov->mov_devol_user ?? 1,
                'mc_fecha'          => $mov->mov_recep_fecha ?? $mov->mov_devol_fecha ?? now(),
                'mc_lat'            => $mov->mov_recep_lat ?? $mov->mov_devol_lat,
                'mc_lng'            => $mov->mov_recep_lng ?? $mov->mov_devol_lng,
                'mc_observaciones'  => $mov->mov_recep_obsv ?? $mov->mov_devol_obsv,
                'mc_estado'         => $estado,
                'mc_created_at'     => $mov->mov_created_at ?? now(),
                'mc_created_user'   => $mov->mov_created_user ?? null,
                'mc_updated_at'     => $mov->mov_updated_at ?? now(),
                'mc_updated_user'   => $mov->mov_updated_user ?? null,
            ]);
            $count++;
        }
        
        $this->command->info("  → {$count} movimientos migrados");
    }

    private function migrarDetalles(): void
    {
        $this->command->info('Migrando detalles de movimientos...');
        
        $detalles = DB::table('inv_movimiento_detalles')->get();
        $count = 0;
        
        foreach ($detalles as $det) {
            $movimiento = DB::table('inv_movimiento_cabecera')
                ->where('mc_id', $det->md_mov_id)
                ->first();
            
            if ($movimiento) {
                $productoCatalogo = DB::table('inv_producto_catalogo')
                    ->where('ipc_ins_code', $movimiento->mc_ins_code)
                    ->where('ipc_nombre', function($q) use ($det) {
                        $q->select('pr_nombre')
                          ->from('inv_productos')
                          ->where('pr_id', $det->md_pr_id);
                    })
                    ->first();
                
                if ($productoCatalogo) {
                    $existe = DB::table('inv_movimiento_detalle')
                        ->where('md_movimiento_id', $det->md_mov_id)
                        ->where('md_producto_id', $productoCatalogo->ipc_id)
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    $estado = match($det->md_exist) {
                        1 => 'ok',
                        0 => 'falta',
                        default => 'ok',
                    };
                    
                    DB::table('inv_movimiento_detalle')->insert([
                        'md_movimiento_id'     => $det->md_mov_id,
                        'md_producto_id'       => $productoCatalogo->ipc_id,
                        'md_cantidad_default'  => $det->md_cant_asign ?? 0,
                        'md_cantidad_real'     => $det->md_cant_recep ?? 0,
                        'md_recibido'          => $det->md_cant_recep > 0,
                        'md_observacion'       => $det->md_recep_obsv,
                        'md_estado'            => $estado,
                        'md_created_at'        => $det->md_created_at ?? now(),
                        'md_created_user'      => $det->md_created_user ?? null,
                        'md_updated_at'        => $det->md_updated_at ?? now(),
                        'md_updated_user'      => $det->md_updated_user ?? null,
                    ]);
                    $count++;
                }
            }
        }
        
        $this->command->info("  → {$count} detalles migrados");
    }

    private function actualizarStock(): void
    {
        $this->command->info('Actualizando stock actual...');
        
        $productos = DB::table('inv_producto_catalogo')->get();
        $count = 0;
        
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
            
            $bajas = DB::table('inv_movimiento_detalle')
                ->join('inv_movimiento_cabecera', 'md_movimiento_id', '=', 'mc_id')
                ->where('md_producto_id', $prod->ipc_id)
                ->where('mc_tipo', 'baja')
                ->where('mc_estado', 'completado')
                ->sum('md_cantidad_real');
            
            $stock = $recepciones - $devoluciones - $bajas;
            
            DB::table('inv_producto_catalogo')
                ->where('ipc_id', $prod->ipc_id)
                ->update(['ipc_stock_actual' => max(0, $stock)]);
            
            $count++;
        }
        
        $this->command->info("  → {$count} stocks actualizados");
    }
}

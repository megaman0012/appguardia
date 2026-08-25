<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos minimos para que una instalacion de produccion funcione (Fase dominio).
 *
 * DatabaseSeeder sirve para desarrollo, pero crea el usuario demo
 * (cedula 1234567890, credencial publicada en el repositorio) y una institucion
 * de mentira con marcadores QR y checklist de inventario. Nada de eso debe
 * llegar a produccion.
 *
 * Este seeder deja solo lo que el sistema necesita para arrancar:
 *   - el parametro 'access', sin el cual POST /api/login responde
 *     "Parametro acceso no definido"
 *
 * Los permisos del panel y los de la app y el portal los crean las migraciones,
 * asi que aqui no se repiten.
 *
 * Los roles y los permisos de la app y del portal los crean las migraciones
 * (2026_08_21_200001 y 2026_08_21_400001), asi que aqui no se repiten.
 *
 * El primer usuario real se crea aparte:  php artisan usuario:crear
 *
 * Es idempotente: se puede volver a correr sin duplicar nada.
 */
class ProductionSeeder extends Seeder
{
    public function run()
    {
        $this->seedParametroAcceso();
    }

    private function seedParametroAcceso(): void
    {
        $valor = env('ACCESS_PARAM_VALUE');

        if (empty($valor)) {
            // Sin este valor el login de la app no funciona, y un valor por
            // defecto silencioso en produccion es peor que un aviso.
            $this->command->warn(
                'ACCESS_PARAM_VALUE no esta definido en .env; se usa un valor generado. ' .
                'Definirlo y volver a correr el seeder.'
            );
            $valor = 'TS-' . now()->format('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        }

        DB::table('parametros')->updateOrInsert(
            ['pr_descripcion' => 'access'],
            ['pr_value' => $valor]
        );

        $this->command->info("Parametro 'access' configurado.");
    }
}

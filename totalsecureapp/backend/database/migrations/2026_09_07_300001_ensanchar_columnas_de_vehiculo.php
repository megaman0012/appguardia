<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensancha `av_patente` y `av_kms` para que quepan los datos reales de v1.
 *
 * `acceso_vehiculo` se diseño suponiendo que esas columnas traerian una placa y
 * un kilometraje. En los datos de produccion son **texto libre** que el guardia
 * escribio en la garita:
 *
 *   ac_patente (max 31): "Placa GTK-8594 Trailblazer auto"
 *                        "Vehículo #191 placas GTJ-2646"
 *   ac_kms     (max 43): "CRJ 0809.  -   SELLO ROTO LEVAPAN. CRJ 3684"
 *                        "CTL 5312. SELLO ROTO EN EL TUTY SALCEDO"
 *
 * Con varchar(20) el ETL fallaba con "value too long". Truncar no era opcion:
 * "Placa GTK-8594 Trail" no es una placa ni una observacion, es basura. Se
 * ensancha a 100, que cubre el maximo real con margen.
 *
 * Que el campo se use para observaciones es un problema de la pantalla, no de la
 * columna; y arreglarlo no puede empezar por perder lo que ya se escribio.
 *
 * Va en SQL directo y no con `->change()` porque eso exige doctrine/dbal, y no
 * vale sumar una dependencia al proyecto para ensanchar dos columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE acceso_vehiculo ALTER COLUMN av_patente TYPE varchar(100)');
        DB::statement('ALTER TABLE acceso_vehiculo ALTER COLUMN av_kms TYPE varchar(100)');
    }

    public function down(): void
    {
        // Volver a 20 truncaria: Postgres rechaza el ALTER si hay filas mas
        // largas, y es lo correcto. Para revertir de verdad hay que decidir
        // primero que se hace con esos valores.
        DB::statement('ALTER TABLE acceso_vehiculo ALTER COLUMN av_patente TYPE varchar(20)');
        DB::statement('ALTER TABLE acceso_vehiculo ALTER COLUMN av_kms TYPE varchar(20)');
    }
};

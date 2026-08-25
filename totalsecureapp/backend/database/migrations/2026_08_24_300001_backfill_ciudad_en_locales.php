<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pasa la ciudad del Local de texto libre (ins_ciudad) a la FK ins_cd_id.
 *
 * La migracion anterior creo la jerarquia Pais > Provincia > Ciudad, pero los
 * locales seguian con la ciudad escrita a mano en ins_ciudad. Sin ins_cd_id un
 * local no pertenece a ningun pais, asi que no lo veria ningun Lider Operativo.
 *
 * Solo mapea lo que puede resolver sin ambiguedad. Lo que no calce queda sin
 * ciudad y se completa desde el panel: es preferible dejarlo pendiente y visible
 * a adivinar mal y meterlo en el pais equivocado.
 */
return new class extends Migration
{
    /** Ciudad => provincia a la que pertenece. */
    private const CIUDADES = [
        'Guayaquil' => 'Guayas',
    ];

    public function up(): void
    {
        foreach (self::CIUDADES as $ciudad => $provincia) {
            $prId = DB::table('provincia')->where('pr_nombre', $provincia)->value('pr_id');
            if (!$prId) {
                continue;
            }

            DB::table('ciudad')->updateOrInsert(
                ['cd_pr_id' => $prId, 'cd_nombre' => $ciudad],
                ['cd_estado' => true, 'created_at' => now(), 'updated_at' => now()]
            );

            $cdId = DB::table('ciudad')
                ->where('cd_pr_id', $prId)
                ->where('cd_nombre', $ciudad)
                ->value('cd_id');

            // Solo los que aun no tienen ciudad asignada: no se pisa nada elegido
            // a mano desde el panel.
            DB::table('organizacion_institucion')
                ->whereNull('ins_cd_id')
                ->whereRaw('LOWER(TRIM(ins_ciudad)) = ?', [mb_strtolower($ciudad)])
                ->update(['ins_cd_id' => $cdId]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('ciudad')
            ->whereIn('cd_nombre', array_keys(self::CIUDADES))
            ->pluck('cd_id');

        if ($ids->isEmpty()) {
            return;
        }

        // Primero se sueltan los locales: la FK es onDelete restrict.
        DB::table('organizacion_institucion')
            ->whereIn('ins_cd_id', $ids)
            ->update(['ins_cd_id' => null]);

        DB::table('ciudad')->whereIn('cd_id', $ids)->delete();
    }
};

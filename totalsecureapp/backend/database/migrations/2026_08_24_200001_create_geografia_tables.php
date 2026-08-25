<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jerarquia territorial: Pais > Provincia > Ciudad, y el enganche del Local.
 *
 * La empresa opera en varios paises (Ecuador, Colombia) con clientes
 * multinacionales. Hasta ahora el pais era la columna de texto libre
 * organizacion.org_pais, que el formulario del panel ni siquiera mostraba: no
 * habia forma de seleccionarlo ni de agrupar por el.
 *
 * DISEÑO: el Local (organizacion_institucion) apunta al Cliente Y a la Ciudad,
 * en vez de colgar de una cadena rigida cliente->pais->provincia->ciudad. Asi el
 * cliente DHL es UN solo registro y el analisis global de gerencia sale directo
 * (where ins_cliente_id = DHL), mientras que el corte por pais sale subiendo
 * ciudad->provincia->pais. Una cadena rigida obligaria a crear "DHL Ecuador" y
 * "DHL Colombia" como clientes distintos, y cualquier consulta global tendria
 * que saber de antemano cuantas filiales existen.
 *
 * ins_code NO se toca: esta en 17 tablas y es el eje de rondas, accesos,
 * alertas, novedades, biometria, turnos, inventario y el alcance del portal.
 * Todo aqui es aditivo.
 *
 * Las columnas nuevas son nullable porque los locales existentes todavia no
 * tienen ciudad ni cliente asignados; se completan desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pais', function (Blueprint $table) {
            $table->bigIncrements('pa_id');
            $table->string('pa_iso2', 2)->unique();   // 'EC'
            $table->string('pa_iso3', 3)->nullable(); // 'ECU'
            $table->string('pa_nombre');
            $table->boolean('pa_estado')->default(true);
            $table->timestamps();
        });

        Schema::create('provincia', function (Blueprint $table) {
            $table->bigIncrements('pr_id');
            $table->unsignedBigInteger('pr_pa_id');
            $table->string('pr_codigo', 8)->nullable(); // ISO 3166-2
            $table->string('pr_nombre');
            $table->boolean('pr_estado')->default(true);
            $table->timestamps();

            $table->foreign('pr_pa_id')->references('pa_id')->on('pais')->onDelete('cascade');
            $table->unique(['pr_pa_id', 'pr_nombre']);
            $table->index('pr_pa_id');
        });

        Schema::create('ciudad', function (Blueprint $table) {
            $table->bigIncrements('cd_id');
            $table->unsignedBigInteger('cd_pr_id');
            $table->string('cd_nombre');
            $table->boolean('cd_estado')->default(true);
            $table->timestamps();

            $table->foreign('cd_pr_id')->references('pr_id')->on('provincia')->onDelete('cascade');
            $table->unique(['cd_pr_id', 'cd_nombre']);
            $table->index('cd_pr_id');
        });

        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->unsignedBigInteger('ins_cd_id')->nullable();
            $table->unsignedBigInteger('ins_cliente_id')->nullable();

            // Sin onDelete cascade a proposito: borrar una ciudad no debe
            // arrastrarse los locales ni sus rondas, accesos y alertas.
            $table->foreign('ins_cd_id')->references('cd_id')->on('ciudad')->onDelete('restrict');
            $table->foreign('ins_cliente_id')->references('org_code')->on('organizacion')->onDelete('restrict');

            // El filtro del Lider Operativo acota por pais subiendo desde aqui.
            $table->index('ins_cd_id');
            $table->index('ins_cliente_id');
        });

        $this->sembrarPaises();
        $this->sembrarProvinciasDeEcuador();
    }

    public function down(): void
    {
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->dropForeign(['ins_cd_id']);
            $table->dropForeign(['ins_cliente_id']);
            $table->dropColumn(['ins_cd_id', 'ins_cliente_id']);
        });

        Schema::dropIfExists('ciudad');
        Schema::dropIfExists('provincia');
        Schema::dropIfExists('pais');
    }

    private function sembrarPaises(): void
    {
        $paises = [
            ['pa_iso2' => 'EC', 'pa_iso3' => 'ECU', 'pa_nombre' => 'Ecuador'],
            ['pa_iso2' => 'CO', 'pa_iso3' => 'COL', 'pa_nombre' => 'Colombia'],
        ];

        foreach ($paises as $pais) {
            DB::table('pais')->updateOrInsert(
                ['pa_iso2' => $pais['pa_iso2']],
                $pais + ['pa_estado' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Las 24 provincias de Ecuador, con su codigo ISO 3166-2:EC.
     *
     * Las de Colombia quedan pendientes: se cargan desde el panel o con una
     * migracion equivalente cuando arranque esa operacion.
     */
    private function sembrarProvinciasDeEcuador(): void
    {
        $ecuador = DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');

        $provincias = [
            ['A',  'Azuay'],
            ['B',  'Bolívar'],
            ['F',  'Cañar'],
            ['C',  'Carchi'],
            ['H',  'Chimborazo'],
            ['X',  'Cotopaxi'],
            ['O',  'El Oro'],
            ['E',  'Esmeraldas'],
            ['W',  'Galápagos'],
            ['G',  'Guayas'],
            ['I',  'Imbabura'],
            ['L',  'Loja'],
            ['R',  'Los Ríos'],
            ['M',  'Manabí'],
            ['S',  'Morona Santiago'],
            ['N',  'Napo'],
            ['D',  'Orellana'],
            ['Y',  'Pastaza'],
            ['P',  'Pichincha'],
            ['SE', 'Santa Elena'],
            ['SD', 'Santo Domingo de los Tsáchilas'],
            ['U',  'Sucumbíos'],
            ['T',  'Tungurahua'],
            ['Z',  'Zamora Chinchipe'],
        ];

        foreach ($provincias as [$codigo, $nombre]) {
            DB::table('provincia')->updateOrInsert(
                ['pr_pa_id' => $ecuador, 'pr_nombre' => $nombre],
                [
                    'pr_codigo' => $codigo,
                    'pr_estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};

<?php

namespace Database\Seeders;

use App\Services\PlantillaTurnoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Plantilla;
use Modules\Administracion\Models\PlantillaAsignacion;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;

/**
 * Datos de ejemplo para ver el cuadrante funcionando.
 *
 * NO se llama desde DatabaseSeeder: se corre a mano cuando alguien quiere mirar
 * el sistema con datos encima.
 *
 *     php artisan db:seed --class=CuadranteEjemploSeeder
 *
 * Es idempotente: correrlo dos veces no duplica nada.
 *
 * Los guardias de ejemplo se crean con la MISMA contraseña que los usuarios de
 * prueba que ya existen, copiando el hash en vez de escribir una clave nueva en
 * el repositorio.
 */
class CuadranteEjemploSeeder extends Seeder
{
    private const PUESTOS = ['Garita principal', 'Andén de carga', 'Sala de monitoreo'];

    private array $guardias = [
        ['0900001001', 'Pedro Cobertura Andrade Lopez'],
        ['0900001002', 'Julia Nocturna Vera Campos'],
        ['0900001003', 'Marco Relevo Zambrano Ruiz'],
        ['0900001004', 'Elena Suplente Ochoa Pinto'],
        // Guardia volante: no tiene franjas fijas en el cuadrante. Existe para
        // que la pantalla de cobertura tenga a quién ofrecerle un turno; sin
        // alguien libre, todos los candidatos chocan con su propio horario.
        ['0900001005', 'Ruth Volante Salazar Mena'],
    ];

    public function run(): void
    {
        $local = DB::table('organizacion_institucion')->where('ins_estado', 1)->min('ins_code');

        if (!$local) {
            $this->command->error('No hay ningún local activo. Cree uno antes de sembrar el ejemplo.');

            return;
        }

        $puestos  = $this->sembrarPuestos((int) $local);
        $usuarios = $this->sembrarGuardias((int) $local);

        if (count($usuarios) < 5) {
            $this->command->error('No se pudieron crear los guardias de ejemplo.');

            return;
        }

        $plantilla = Plantilla::firstOrCreate(
            ['pl_ins_code' => $local, 'pl_nombre' => 'Cuadrante semanal — ejemplo'],
            ['pl_observaciones' => 'Sembrado por CuadranteEjemploSeeder para ver el sistema con datos.']
        );

        $plantilla->franjas()->delete();

        // Lunes a viernes, dos turnos que se relevan en la garita.
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $this->franja($plantilla, $puestos['Garita principal'], $dia, '06:00', '14:00', $usuarios[0]);
            $this->franja($plantilla, $puestos['Garita principal'], $dia, '14:00', '22:00', $usuarios[2]);
        }

        // El andén trabaja también el sábado.
        foreach ([1, 2, 3, 4, 5, 6] as $dia) {
            $this->franja($plantilla, $puestos['Andén de carga'], $dia, '08:00', '16:00', $usuarios[3]);
        }

        // Monitoreo cubre la noche todos los días: cruza la medianoche.
        foreach ([1, 2, 3, 4, 5, 6, 7] as $dia) {
            $this->franja($plantilla, $puestos['Sala de monitoreo'], $dia, '22:00', '06:00', $usuarios[1]);
        }

        $desde = Carbon::today()->startOfMonth();
        $hasta = Carbon::today()->endOfMonth();

        $r = app(PlantillaTurnoService::class)->generar($plantilla, $desde, $hasta);

        $this->command->info(sprintf(
            'Cuadrante de ejemplo #%d en el local %d: %d franjas, %d turnos generados del %s al %s.',
            $plantilla->pl_id,
            $local,
            $plantilla->franjas()->count(),
            $r['creados'],
            $desde->format('d/m/Y'),
            $hasta->format('d/m/Y')
        ));

        foreach ($r['avisos'] as $aviso) {
            $this->command->warn('  ' . $aviso);
        }
        foreach ($r['errores'] as $error) {
            $this->command->error('  ' . $error);
        }
    }

    /** @return array<string, int> nombre => pu_id */
    private function sembrarPuestos(int $local): array
    {
        $puestos = [];

        foreach (self::PUESTOS as $nombre) {
            $puestos[$nombre] = Puesto::firstOrCreate(
                ['pu_ins_code' => $local, 'pu_nombre' => $nombre],
                ['pu_estado' => true]
            )->pu_id;
        }

        return $puestos;
    }

    /** @return int[] ids de usuario, en el orden de $this->guardias */
    private function sembrarGuardias(int $local): array
    {
        // Se copia el hash de un usuario existente: así los guardias de ejemplo
        // entran con la misma clave de prueba y no se agrega una nueva al repo.
        $hash = DB::table('users')->whereNotNull('usu_password')->orderBy('id')->value('usu_password');
        $rolVigilante = DB::table('roles')->where('name', 'Vigilante')->value('id');

        $ids = [];

        foreach ($this->guardias as $i => [$cedula, $nombre]) {
            $partes = explode(' ', $nombre);

            DB::table('users')->updateOrInsert(
                ['usu_cedula' => $cedula],
                [
                    'usu_tipdoc'  => 'CC',
                    'usu_password' => $hash,
                    'usu_nmbcom'  => $nombre,
                    'usu_nmb1'    => $partes[0],
                    'usu_nmb2'    => $partes[1] ?? '',
                    'usu_ape1'    => $partes[2] ?? '',
                    'usu_ape2'    => $partes[3] ?? '',
                    'usu_email'   => $cedula . '@ejemplo.local',
                    'usu_state'   => 1,
                    // El volante y el de la mañana aceptan turnos extra.
                    'usu_acepta_extras' => $i === 0 || $i === 4,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );

            $id = (int) DB::table('users')->where('usu_cedula', $cedula)->value('id');
            $ids[] = $id;

            DB::table('user_has_institucion')->updateOrInsert(
                ['ui_usu_id' => $id, 'ui_ins_code' => $local],
                ['ui_state' => 1]
            );

            if ($rolVigilante) {
                DB::table('user_has_roles')->updateOrInsert(
                    ['user_id' => $id, 'role_id' => $rolVigilante],
                    ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
                );
            }
        }

        return $ids;
    }

    private function franja(Plantilla $plantilla, int $puestoId, int $dia, string $inicio, string $fin, int $usuario): void
    {
        $franja = PlantillaFranja::create([
            'pf_pl_id'       => $plantilla->pl_id,
            'pf_puesto_id'   => $puestoId,
            'pf_dia_semana'  => $dia,
            'pf_hora_inicio' => $inicio,
            'pf_hora_fin'    => $fin,
        ]);

        PlantillaAsignacion::create([
            'pa_pf_id'  => $franja->pf_id,
            'pa_usu_id' => $usuario,
        ]);
    }
}

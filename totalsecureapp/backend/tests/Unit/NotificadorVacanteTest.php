<?php

namespace Tests\Unit;

use App\Services\Avisos\CanalDeAviso;
use App\Services\NotificadorVacante;
use App\Services\VacanteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;
use Tests\TestCase;

/**
 * Canal de prueba: guarda los avisos en memoria en vez de mandarlos.
 *
 * Existe para poder preguntar "¿a quién se le avisó?" sin depender de la red ni
 * de que Firebase esté configurado.
 */
class CanalDePrueba implements CanalDeAviso
{
    /** @var array<int, array{usuario: int, titulo: string, tipo: string}> */
    public static array $enviados = [];
    public static bool $falla = false;

    public function nombre(): string
    {
        return 'prueba';
    }

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, array $datos = []): bool
    {
        if (self::$falla) {
            throw new \RuntimeException('Canal caído');
        }

        self::$enviados[] = [
            'usuario' => $usuarioId,
            'titulo'  => $titulo,
            'tipo'    => $datos['tipo'] ?? '',
        ];

        return true;
    }
}

/**
 * A quién le llega el aviso de que un puesto quedó vacío.
 *
 * Lo que se fija acá es a quién se le avisa y a quién no. Avisarle a todos
 * parece inofensivo y es lo peor que se puede hacer: en dos semanas nadie mira
 * los avisos y el canal deja de servir justo cuando hace falta.
 */
class NotificadorVacanteTest extends TestCase
{
    use RefreshDatabase;

    private int $local;
    private int $otroLocal;
    private int $lider = 1;
    private int $supervisor = 2;
    private int $supervisorAjeno = 3;
    private int $guardiaDelLocal = 4;
    private int $guardiaDeLaCiudad = 5;
    private int $ausente = 6;

    protected function setUp(): void
    {
        parent::setUp();

        CanalDePrueba::$enviados = [];
        CanalDePrueba::$falla = false;
        config(['avisos.canales' => [CanalDePrueba::class]]);

        $ecuador = (int) DB::table('pais')->where('pa_iso2', 'EC')->value('pa_id');
        $provincia = DB::table('provincia')->where('pr_pa_id', $ecuador)->value('pr_id');
        $ciudad = DB::table('ciudad')->where('cd_pr_id', $provincia)->value('cd_id')
            ?: DB::table('ciudad')->insertGetId([
                'cd_pr_id' => $provincia, 'cd_nombre' => 'Ciudad de prueba', 'cd_estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], 'cd_id');

        $this->local     = $this->crearLocal('Terminal', $ciudad);
        $this->otroLocal = $this->crearLocal('Bodega', $ciudad);

        $this->crearUsuario($this->lider, 'Lider Operativo', null, false);
        DB::table('user_has_pais')->insert([
            'up_usu_id' => $this->lider, 'up_pa_id' => $ecuador, 'up_estado' => true,
        ]);

        $this->crearUsuario($this->supervisor, 'Supervisor', $this->local, false);
        $this->crearUsuario($this->supervisorAjeno, 'Supervisor', $this->otroLocal, false);
        $this->crearUsuario($this->guardiaDelLocal, 'Vigilante', $this->local, true);
        $this->crearUsuario($this->guardiaDeLaCiudad, 'Vigilante', $this->otroLocal, true);
        $this->crearUsuario($this->ausente, 'Vigilante', $this->local, true);
    }

    private function crearLocal(string $nombre, $ciudadId): int
    {
        return DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => $nombre, 'ins_estado' => true, 'ins_cd_id' => $ciudadId,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');
    }

    private function crearUsuario(int $id, string $rol, ?int $local, bool $extras): void
    {
        DB::table('users')->updateOrInsert(['id' => $id], [
            'usu_cedula' => str_pad((string) $id, 10, '0', STR_PAD_LEFT),
            'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => $rol . ' ' . $id, 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => $id . '@e.com',
            'usu_state' => 1, 'usu_acepta_extras' => $extras,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rolId = DB::table('roles')->where('name', $rol)->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => $id, 'role_id' => $rolId],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        if ($local) {
            DB::table('user_has_institucion')->updateOrInsert(
                ['ui_usu_id' => $id, 'ui_ins_code' => $local],
                ['ui_state' => 1]
            );
        }
    }

    private function vacante(): TurnoVacante
    {
        $puesto = Puesto::create([
            'pu_ins_code' => $this->local, 'pu_nombre' => 'Garita', 'pu_estado' => true,
        ]);

        $turno = Turno::create([
            'tu_ins_code' => $this->local, 'tu_usu_id' => $this->ausente,
            'tu_puesto_id' => $puesto->pu_id,
            'tu_fecha' => Carbon::today()->toDateString(),
            'tu_hora_inicio_prevista' => '06:00', 'tu_hora_fin_prevista' => '14:00',
            'tu_estado' => 'programado', 'tu_state' => true,
        ]);

        return app(VacanteService::class)->crearDesdeTurno($turno);
    }

    /** @return int[] */
    private function avisadosDe(string $tipo): array
    {
        return array_values(array_unique(array_column(
            array_filter(CanalDePrueba::$enviados, fn ($a) => $a['tipo'] === $tipo),
            'usuario'
        )));
    }

    // ── La falta detectada va a quien decide ──

    public function test_la_falta_detectada_le_llega_al_lider_y_al_supervisor_del_local(): void
    {
        app(NotificadorVacante::class)->faltaDetectada($this->vacante());

        $avisados = $this->avisadosDe('falta_detectada');

        $this->assertContains($this->lider, $avisados, 'Cubrir el puesto es su responsabilidad');
        $this->assertContains($this->supervisor, $avisados, 'Es quien puede confirmar si el guardia llegó');
    }

    public function test_no_le_llega_al_supervisor_de_otro_local(): void
    {
        app(NotificadorVacante::class)->faltaDetectada($this->vacante());

        $this->assertNotContains($this->supervisorAjeno, $this->avisadosDe('falta_detectada'));
    }

    public function test_la_falta_detectada_no_se_le_avisa_a_los_guardias(): void
    {
        // Todavía no está confirmada: puede ser un teléfono sin señal. Convocar
        // en ese momento sería mover gente por una sospecha.
        app(NotificadorVacante::class)->faltaDetectada($this->vacante());

        $avisados = $this->avisadosDe('falta_detectada');

        $this->assertNotContains($this->guardiaDelLocal, $avisados);
        $this->assertNotContains($this->ausente, $avisados);
    }

    // ── La convocatoria va a quien puede tomarla ──

    public function test_al_abrir_se_avisa_a_los_guardias_del_local(): void
    {
        app(VacanteService::class)->abrir($this->vacante(), $this->supervisor);

        $avisados = $this->avisadosDe('vacante_abierta');

        $this->assertContains($this->guardiaDelLocal, $avisados);
        $this->assertNotContains($this->guardiaDeLaCiudad, $avisados, 'Todavía es la ola del local');
    }

    public function test_al_escalar_no_se_le_insiste_a_quien_ya_recibio_el_aviso(): void
    {
        $servicio = app(VacanteService::class);
        $vacante = $servicio->abrir($this->vacante(), $this->supervisor);
        $vacante->tv_abierta_en = Carbon::now()->subHour();
        $vacante->save();
        CanalDePrueba::$enviados = [];

        $servicio->escalarAlcance();

        $avisados = $this->avisadosDe('vacante_escalada');

        $this->assertContains($this->guardiaDeLaCiudad, $avisados);
        $this->assertNotContains(
            $this->guardiaDelLocal,
            $avisados,
            'Ya recibió el primer aviso y no se postuló: repetirlo no agrega nada'
        );
    }

    // ── El cierre le llega a todos los que se ofrecieron ──

    public function test_al_confirmar_se_avisa_al_elegido_y_a_los_que_no_quedaron(): void
    {
        $servicio = app(VacanteService::class);
        $vacante = $servicio->abrir($this->vacante(), $this->supervisor);
        $vacante->tv_alcance = TurnoVacante::ALCANCE_CIUDAD;
        $vacante->save();

        $servicio->postular($vacante, $this->guardiaDelLocal);
        $servicio->postular($vacante, $this->guardiaDeLaCiudad);
        $elegida = TurnoPostulacion::where('tp_usu_id', $this->guardiaDelLocal)->first();
        CanalDePrueba::$enviados = [];

        $servicio->confirmar($vacante, $elegida->tp_id, $this->supervisor);

        $this->assertContains($this->guardiaDelLocal, $this->avisadosDe('cobertura_confirmada'));
        $this->assertContains(
            $this->guardiaDeLaCiudad,
            $this->avisadosDe('cobertura_asignada_a_otro'),
            'Quedarse esperando una respuesta que no llega es peor que un "ya está cubierto"'
        );
    }

    // ── El aviso nunca manda sobre la operación ──

    public function test_si_el_canal_falla_la_vacante_igual_queda_abierta(): void
    {
        // El aviso acelera, no habilita: el guardia igual ve el turno en la
        // pantalla "Turnos disponibles".
        CanalDePrueba::$falla = true;

        $vacante = app(VacanteService::class)->abrir($this->vacante(), $this->supervisor);

        $this->assertSame(TurnoVacante::ABIERTA, $vacante->fresh()->tv_estado);
    }

    public function test_un_canal_mal_configurado_tampoco_frena_la_operacion(): void
    {
        // Distinto del canal que falla al enviar: acá revienta al construirse,
        // antes de que el notificador pueda protegerse por canal. Es el caso de
        // un `config/avisos.php` con una clase mal escrita, que es justo lo que
        // pasaría al agregar WhatsApp con un typo.
        config(['avisos.canales' => ['App\\Services\\Avisos\\CanalQueNoExiste']]);

        $vacante = app(VacanteService::class)->abrir($this->vacante(), $this->supervisor);

        $this->assertSame(TurnoVacante::ABIERTA, $vacante->fresh()->tv_estado);
    }

    public function test_si_el_canal_falla_la_cobertura_igual_se_confirma(): void
    {
        $servicio = app(VacanteService::class);
        $vacante = $servicio->abrir($this->vacante(), $this->supervisor);
        $servicio->postular($vacante, $this->guardiaDelLocal);
        CanalDePrueba::$falla = true;

        $r = $servicio->confirmar($vacante, TurnoPostulacion::first()->tp_id, $this->supervisor);

        $this->assertSame(TurnoVacante::CUBIERTA, $r['vacante']->tv_estado);
        $this->assertNotNull(Turno::find($r['turno']->tu_id));
    }
}

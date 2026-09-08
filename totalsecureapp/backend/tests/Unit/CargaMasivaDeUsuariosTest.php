<?php

namespace Tests\Unit;

use App\Services\UsuarioImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Carga masiva de usuarios.
 *
 * Lo que se comprueba, sobre todo, es que cree las **cuatro** piezas que el
 * login exige: usuario, rol, gestion abierta y vinculo al local. Es lo que se
 * olvida al hacerlo a mano -- el formulario de Usuarios del panel crea solo la
 * primera, asi que un usuario dado de alta ahi no puede entrar.
 */
class CargaMasivaDeUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private UsuarioImportService $servicio;
    private int $local;
    private string $ruta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(UsuarioImportService::class);

        $this->local = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita Norte',
            'ins_estado'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], 'ins_code');

        $this->ruta = storage_path('app/prueba-usuarios.csv');
    }

    protected function tearDown(): void
    {
        @unlink($this->ruta);
        parent::tearDown();
    }

    private function archivo(string $contenido): string
    {
        file_put_contents($this->ruta, $contenido);

        return $this->ruta;
    }

    private function csv(array $filas, string $cabecera = 'cedula,nombres,apellidos,rol,locales,email'): string
    {
        return $cabecera . "\n" . implode("\n", $filas) . "\n";
    }

    // ── Las cuatro piezas ──

    public function test_crea_usuario_rol_gestion_y_local(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,JUAN CARLOS,PEREZ GOMEZ,Vigilante,{$this->local},j@e.com",
        ])));

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['creados']);

        $u = users::where('usu_cedula', '0912345678')->first();
        $this->assertNotNull($u);
        $this->assertSame(1, (int) $u->usu_state);
        $this->assertSame('JUAN CARLOS PEREZ GOMEZ', $u->usu_nmbcom);

        // 2. Rol
        $rol = DB::table('user_has_roles')->join('roles', 'roles.id', '=', 'user_has_roles.role_id')
            ->where('user_id', $u->id)->value('roles.name');
        $this->assertSame('Vigilante', $rol);

        // 3. Gestion ABIERTA: sin esto el login responde «no tiene una gestion
        //    activa» y el usuario queda creado pero inservible.
        $this->assertTrue(
            DB::table('user_has_gestions')->where('ug_user_id', $u->id)->where('ug_finish', false)->exists()
        );

        // 4. Vinculo al local: sin esto la app movil no puede registrar nada.
        $this->assertTrue(
            DB::table('user_has_institucion')
                ->where('ui_usu_id', $u->id)->where('ui_ins_code', $this->local)->where('ui_state', 1)->exists()
        );
    }

    public function test_la_clave_temporal_se_devuelve_y_sirve_para_entrar(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},a@e.com",
        ])));

        $this->assertCount(1, $r['claves']);
        $clave = $r['claves'][0]['clave'];

        // La clave NO viaja en el archivo de entrada: se genera y se entrega una
        // sola vez. Y tiene que quedar hasheada y ser valida.
        $hash = users::where('usu_cedula', '0912345678')->value('usu_password');
        $this->assertNotSame($clave, $hash, 'La clave se guardó en texto plano');
        $this->assertTrue(Hash::check($clave, $hash));

        // Mismas reglas que exige el cambio de clave de la app.
        $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $clave);
    }

    public function test_cada_usuario_recibe_una_clave_distinta(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
            "0912345679,LUIS,MORA,Vigilante,{$this->local},",
            "0912345680,EVA,SOTO,Vigilante,{$this->local},",
        ])));

        $claves = array_column($r['claves'], 'clave');
        $this->assertCount(3, array_unique($claves));
    }

    // ── Validacion antes de escribir ──

    public function test_analizar_no_escribe_nada(): void
    {
        $this->servicio->analizar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
        ])));

        $this->assertSame(0, users::where('usu_cedula', '0912345678')->count());
    }

    public function test_un_error_de_formato_no_crea_ninguno(): void
    {
        // Todo o nada: una nomina cargada a medias es peor que no cargarla.
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
            "0912345679,LUIS,MORA,RolQueNoExiste,{$this->local},",
        ])));

        $this->assertSame(0, $r['creados']);
        $this->assertNotSame([], $r['errores']);
        $this->assertSame(0, users::whereIn('usu_cedula', ['0912345678', '0912345679'])->count());
    }

    public function test_avisa_de_una_cedula_repetida_dentro_del_archivo(): void
    {
        $r = $this->servicio->analizar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
        ])));

        $this->assertNotSame([], $r['errores']);
        $this->assertStringContainsString('repetida', implode(' ', $r['errores']));
    }

    public function test_una_cedula_que_ya_existe_se_omite_con_aviso(): void
    {
        DB::table('users')->insert([
            'usu_cedula' => '0912345678', 'usu_tipdoc' => 'C', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Ya Existe', 'usu_ape1' => 'A', 'usu_ape2' => 'A',
            'usu_nmb1' => 'B', 'usu_nmb2' => 'B', 'usu_email' => 'ya@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,OTRO,NOMBRE,Vigilante,{$this->local},",
            "0912345679,LUIS,MORA,Vigilante,{$this->local},",
        ])));

        // La que existe se salta, la otra entra: no se aborta la carga entera
        // por una persona que ya estaba.
        $this->assertSame(1, $r['creados']);
        $this->assertStringContainsString('ya existe', implode(' ', $r['avisos']));
        $this->assertSame('Ya Existe', users::where('usu_cedula', '0912345678')->value('usu_nmbcom'));
    }

    public function test_rechaza_una_cedula_con_letras(): void
    {
        $r = $this->servicio->analizar($this->archivo($this->csv([
            "09ABC45678,ANA,LOPEZ,Vigilante,{$this->local},",
        ])));

        $this->assertNotSame([], $r['errores']);
    }

    public function test_rechaza_un_local_inexistente(): void
    {
        $r = $this->servicio->analizar($this->archivo($this->csv([
            '0912345678,ANA,LOPEZ,Vigilante,99999,',
        ])));

        $this->assertStringContainsString('no se reconocen', mb_strtolower(implode(' ', $r['errores'])));
    }

    public function test_sin_local_se_crea_pero_avisa(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv([
            '0912345678,ANA,LOPEZ,Vigilante,,',
        ])));

        $this->assertSame(1, $r['creados']);
        $this->assertStringContainsString('la app no le permitirá registrar', implode(' ', $r['avisos']));
    }

    public function test_faltan_columnas_avisa_y_no_escribe(): void
    {
        $r = $this->servicio->importar($this->archivo("cedula,nombres\n0912345678,ANA\n"));

        $this->assertSame(0, $r['creados']);
        $this->assertStringContainsString('Faltan columnas', implode(' ', $r['errores']));
    }

    // ── Tolerancia al archivo que manda la gente ──

    public function test_acepta_punto_y_coma_y_el_BOM_de_excel(): void
    {
        // Excel en español guarda con punto y coma, y le agrega un BOM al
        // inicio: sin quitarlo la primera columna se llama «\xEF\xBB\xBFcedula»
        // y no se reconoce ninguna.
        $contenido = "\xEF\xBB\xBF" . "cedula;nombres;apellidos;rol;locales;email\n"
            . "0912345678;ANA;LOPEZ;Vigilante;{$this->local};a@e.com\n";

        $r = $this->servicio->importar($this->archivo($contenido));

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['creados']);
    }

    public function test_acepta_el_rol_en_minusculas_y_sin_tildes(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,vigilante,{$this->local},",
        ])));

        $this->assertSame(1, $r['creados']);
    }

    public function test_acepta_el_local_por_nombre(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv([
            '0912345678,ANA,LOPEZ,Vigilante,Garita Norte,',
        ])));

        $this->assertSame(1, $r['creados']);
        $u = users::where('usu_cedula', '0912345678')->first();
        $this->assertTrue(
            DB::table('user_has_institucion')->where('ui_usu_id', $u->id)->where('ui_ins_code', $this->local)->exists()
        );
    }

    public function test_varios_locales_separados_por_barra(): void
    {
        $otro = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita Sur', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local}|{$otro},",
        ])));

        $this->assertSame(1, $r['creados']);
        $u = users::where('usu_cedula', '0912345678')->first();
        $this->assertSame(2, DB::table('user_has_institucion')->where('ui_usu_id', $u->id)->count());
    }

    public function test_el_correo_puede_venir_vacio(): void
    {
        // 505 de los 879 usuarios reales lo tienen vacio, y la columna no es
        // unica en la practica: exigirlo haria inutilizable la carga.
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
        ])));

        $this->assertSame(1, $r['creados']);
        $this->assertSame('', users::where('usu_cedula', '0912345678')->value('usu_email'));
    }

    public function test_el_whatsapp_se_normaliza_con_codigo_de_pais(): void
    {
        $r = $this->servicio->importar($this->archivo($this->csv(
            ["0912345678,ANA,LOPEZ,Vigilante,{$this->local},,0987654321,si"],
            'cedula,nombres,apellidos,rol,locales,email,whatsapp,acepta_whatsapp'
        )));

        $this->assertSame(1, $r['creados']);

        $u = users::where('usu_cedula', '0912345678')->first();
        // Un numero local el gateway lo acepta sin quejarse y el mensaje nunca
        // llega: por eso se guarda con el 593.
        $this->assertSame('593987654321', $u->usu_whatsapp);
        $this->assertTrue((bool) $u->usu_acepta_whatsapp);
    }

    public function test_un_solo_nombre_llena_los_dos_campos_obligatorios(): void
    {
        // usu_nmb2 y usu_ape2 son NOT NULL. Cuando la persona no tiene segundo,
        // se repite el primero: es lo que hizo el ETL con 665 usuarios.
        $r = $this->servicio->importar($this->archivo($this->csv([
            "0912345678,ANA,LOPEZ,Vigilante,{$this->local},",
        ])));

        $this->assertSame(1, $r['creados']);

        $u = users::where('usu_cedula', '0912345678')->first();
        $this->assertSame('ANA', $u->usu_nmb1);
        $this->assertSame('ANA', $u->usu_nmb2);
        $this->assertSame('LOPEZ', $u->usu_ape1);
        $this->assertSame('LOPEZ', $u->usu_ape2);
    }

    public function test_el_modelo_de_ejemplo_se_puede_volver_a_importar(): void
    {
        // Si el modelo que se descarga no pasa su propia validacion, el operador
        // arranca con un error que no cometio.
        $modelo = $this->servicio->plantillaDeEjemplo();

        $r = $this->servicio->analizar($this->archivo($modelo));

        $this->assertSame([], $r['errores']);
        $this->assertCount(1, $r['filas']);
    }
}

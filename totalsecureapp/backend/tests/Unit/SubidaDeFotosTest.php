<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Administracion\Models\user_has_biometria;
use Modules\MobileApp\Models\users;
use Tests\TestCase;

/**
 * Subir una foto de evidencia de verdad, por el endpoint, con un archivo real.
 *
 * ⚠️ **Este es el test que faltaba.** `ApiRespondeTest` recorre las 55 rutas
 * contra 5xx, pero con datos sinteticos: **ninguna prueba subia un archivo**. Por
 * eso paso sin detectarse que el marcaje biometrico estuviera roto en produccion
 * -- PHP traia `upload_max_filesize = 2M` de fabrica y descartaba las fotos de
 * camara, de 3 a 5 MB, antes de que Laravel las viera.
 *
 * El limite de PHP en si no se puede comprobar desde PHPUnit: es configuracion
 * del entorno web, no del codigo. Lo que si se comprueba aca es el circuito
 * completo --el archivo llega, se guarda donde debe y despues se encuentra-- y,
 * en `ConfiguracionDeSubidaTest`, que el proyecto declare limites suficientes.
 */
class SubidaDeFotosTest extends TestCase
{
    use RefreshDatabase;

    private int $insCode;
    private string $token;
    private array $creados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->insCode = DB::table('organizacion_institucion')->insertGetId([
            'ins_descripcion' => 'Garita Norte', 'ins_estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'ins_code');

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '1111111111', 'usu_tipdoc' => 'CC', 'usu_password' => bcrypt('x'),
            'usu_nmbcom' => 'Guardia Uno', 'usu_ape1' => 'T', 'usu_ape2' => 'T',
            'usu_nmb1' => 'T', 'usu_nmb2' => 'T', 'usu_email' => 'g@e.com',
            'usu_state' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rol = DB::table('roles')->where('name', 'Vigilante')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $rol],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );
        DB::table('user_has_institucion')->updateOrInsert(
            ['ui_usu_id' => 1, 'ui_ins_code' => $this->insCode],
            ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
        );

        $this->token = users::find(1)->createToken('subida-test')->plainTextToken;
    }

    protected function tearDown(): void
    {
        foreach ($this->creados as $ruta) {
            File::delete($ruta);
        }

        parent::tearDown();
    }

    private function marcar(UploadedFile $foto)
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post('/api/biometria', [
                'file' => $foto,
                'latitud' => '-2.1890',
                'longitud' => '-79.8890',
                'is_entrada' => '1',
                'institucion' => $this->insCode,
            ]);
    }

    public function test_una_foto_se_sube_y_queda_registrada(): void
    {
        $this->marcar(UploadedFile::fake()->image('marcacion.jpg', 800, 600));

        $bio = user_has_biometria::first();

        $this->assertNotNull($bio, 'El marcaje no se registro.');
        $this->assertNotEmpty($bio->bio_image_name, 'El registro quedo sin foto.');
    }

    /**
     * La foto tiene que quedar donde el modelo la va a buscar despues. Es la
     * mitad que se rompia sola: el registro entraba y la imagen no aparecia.
     */
    public function test_la_foto_queda_donde_el_modelo_la_encuentra(): void
    {
        $this->marcar(UploadedFile::fake()->image('marcacion.jpg', 800, 600));

        $bio = user_has_biometria::first();
        $this->creados[] = public_path('images/' . $bio->bio_image_name);

        $this->assertNotNull(
            $bio->imagen_url,
            'La foto se subio pero el modelo no la encuentra en el disco.'
        );
    }

    /** Desde el arreglo, la columna guarda la ruta completa y no solo el nombre. */
    public function test_se_guarda_la_ruta_relativa_y_no_solo_el_nombre(): void
    {
        $this->marcar(UploadedFile::fake()->image('marcacion.jpg', 800, 600));

        $bio = user_has_biometria::first();
        $this->creados[] = public_path('images/' . $bio->bio_image_name);

        $this->assertStringContainsString('biometria/', $bio->bio_image_name);
        $this->assertStringContainsString('/', $bio->bio_image_name);
    }

    /**
     * Una foto del tamaño real de una camara de tablet.
     *
     * Es el caso que fallaba en produccion. Aca no puede reproducirse el limite
     * de PHP --PHPUnit no pasa por php-fpm-- pero si que nada del codigo la
     * rechace por tamaño.
     */
    public function test_una_foto_grande_de_camara_se_acepta(): void
    {
        $grande = UploadedFile::fake()->image('marcacion.jpg', 3000, 4000)->size(4096);

        $this->marcar($grande);

        $bio = user_has_biometria::first();

        $this->assertNotNull($bio, 'Una foto de 4 MB fue rechazada por el codigo.');
        if ($bio) {
            $this->creados[] = public_path('images/' . $bio->bio_image_name);
        }
    }

    public function test_sin_archivo_se_rechaza_con_un_motivo_claro(): void
    {
        $r = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/biometria', [
                'latitud' => '-2.1890',
                'longitud' => '-79.8890',
                'is_entrada' => '1',
                'institucion' => $this->insCode,
            ]);

        $r->assertJsonPath('success', false);
        $this->assertStringContainsString(
            'imagen',
            mb_strtolower(json_encode($r->json('errors'), JSON_UNESCAPED_UNICODE))
        );
    }
}

<?php

namespace Tests\Unit;

use App\Providers\AjustesServiceProvider;
use App\Support\Ajustes;
use App\Filament\Pages\Configuracion;
use Livewire\Livewire;
use Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los ajustes editables desde el panel.
 *
 * Lo que se prueba aca no es «guarda y lee»: es que **no se pueda dejar el
 * proyecto imposible de levantar** y que **la contraseña no quede en claro**.
 */
class AjustesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Ajustes::olvidar();
    }

    #[Test]
    public function guarda_y_devuelve_lo_guardado(): void
    {
        Ajustes::guardar(['mail.host' => 'smtp.ejemplo.com', 'mail.port' => '587']);

        $this->assertSame('smtp.ejemplo.com', Ajustes::get('mail.host'));
        $this->assertSame('587', Ajustes::get('mail.port'));
    }

    #[Test]
    public function la_contrasena_no_queda_en_claro_en_la_base(): void
    {
        // La razon de ser de `cf_cifrado`. Alguien con acceso de lectura a la
        // base --un respaldo, un volcado que se comparte para depurar-- no debe
        // encontrar ahi la clave del correo de la empresa.
        Ajustes::guardar(['mail.password' => 'clave-secreta-123']);

        $crudo = DB::table('configuracion')->where('cf_clave', 'mail.password')->first();

        $this->assertNotSame('clave-secreta-123', $crudo->cf_valor);
        $this->assertStringNotContainsString('clave-secreta-123', (string) $crudo->cf_valor);
        $this->assertTrue((bool) $crudo->cf_cifrado);

        // Y aun asi se lee bien.
        $this->assertSame('clave-secreta-123', Ajustes::get('mail.password'));
    }

    #[Test]
    public function un_valor_vacio_cae_al_defecto_en_vez_de_borrar_la_configuracion(): void
    {
        // Si no, alguien que borra el campo del formulario deja el correo
        // apuntando a un host vacio, en vez de volver al del `.env`.
        Ajustes::guardar(['mail.host' => '']);

        $this->assertSame('el-del-env', Ajustes::get('mail.host', 'el-del-env'));
    }

    #[Test]
    public function el_proveedor_pisa_la_configuracion_de_correo(): void
    {
        Ajustes::guardar([
            'mail.host'         => 'smtp.propio.com',
            'mail.from_address' => 'avisos@totalsecure.ec',
        ]);

        (new AjustesServiceProvider($this->app))->boot();

        $this->assertSame('smtp.propio.com', config('mail.mailers.smtp.host'));
        $this->assertSame('avisos@totalsecure.ec', config('mail.from.address'));
    }

    #[Test]
    public function lo_que_no_esta_guardado_no_se_pisa(): void
    {
        // Encender esto no tiene que cambiar nada mientras nadie edite el
        // formulario: el `.env` sigue mandando en todo lo que no se toco.
        config(['mail.mailers.smtp.username' => 'del-env']);

        Ajustes::guardar(['mail.host' => 'smtp.propio.com']);
        (new AjustesServiceProvider($this->app))->boot();

        $this->assertSame('del-env', config('mail.mailers.smtp.username'));
    }

    #[Test]
    public function sin_la_tabla_el_sistema_igual_arranca(): void
    {
        // ⚠️ **El test que justifica todo el try/catch.** El proveedor consulta
        // la base en `boot()`, y `boot()` corre tambien durante
        // `php artisan migrate` -- o sea antes de que la tabla exista. Sin la
        // proteccion, una instalacion limpia no podria correr la migracion que
        // crea la tabla que el proveedor necesita, y el proyecto quedaria
        // muerto sin forma de arreglarlo desde adentro.
        //
        // En este repo un error en el `boot()` de un proveedor ya tumbo la
        // aplicacion entera --panel y API-- dos veces durante la migracion.
        Schema::drop('configuracion');
        Ajustes::olvidar();

        $this->assertFalse(Ajustes::disponible());

        // Lo que importa: no lanza.
        (new AjustesServiceProvider($this->app))->boot();

        $this->assertTrue(true, 'El proveedor sobrevivió a que no exista la tabla.');
    }

    #[Test]
    public function un_valor_ilegible_no_rompe_nada_y_cae_al_defecto(): void
    {
        // Pasa si se rota `APP_KEY`. Degradarse es aceptable; reventar el panel
        // entero porque una contraseña vieja no se puede descifrar, no.
        DB::table('configuracion')->insert([
            'cf_clave' => 'mail.password', 'cf_valor' => 'esto-no-es-un-cifrado-valido',
            'cf_cifrado' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Ajustes::olvidar();

        $this->assertSame('defecto', Ajustes::get('mail.password', 'defecto'));
    }

    #[Test]
    public function las_claves_sensibles_estan_declaradas_como_cifradas(): void
    {
        // Si alguien agrega un campo de contraseña al formulario y olvida
        // sumarlo a CIFRADAS, se guardaria en claro sin que nada avise.
        $this->assertContains('mail.password', Ajustes::CIFRADAS);
        $this->assertContains('whatsapp.api_key', Ajustes::CIFRADAS);
    }

    #[Test]
    public function la_pagina_del_panel_guarda_y_cifra(): void
    {
        // El cableado completo: el formulario usa `mail__host` (con dos guiones
        // bajos) porque Filament interpreta el punto como acceso anidado, y la
        // pagina lo traduce a `mail.host` al guardar. Si esa traduccion se
        // rompiera, el formulario guardaria claves con nombres que nadie lee y
        // no pasaria nada visible.
        Session::put('usuPF', 'Administrador');

        Livewire::test(Configuracion::class)
            ->fillForm([
                'mail__host'         => 'smtp.propio.com',
                'mail__port'         => '587',
                'mail__from_address' => 'avisos@totalsecure.ec',
                'mail__password'     => 'secreta',
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $this->assertSame('smtp.propio.com', Ajustes::get('mail.host'));
        $this->assertSame('avisos@totalsecure.ec', Ajustes::get('mail.from_address'));
        $this->assertSame('secreta', Ajustes::get('mail.password'));

        // Y que la clave siga sin estar en claro cuando pasa por la pagina.
        $crudo = DB::table('configuracion')->where('cf_clave', 'mail.password')->value('cf_valor');
        $this->assertStringNotContainsString('secreta', (string) $crudo);
    }

    #[Test]
    public function al_abrir_muestra_lo_que_hoy_esta_valiendo(): void
    {
        // Si abriera en blanco, daria a entender que no hay nada configurado
        // cuando en realidad el `.env` esta mandando. Se veria como un sistema
        // sin configurar y alguien lo "arreglaria" pisando lo que funcionaba.
        Session::put('usuPF', 'Administrador');
        config(['mail.mailers.smtp.host' => 'host-del-env']);

        Livewire::test(Configuracion::class)
            ->assertFormSet(['mail__host' => 'host-del-env']);
    }
}

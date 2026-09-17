<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Modules\Acceso\Models\users;
use Tests\TestCase;

/**
 * El panel, pedido por HTTP y con la sesion iniciada.
 *
 * ⚠️ **Este es el test que faltaba, y su ausencia dejo pasar un 500 en toda la
 * aplicacion.**
 *
 * `PanelSeDibujaTest` prueba cada listado con `Livewire::test()`, que monta el
 * componente **aislado**: no dibuja el layout del panel. La campanita de
 * notificaciones vive en ese layout, y su conteo de no leidas usa el operador
 * JSON `->>` de PostgreSQL contra `notifications.data`. Esa columna se habia
 * creado como `text` --la migracion estandar de Laravel esta pensada para
 * MySQL-- y en PostgreSQL `->>` no existe para `text`:
 *
 *     SQLSTATE[42883]: operator does not exist: text ->> unknown
 *
 * Resultado: los 27 listados pasaban en verde y **cualquier pagina del panel
 * devolvia 500 apenas se iniciaba sesion**. El login y la seleccion de perfil
 * funcionaban, porque son vistas del modulo Acceso y estan fuera de Filament;
 * el error saltaba justo despues.
 *
 * Leccion de metodo, y ya es la segunda vez en este proyecto: **probar las
 * piezas por separado no alcanza. Hay que pedir la pagina de verdad.**
 */
class PanelConSesionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->updateOrInsert(['id' => 1], [
            'usu_cedula' => '0912345678', 'usu_tipdoc' => 'CC',
            'usu_password' => bcrypt('x'), 'usu_nmbcom' => 'Administrador Sistemas',
            'usu_ape1' => 'T', 'usu_ape2' => 'T', 'usu_nmb1' => 'T', 'usu_nmb2' => 'T',
            'usu_email' => 'admin@e.com', 'usu_state' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rol = DB::table('roles')->where('name', 'Administrador')->value('id');
        DB::table('user_has_roles')->updateOrInsert(
            ['user_id' => 1, 'role_id' => $rol],
            ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
        );

        Session::put('usuID', 1);
        Session::put('usuPF', 'Administrador');

        $this->actingAs(users::find(1));
    }

    /** El tablero entero, layout incluido. */
    public function test_el_tablero_se_dibuja_con_sesion_iniciada(): void
    {
        $this->get('/admin')->assertSuccessful();
    }

    /**
     * La campanita de notificaciones, que es lo que reventaba.
     *
     * Se dibuja en el layout de cualquier pagina del panel, asi que basta con
     * pedir una: si el conteo de no leidas falla, la respuesta es 500.
     */
    public function test_la_campanita_no_tumba_el_panel(): void
    {
        $this->get('/admin')->assertSuccessful();

        // Y con una notificacion guardada, que es cuando el conteo hace trabajo.
        \Filament\Notifications\Notification::make()
            ->title('Prueba')
            ->body('Cuerpo')
            ->sendToDatabase(users::find(1));

        $this->assertSame(1, users::find(1)->unreadNotifications()->count());

        $this->get('/admin')->assertSuccessful();
    }

    /**
     * El operador JSON contra `notifications.data`, que es la consulta exacta
     * que fallaba. Si la columna vuelve a ser `text`, esto revienta.
     */
    public function test_la_columna_data_admite_el_operador_json(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Prueba')
            ->sendToDatabase(users::find(1));

        $n = DB::table('notifications')
            ->whereRaw("data->>'format' = ?", ['filament'])
            ->count();

        $this->assertSame(1, $n);
    }

    /** Un par de paginas mas, para no fiarse de una sola. */
    public function test_los_listados_se_dibujan_por_http(): void
    {
        foreach (['/admin/personas-dentros', '/admin/novedads'] as $ruta) {
            $r = $this->get($ruta);

            $this->assertNotSame(
                500,
                $r->getStatusCode(),
                "La ruta {$ruta} devolvio 500."
            );
        }
    }

    /*
     * ---------------------------------------------------------------
     * La alarma de emergencia tiene que estar en TODAS las paginas.
     *
     * ⚠️ El widget del tablero solo se monta en el tablero, asi que una
     * emergencia que entraba mientras alguien trabajaba en Usuarios o en Turnos
     * **no sonaba**: no habia nada montado que la detectara. Los botones de
     * prueba funcionaban porque se pulsaban estando en el tablero, lo que hacia
     * parecer que todo andaba.
     * ---------------------------------------------------------------
     */
    public function test_la_alarma_esta_en_todas_las_paginas_del_panel(): void
    {
        foreach (['/admin', '/admin/users', '/admin/alertas'] as $ruta) {
            $html = $this->get($ruta)->getContent();

            $this->assertStringContainsString(
                'alarma()',
                $html,
                "La alarma de emergencia no esta montada en {$ruta}: una emergencia "
                . 'que entre mientras se trabaja en esa pantalla no sonaria.'
            );
        }
    }

    public function test_la_alarma_pide_activarse_una_vez(): void
    {
        // Sin esto quedaria muda para siempre: el navegador no reproduce audio
        // hasta que la persona interactua con la pagina.
        $this->assertStringContainsString(
            'Activar alarma de emergencias',
            $this->get('/admin')->getContent()
        );
    }
}

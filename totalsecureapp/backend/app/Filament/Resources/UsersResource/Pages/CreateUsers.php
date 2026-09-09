<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Resources\UsersResource;
use App\Services\UsuarioImportService;
use App\helpers;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUsers extends CreateRecord
{
    protected static string $resource = UsersResource::class;

    /** Se guarda para mostrarla una sola vez al terminar. */
    private string $claveTemporal = '';

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Volver a Usuarios')
                ->label('Volver')
                ->url(UsersResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_user'] = auth()->id();
        $data['updated_user'] = auth()->id();

        // ⚠️ **Antes esto era `Hash::make('123456')`**, la misma clave para
        // todos los usuarios creados desde el panel, y nadie se enteraba porque
        // el formulario no muestra nada de contraseñas. Ahora se genera una por
        // persona y se muestra una sola vez al terminar.
        //
        // Se usa el mismo generador que la carga masiva, que elige los
        // caracteres **por clase**: con `Str::random` la clave podia salir sin
        // ninguna minuscula y no cumplir las reglas del cambio de clave de la
        // app (8+ con mayuscula, minuscula y numero).
        $this->claveTemporal = app(UsuarioImportService::class)->claveTemporal();

        // ⚠️ **Hay que hashear aca, a mano.** Este recurso usa
        // `Modules\Acceso\Models\users`, que **no** tiene el evento `saving`
        // que hashea -- el que si lo tiene es el de `Modules\MobileApp`, y su
        // `boot()` en el de Acceso esta comentado. Asignar la clave en claro la
        // guardaba **en texto plano** en la base.
        $data['usu_password'] = Hash::make($this->claveTemporal);

        return $data;
    }

    /**
     * Completa las tres piezas que faltaban.
     *
     * Un usuario necesita cuatro cosas para poder entrar, y este formulario
     * creaba **solo la primera**:
     *
     *   1. La fila en `users` — la crea Filament.
     *   2. El rol en `user_has_roles`.
     *   3. Una gestion **abierta** en `user_has_gestions` (`ug_finish = false`).
     *      Sin ella el login responde «El usuario no tiene una gestion activa»:
     *      el usuario quedaba creado y sin poder entrar.
     *   4. El vinculo en `user_has_institucion`, sin el cual la app movil no
     *      puede registrar nada y la API del portal responde 403.
     *
     * Es la misma logica que `usuario:crear` y que la carga masiva
     * (`UsuarioImportService`).
     */
    protected function afterCreate(): void
    {
        $record = $this->record;
        $datos = $this->data;

        DB::transaction(function () use ($record, $datos) {
            $rolId = DB::table('roles')->where('name', $datos['rol'] ?? null)->value('id');

            if ($rolId !== null) {
                DB::table('user_has_roles')->updateOrInsert(
                    ['user_id' => $record->id, 'role_id' => $rolId],
                    ['ru_code' => (DB::table('user_has_roles')->max('ru_code') ?? 0) + 1]
                );
            }

            DB::table('user_has_gestions')->updateOrInsert(
                ['ug_user_id' => $record->id, 'ug_finish' => false],
                [
                    'ug_ingreso'      => now(),
                    'ug_state'        => 1,
                    'ug_created_user' => auth()->id() ?? $record->id,
                    'ug_created_at'   => now(),
                    'ug_updated_at'   => now(),
                ]
            );

            foreach ((array) ($datos['locales'] ?? []) as $insCode) {
                DB::table('user_has_institucion')->updateOrInsert(
                    ['ui_usu_id' => $record->id, 'ui_ins_code' => (int) $insCode],
                    ['ui_state' => 1, 'ui_created_at' => now(), 'ui_updated_at' => now()]
                );
            }
        });

        Notification::make()
            ->title('Usuario creado')
            ->body(
                "Entra con la cédula {$record->usu_cedula} y esta contraseña:\n\n"
                . "{$this->claveTemporal}\n\n"
                . 'Anótela ahora: no se vuelve a mostrar. Se cambia desde el listado de Usuarios.'
            )
            ->success()
            ->persistent()
            ->send();

        helpers::control_log_filament($record->toArray(), 'UserResource', 'Create', 'NOTICE', 'Creacion User');
    }
}

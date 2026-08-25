<?php

namespace App\Filament\Resources\UserHasInstitucionResource\Pages;

use App\Filament\Resources\UserHasInstitucionResource;
use App\helpers;
use Filament\Pages\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Administracion\Models\UserHasInstitucion;

class CreateUserHasInstitucion extends CreateRecord
{
    protected static string $resource = UserHasInstitucionResource::class;

    protected function getFormActions(): array {
        return [
            $this->getCreateFormAction(), // solo "Guardar"
        ];
    }

    protected function getActions(): array {
        return [
            Actions\Action::make('Volver a UsInst')
                ->label('Volver')
                ->url(UserHasInstitucionResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    protected function getTitle(): string {
        return 'Crear Usuario > Institucion';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ui_created_user'] = auth()->id();
        $data['ui_updated_user'] = auth()->id();
        return $data;
    }

    /**
     * Crea un vinculo por cada local seleccionado.
     *
     * El selector es multiple al crear, asi que ui_ins_code llega como arreglo.
     * Antes habia que repetir el alta una vez por local; un guardia que rota por
     * cuatro locales exigia cuatro pasadas por el formulario.
     *
     * Los que ya estaban vinculados se saltan en vez de fallar: reasignar a
     * alguien agregando un local no deberia obligar a acordarse de cuales ya
     * tenia.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $locales = (array) ($data['ui_ins_code'] ?? []);
        unset($data['ui_ins_code']);

        $creados = [];
        $repetidos = 0;

        foreach ($locales as $insCode) {
            $existente = UserHasInstitucion::where('ui_usu_id', $data['ui_usu_id'])
                ->where('ui_ins_code', $insCode)
                ->first();

            if ($existente) {
                $repetidos++;
                continue;
            }

            $creados[] = UserHasInstitucion::create($data + [
                'ui_ins_code' => $insCode,
                'ui_state'    => 1,
            ]);
        }

        if ($repetidos > 0) {
            Notification::make()
                ->title($repetidos . ' local(es) ya estaban asignados y se omitieron')
                ->warning()
                ->send();
        }

        if (empty($creados)) {
            // Filament espera un modelo de vuelta; si no se creo ninguno se
            // devuelve el vinculo existente para no romper el flujo.
            return UserHasInstitucion::where('ui_usu_id', $data['ui_usu_id'])
                ->where('ui_ins_code', $locales[0] ?? null)
                ->firstOrFail();
        }

        if (count($creados) > 1) {
            Notification::make()
                ->title(count($creados) . ' locales asignados')
                ->success()
                ->send();
        }

        return $creados[0];
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserHasInstitucionResource', 'Create','NOTICE', 'Creacion User Has Institucion');
    }

}

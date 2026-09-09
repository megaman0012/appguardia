<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Resources\UsersResource;
use App\helpers;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

use App\Support\NombreDePersona;

use Filament\Notifications\Notification;

class EditUsers extends EditRecord
{
    protected static string $resource = UsersResource::class;

    protected function getFormActions(): array {
        return [ $this->getSaveFormAction() ];
    }

    protected function getHeaderActions(): array {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('Volver a Usuarios')
                ->label('Volver')
                ->url(UsersResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    /**
     * Llena «Apellidos» y «Nombres» a partir de lo que hay guardado.
     *
     * ⚠️ En 749 de los 880 usuarios las columnas del desglose estan VACIAS y
     * solo queda el nombre completo, donde **no hay forma de saber donde
     * terminan los apellidos**. `descomponer()` asume el patron dominante --dos
     * apellidos primero-- y avisa que lo adivino; aca eso se convierte en una
     * advertencia visible, para que quien edite lo revise en vez de guardar una
     * suposicion como si fuera un dato. Los apellidos compuestos («DE LA TORRE»)
     * salen mal a proposito: es preferible que se vea y se corrija.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $partes = NombreDePersona::descomponer($data);

        $data['apellidos'] = $partes['apellidos'];
        $data['nombres']   = $partes['nombres'];

        if ($partes['adivinado']) {
            Notification::make()
                ->warning()
                ->title('Revisá cómo quedó separado el nombre')
                ->body('Este usuario venía con el nombre en una sola línea, así que se supuso que los dos primeros son los apellidos. Corregilo si no es así antes de guardar.')
                ->persistent()
                ->send();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_user'] = auth()->id();

        $data = array_merge($data, NombreDePersona::componer(
            $data['nombres'] ?? null,
            $data['apellidos'] ?? null,
        ));
        unset($data['nombres'], $data['apellidos']);

        // ⚠️ `usu_email` es NOT NULL, y los 505 usuarios sin correo estan
        // guardados como CADENA VACIA. Filament manda `null` cuando el campo
        // queda en blanco, y eso revienta con «null value violates not-null
        // constraint» al guardar. Se respeta la convencion que ya tiene la
        // tabla en vez de pelearse con ella.
        $data['usu_email'] = $data['usu_email'] ?? '';

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'UserResource', 'Edit','NOTICE', 'Editar User');
    }
}

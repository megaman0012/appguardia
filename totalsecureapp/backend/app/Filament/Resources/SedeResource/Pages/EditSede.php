<?php

namespace App\Filament\Resources\SedeResource\Pages;

use App\Filament\Resources\SedeResource;
use App\helpers;
use Filament\Forms\Components\Actions\Action;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSede extends EditRecord
{
    protected static string $resource = SedeResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('Volver a Sedes')
                ->label('Volver')
                ->url(SedeResource::getUrl())
                ->color('primary')
                ->icon('heroicon-o-arrow-left')
        ];
    }

    public function save(bool $shouldRedirect = true): void
    {
        try {
            parent::save($shouldRedirect);
        } catch (\Exception $e) {
            $this->notify('danger', $e->getMessage());
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['ps_updated_user'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        helpers::control_log_filament($record->toArray(), 'SedeResource', 'Edit','NOTICE', 'Editar Sede');
    }

}

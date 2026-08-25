<?php

namespace App\Filament\Resources\AvisoResource\Pages;

use App\Filament\Resources\AvisoResource;
use App\Filament\Widgets\EstadoWhatsapp;
use App\Services\Avisos\CanalWhatsApp;
use App\Services\Avisos\NumeroWhatsapp;
use App\Support\PerfilPanel;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAvisos extends ListRecords
{
    protected static string $resource = AvisoResource::class;

    protected function getTitle(): string
    {
        return 'Avisos enviados';
    }

    protected function getHeaderWidgets(): array
    {
        return [EstadoWhatsapp::class];
    }

    protected function getActions(): array
    {
        return [
            // Probar sin esperar a que falte un guardia: si la sesión se cayó,
            // conviene enterarse ahora y no a las tres de la mañana.
            Actions\Action::make('probarWhatsapp')
                ->label('Probar WhatsApp')
                ->icon('heroicon-o-chat-alt')
                ->color('secondary')
                ->modalHeading('Enviar un mensaje de prueba')
                ->form([
                    TextInput::make('numero')
                        ->label('Número')
                        ->required()
                        ->helperText('Con código de país: 593987654321. También acepta 0987654321.'),
                ])
                ->action(fn (array $data) => $this->probar($data['numero']))
                ->visible(fn () => PerfilPanel::puedeConfigurarSistema()),
        ];
    }

    private function probar(string $numero): void
    {
        $normalizado = NumeroWhatsapp::normalizar($numero);

        if (!NumeroWhatsapp::valido($normalizado)) {
            Notification::make()
                ->title('Número inválido')
                ->body('Quedó como "' . $normalizado . '", que no parece un móvil de Ecuador ni de Colombia.')
                ->danger()
                ->send();

            return;
        }

        $r = app(\App\Services\Avisos\EvolutionApi::class)->enviarTexto(
            $normalizado,
            "*Total Secure App*\nMensaje de prueba del canal de avisos."
        );

        if ($r['ok']) {
            Notification::make()
                ->title('Mensaje enviado')
                ->body('Salió al +' . $normalizado . '. Confirme que haya llegado al teléfono.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('No se pudo enviar')
            ->body($r['detalle'] ?? 'Sin detalle')
            ->danger()
            ->persistent()
            ->send();
    }
}

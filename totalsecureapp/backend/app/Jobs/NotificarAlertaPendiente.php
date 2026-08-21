<?php

namespace App\Jobs;

use App\Services\AlertaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Administracion\Models\Alertas;

class NotificarAlertaPendiente implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Alertas $alerta
    ) {}

    public function handle(): void
    {
        if ($this->alerta->al_estado_alerta !== 'pendiente') {
            return;
        }

        $service = app(AlertaService::class);
        $service->escalarAlerta(
            $this->alerta,
            $this->alerta->al_created_user,
            'Escalamiento automático por tiempo de espera excedido'
        );
    }
}

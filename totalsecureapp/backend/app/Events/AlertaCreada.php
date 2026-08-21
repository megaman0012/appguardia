<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Administracion\Models\Alertas;

class AlertaCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Alertas $alerta;

    public function __construct(Alertas $alerta)
    {
        $this->alerta = $alerta;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('alertas.institucion.' . $this->alerta->al_ins_code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'alerta.creada';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alerta->al_code,
            'prioridad' => $this->alerta->al_prioridad,
            'observacion' => $this->alerta->al_observacion,
            'usuario' => $this->alerta->usuario->name ?? 'Desconocido',
            'created_at' => $this->alerta->created_at->toISOString(),
        ];
    }
}

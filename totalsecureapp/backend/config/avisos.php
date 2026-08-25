<?php

return [

    /*
     * Canales por los que sale un aviso, en orden.
     *
     * La bitácora va siempre: es lo que permite responder después "¿a quién se
     * le avisó?". El push es el canal real de hoy, aunque sin Firebase todavía
     * no llegue a un Android físico.
     *
     * Para sumar WhatsApp o SMS: crear una clase que implemente
     * App\Services\Avisos\CanalDeAviso y agregarla acá. Nada más cambia.
     */
    'canales' => [
        App\Services\Avisos\CanalBitacora::class,
        App\Services\Avisos\CanalPush::class,
    ],

    'canal_log' => env('AVISOS_LOG_CHANNEL', 'stack'),

    /*
     * Interruptor general. Se apaga en los tests para no llenar el log ni
     * depender de la red al probar la lógica de cobertura.
     */
    'activos' => env('AVISOS_ACTIVOS', true),

];

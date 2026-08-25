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
        App\Services\Avisos\CanalPush::class,
        // Se auto-desactiva si falta la configuración de abajo, así que puede
        // quedar acá aunque todavía no exista el gateway.
        App\Services\Avisos\CanalWhatsApp::class,
    ],

    'canal_log' => env('AVISOS_LOG_CHANNEL', 'stack'),

    /*
     * Interruptor general. Se apaga en los tests para no llenar el log ni
     * depender de la red al probar la lógica de cobertura.
     */
    'activos' => env('AVISOS_ACTIVOS', true),

    /*
     * Gateway de WhatsApp (Evolution API).
     *
     * Corre APARTE del proyecto, en su propio contenedor. Mientras estas tres
     * variables estén vacías, el canal no intenta nada y cada aviso queda
     * registrado como "no se intentó".
     *
     * OJO: Evolution es un cliente NO oficial. El número puede ser bloqueado sin
     * aviso, así que conviene uno dedicado y nunca el número operativo de la
     * empresa. Nada del sistema depende de que esto funcione.
     */
    'whatsapp' => [
        'url'         => env('WHATSAPP_URL', ''),
        'instancia'   => env('WHATSAPP_INSTANCIA', ''),
        'api_key'     => env('WHATSAPP_API_KEY', ''),
        'timeout'     => (int) env('WHATSAPP_TIMEOUT', 8),

        // Para completar números cargados en formato local (0987654321).
        'codigo_pais' => env('WHATSAPP_CODIGO_PAIS', '593'),
    ],

];

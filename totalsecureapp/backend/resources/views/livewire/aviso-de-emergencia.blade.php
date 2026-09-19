{{--
    La alarma de emergencia, presente en TODAS las paginas del panel.

    Se inyecta por `PanelsRenderHook::BODY_END`. El widget del tablero solo
    existe en el tablero, y ahi estaba el problema original: una emergencia que
    entraba mientras se trabajaba en Usuarios o Turnos no tenia nada montado que
    la detectara.

    ⚠️ **`keep-alive` no es opcional.** Sin ese modificador Livewire descarta el
    95% de los sondeos con la pestaña en segundo plano -- que es donde vive el
    panel de un operador.

    El motor (audio, titulo parpadeante, oyente del evento) esta en
    `partials/alarma-de-emergencia-js`. Aca solo va la pantalla.
--}}
<div
    wire:poll.15s.keep-alive="comprobar"
    x-data="alarmaDeEmergencia()"
    x-init="iniciar()"
>
    {{-- ⚠️ Banner, no un boton chico en una esquina.

         El aviso anterior era un boton de 2 cm abajo a la derecha, y con el
         panel sin SPA reaparecia en cada carga de pagina: se volvio parte del
         paisaje y nadie lo pulsaba. Si el audio esta bloqueado, la alarma **no
         va a sonar**, y eso tiene que estorbar. --}}
    <div x-show="!listo" x-cloak
         style="position:fixed; left:0; right:0; bottom:0; z-index:50;
                background:#b91c1c; color:#fff; padding:.55rem 1rem;
                display:flex; align-items:center; justify-content:center; gap:1rem;
                font-size:.82rem; font-weight:600;
                box-shadow:0 -4px 12px rgba(0,0,0,.25);">
        <span>🔇 El sonido de emergencias está bloqueado por el navegador.</span>
        <button type="button" x-on:click="activar()"
                style="background:#fff; color:#b91c1c; border:0; border-radius:.4rem;
                       padding:.35rem .8rem; font-weight:700; cursor:pointer;">
            Activar y probar
        </button>
    </div>

    {{-- El cartel se dibuja aunque el audio este bloqueado: una emergencia que
         no suena tiene que verse. --}}
    <div x-show="sonando" x-cloak
         style="position:fixed; left:50%; top:1rem; transform:translateX(-50%);
                z-index:60; background:#b91c1c; color:#fff; border-radius:.75rem;
                padding:1rem 1.5rem; box-shadow:0 8px 24px rgba(0,0,0,.35);
                font-weight:700; text-align:center;">
        🚨 EMERGENCIA
        <div style="font-weight:500; font-size:.85rem; margin-top:.25rem;">
            <span x-text="$wire.abiertas"></span> sin atender ·
            <a href="{{ \App\Filament\Resources\AlertasResource::getUrl('index') }}"
               style="color:#fff; text-decoration:underline;">Ver alertas</a>
        </div>
    </div>
</div>

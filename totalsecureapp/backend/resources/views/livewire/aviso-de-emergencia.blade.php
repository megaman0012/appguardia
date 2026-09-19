{{--
    La alarma de emergencia, presente en TODAS las paginas del panel.

    Se inyecta por `PanelsRenderHook::BODY_END`, asi que sigue vigilando aunque
    se este en Usuarios, Turnos o Accesos -- que es donde se pasa el tiempo. El
    widget del tablero solo existe en el tablero, y ahi estaba el problema: una
    emergencia que entraba mientras se trabajaba en otra pantalla no sonaba.

    ⚠️ **`keep-alive` no es opcional aca.** Sin ese modificador, Livewire
    descarta el 95% de los sondeos cuando la pestana esta en segundo plano
    (`throttleWhile(theTabIsInTheBackground() && theDirectiveIsMissingKeepAlive)`
    en su bundle). A 15 s eso es una comprobacion cada cinco minutos de media, y
    el panel de un operador esta de fondo casi todo el tiempo -- que es
    exactamente cuando una emergencia importa.

    La logica de audio vive en `partials/alarma-de-emergencia-js`, compartida con
    el widget del tablero.
--}}
<div
    wire:poll.15s.keep-alive="comprobar"
    x-data="alarmaDeEmergencia()"
    x-init="escucharEmergencias()"
    x-on:emergencia-nueva.window="dispararAlarma()"
>
    {{-- Mientras el navegador no este dejando sonar se pide en cualquier
         pagina. `listo` sale del estado real del AudioContext, asi que este
         boton reaparece solo si el audio se suspende: antes se escondia para
         siempre en cuanto `localStorage` decia que ya se habia activado, y la
         alarma quedaba muda sin que nadie lo supiera. --}}
    <div x-show="!listo" x-cloak
         style="position:fixed; right:1rem; bottom:1rem; z-index:50;">
        <button type="button" x-on:click="activar()"
                style="background:#b91c1c; color:#fff; border:0; border-radius:.6rem;
                       padding:.6rem .9rem; font-weight:700; font-size:.8rem;
                       box-shadow:0 4px 12px rgba(0,0,0,.25); cursor:pointer;">
            🔔 Activar alarma de emergencias
        </button>
    </div>

    {{-- Aviso flotante cuando suena: el sonido solo no dice que pasa ni donde
         mirar. Se dibuja aunque el audio este bloqueado -- una emergencia que no
         suena tiene que verse. --}}
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
        <div x-show="!listo" x-cloak
             style="font-weight:500; font-size:.75rem; margin-top:.4rem;">
            El navegador tiene el sonido bloqueado ·
            <button type="button" x-on:click="activar()"
                    style="background:none; border:0; color:#fff; text-decoration:underline;
                           cursor:pointer; font:inherit;">Activar</button>
        </div>
    </div>
</div>

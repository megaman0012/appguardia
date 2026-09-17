{{--
    La alarma de emergencia, presente en TODAS las paginas del panel.

    Se inyecta por `PanelsRenderHook::BODY_END`, asi que sigue vigilando aunque
    se este en Usuarios, Turnos o Accesos -- que es donde se pasa el tiempo. El
    widget del tablero solo existe en el tablero, y ahi estaba el problema: una
    emergencia que entraba mientras se trabajaba en otra pantalla no sonaba.
--}}
<div
    wire:poll.15s="comprobar"
    x-data="{
        audio: null,
        listo: false,
        sonando: false,

        init() {
            if (localStorage.getItem('alertas-sonido') === '1') this.preparar();

            // Los dos caminos: el API explicito de Livewire y el evento del DOM.
            // Con uno solo ya fallo una vez.
            document.addEventListener('livewire:init', () => {
                window.Livewire.on('emergencia-nueva', () => this.alarma());
            });
            if (window.Livewire) {
                window.Livewire.on('emergencia-nueva', () => this.alarma());
            }
        },

        preparar() {
            try {
                this.audio = new (window.AudioContext || window.webkitAudioContext)();
                this.listo = true;
                localStorage.setItem('alertas-sonido', '1');
            } catch (e) { console.warn('audio', e); }
        },

        activar() { this.preparar(); this.alarma(); },

        /*
         * Sirena de emergencia, no un pitido.
         *
         * Un barrido continuo de frecuencia entre 600 y 1200 Hz, repetido seis
         * veces durante unos 5 segundos: es el patron que se reconoce como
         * alarma a traves de una puerta y con ruido de oficina. Los pitines
         * cortos anteriores se confundian con una notificacion de correo.
         */
        alarma() {
            if (!this.audio) return;
            if (this.audio.state === 'suspended') this.audio.resume();

            this.sonando = true;
            setTimeout(() => { this.sonando = false; }, 5200);

            const t0 = this.audio.currentTime;
            const ciclos = 6;
            const dur = 0.8;

            for (let i = 0; i < ciclos; i++) {
                const t = t0 + i * dur;

                const osc = this.audio.createOscillator();
                const vol = this.audio.createGain();

                osc.type = 'sawtooth';
                // El barrido: sube y baja, como una sirena de verdad.
                osc.frequency.setValueAtTime(600, t);
                osc.frequency.linearRampToValueAtTime(1200, t + dur / 2);
                osc.frequency.linearRampToValueAtTime(600, t + dur);

                vol.gain.setValueAtTime(0.0001, t);
                vol.gain.exponentialRampToValueAtTime(0.85, t + 0.05);
                vol.gain.setValueAtTime(0.85, t + dur - 0.1);
                vol.gain.exponentialRampToValueAtTime(0.0001, t + dur);

                osc.connect(vol).connect(this.audio.destination);
                osc.start(t);
                osc.stop(t + dur);
            }
        },
    }"
    x-on:emergencia-nueva.window="alarma()"
>
    {{-- Mientras el sonido no este activado se pide una vez, en cualquier
         pagina: el navegador no deja reproducir audio sin una interaccion
         previa, y si no se avisa, la alarma queda muda sin que nadie lo sepa. --}}
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
         mirar. --}}
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

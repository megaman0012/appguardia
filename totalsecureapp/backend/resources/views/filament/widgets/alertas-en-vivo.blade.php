{{--
    Las emergencias abiertas, con sonido.

    ⚠️ Se usa `<x-filament::widget>` + `<x-filament::card>`, que es lo que ya usa
    el widget de WhatsApp de este mismo proyecto. **`<x-filament::section>` NO
    existe en esta version de Filament**, y esa fue la razon de que el boton de
    sonido no apareciera: el componente fallaba y se llevaba por delante todo su
    contenido, incluido el boton.

    Tampoco se usan clases Tailwind propias: este proyecto no compila CSS, asi
    que solo existe lo que viene en la hoja precompilada de Filament.
--}}
@php($alertas = $this->getAlertas())

<x-filament::widget>
    <div
        x-data="{
            audio: null,
            sonidoListo: false,

            init() {
                // Si ya se activo antes en este navegador se reactiva solo: de
                // lo contrario habia que pulsarlo en cada carga de pagina, y
                // bastaba con navegar a otra pantalla para quedarse en silencio
                // sin notarlo.
                if (localStorage.getItem('alertas-sonido') === '1') this.preparar();

                /*
                 * ⚠️ Se escucha con `Livewire.on()`, el API explicito, y no solo
                 * con `x-on:...window`.
                 *
                 * El servidor despachaba el evento correctamente --comprobado--
                 * y aun asi no sonaba: el atajo de Alpine depende de que el
                 * evento llegue como evento del DOM en `window`, y eso no
                 * ocurria de forma fiable dentro de un widget que Livewire
                 * vuelve a dibujar en cada refresco. `Livewire.on` lo recibe
                 * siempre.
                 *
                 * Se deja tambien el `x-on:` de respaldo: si uno de los dos
                 * camina, suena.
                 */
                document.addEventListener('livewire:init', () => {
                    window.Livewire.on('emergencia-nueva', () => this.pitar());
                });

                if (window.Livewire) {
                    window.Livewire.on('emergencia-nueva', () => this.pitar());
                }
            },

            preparar() {
                try {
                    this.audio = new (window.AudioContext || window.webkitAudioContext)();
                    this.sonidoListo = true;
                    localStorage.setItem('alertas-sonido', '1');
                } catch (e) {
                    console.warn('No se pudo iniciar el audio', e);
                }
            },

            activar() { this.preparar(); this.pitar(); },

            /*
             * El sonido se genera, no se descarga: no hay que desplegar ningun
             * archivo ni depende de que una ruta exista. Tres tonos alternados,
             * que se reconocen como alarma y no como notificacion de correo.
             */
            pitar() {
                if (!this.audio) return;
                if (this.audio.state === 'suspended') this.audio.resume();

                const t0 = this.audio.currentTime;

                // Cinco tonos y volumen alto: una alarma tiene que oirse en una
                // oficina con ruido, no ser una notificacion discreta. El
                // usuario reporto que al volumen anterior (0.3) apenas se oia.
                [0, 0.35, 0.7, 1.05, 1.4].forEach((retraso, i) => {
                    const osc = this.audio.createOscillator();
                    const vol = this.audio.createGain();

                    osc.type = 'square';
                    osc.frequency.value = i % 2 === 0 ? 880 : 660;

                    vol.gain.setValueAtTime(0.0001, t0 + retraso);
                    vol.gain.exponentialRampToValueAtTime(0.9, t0 + retraso + 0.02);
                    vol.gain.exponentialRampToValueAtTime(0.0001, t0 + retraso + 0.28);

                    osc.connect(vol).connect(this.audio.destination);
                    osc.start(t0 + retraso);
                    osc.stop(t0 + retraso + 0.3);
                });
            },
        }"
        x-on:emergencia-nueva.window="pitar()"
        wire:poll.15s="comprobar"
    >
        <x-filament::card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-base font-bold text-danger-600">
                        @if (count($alertas) > 0)
                            🚨 {{ count($alertas) === 1
                                ? 'Emergencia sin atender'
                                : count($alertas) . ' emergencias sin atender' }}
                        @else
                            Emergencias
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <span x-show="!sonidoListo">
                            Pulse «Activar sonido» una vez: el navegador no deja sonar una alarma sin eso.
                        </span>
                        <span x-show="sonidoListo" x-cloak>
                            Sonido activo en este equipo.
                            @if (count($alertas) === 0) Sin emergencias abiertas. @endif
                        </span>
                    </p>
                </div>

                {{-- El boton va SIEMPRE, haya o no alertas: si solo apareciera
                     con una emergencia en curso, no habria forma de dejar el
                     sonido listo de antemano ni de comprobar que se oye. --}}
                <div class="flex gap-2">
                    <x-filament::button size="sm" color="danger"
                                        x-show="!sonidoListo" x-on:click="activar()">
                        🔔 Activar sonido
                    </x-filament::button>

                    <x-filament::button size="sm" color="gray"
                                        x-show="sonidoListo" x-cloak x-on:click="pitar()">
                        Probar sonido
                    </x-filament::button>

                    {{-- Prueba el circuito COMPLETO: el servidor despacha el
                         aviso igual que cuando entra una emergencia real. Si
                         este suena, la alarma de verdad va a sonar. --}}
                    <x-filament::button size="sm" color="warning"
                                        x-show="sonidoListo" x-cloak
                                        wire:click="probarAviso">
                        Probar aviso completo
                    </x-filament::button>
                </div>
            </div>

            @if (count($alertas) > 0)
                <div class="mt-4 space-y-3">
                    @foreach ($alertas as $a)
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-semibold text-gray-950 dark:text-white">
                                    {{ $a['local'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $a['hace'] }}
                                </span>
                            </div>

                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium">{{ $a['guardia'] }}</span>
                                @if ($a['motivo']) — {{ $a['motivo'] }} @endif
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-4 text-sm">
                                <x-filament::badge color="danger">{{ $a['prioridad'] }}</x-filament::badge>

                                @if ($a['mapa'])
                                    <a href="{{ $a['mapa'] }}" target="_blank" rel="noopener"
                                       class="font-medium text-primary-600 hover:underline">Ver ubicación</a>
                                @else
                                    <span class="text-gray-400">Sin ubicación</span>
                                @endif

                                <a href="{{ \App\Filament\Resources\AlertasResource::getUrl('index') }}"
                                   class="font-medium text-primary-600 hover:underline">Atender</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::card>
    </div>
</x-filament::widget>

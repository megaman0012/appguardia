{{--
    Las emergencias abiertas, con sonido.

    ⚠️ Se usa `<x-filament::section>` y las clases que YA trae Filament, no
    Tailwind propio. Este proyecto **no compila CSS**: no hay tema propio ni
    `public/build`, asi que Filament sirve su hoja precompilada y cualquier clase
    que no este ahi dentro simplemente no existe. La version anterior usaba
    `bg-danger-50`, `border-2` y `dark:bg-danger-950/30`, y por eso el panel
    salia sin formato.
--}}
@php($alertas = $this->getAlertas())

<div wire:poll.15s="comprobar">
    <div
        x-data="{
            audio: null,
            sonidoListo: false,

            init() {
                // Si ya lo activo antes en este navegador, se reactiva solo: el
                // navegador permite el audio mientras haya habido alguna
                // interaccion previa en la pestaña.
                if (localStorage.getItem('alertas-sonido') === '1') {
                    this.preparar();
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

            activar() {
                this.preparar();
                this.pitar();
            },

            /*
             * El sonido se genera, no se descarga: asi no hay que desplegar
             * ningun archivo ni depende de que una ruta exista. Tres tonos
             * alternados, que se reconocen como alarma y no como notificacion.
             */
            pitar() {
                if (!this.audio) return;
                if (this.audio.state === 'suspended') this.audio.resume();

                const t0 = this.audio.currentTime;

                [0, 0.35, 0.7].forEach((retraso, i) => {
                    const osc = this.audio.createOscillator();
                    const vol = this.audio.createGain();

                    osc.type = 'square';
                    osc.frequency.value = i % 2 === 0 ? 880 : 660;

                    vol.gain.setValueAtTime(0.0001, t0 + retraso);
                    vol.gain.exponentialRampToValueAtTime(0.3, t0 + retraso + 0.02);
                    vol.gain.exponentialRampToValueAtTime(0.0001, t0 + retraso + 0.28);

                    osc.connect(vol).connect(this.audio.destination);
                    osc.start(t0 + retraso);
                    osc.stop(t0 + retraso + 0.3);
                });
            },
        }"
        x-on:emergencia-nueva.window="pitar()"
    >
        @if (count($alertas) > 0)
            <x-filament::section>
                <x-slot name="heading">
                    🚨 {{ count($alertas) === 1
                        ? 'Emergencia sin atender'
                        : count($alertas) . ' emergencias sin atender' }}
                </x-slot>

                <x-slot name="description">
                    <span x-show="!sonidoListo">
                        El navegador no deja sonar una alarma hasta que se pulsa aquí una vez.
                    </span>
                    <span x-show="sonidoListo" x-cloak>Sonido activo en este equipo.</span>
                </x-slot>

                <x-slot name="headerEnd">
                    <x-filament::button size="sm" color="danger" x-show="!sonidoListo"
                                        x-on:click="activar()">
                        Activar sonido
                    </x-filament::button>

                    {{-- Probar sin esperar a una emergencia real: si no se oye,
                         el volumen esta bajo y mas vale saberlo ahora. --}}
                    <x-filament::button size="sm" color="gray" x-show="sonidoListo" x-cloak
                                        x-on:click="pitar()">
                        Probar sonido
                    </x-filament::button>
                </x-slot>

                <div class="fi-ta-ctn divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($alertas as $a)
                        <div class="py-3">
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
                                <x-filament::badge color="danger">
                                    {{ $a['prioridad'] }}
                                </x-filament::badge>

                                @if ($a['mapa'])
                                    <x-filament::link href="{{ $a['mapa'] }}" target="_blank">
                                        Ver ubicación
                                    </x-filament::link>
                                @else
                                    <span class="text-gray-400">Sin ubicación</span>
                                @endif

                                <x-filament::link
                                    href="{{ \App\Filament\Resources\AlertasResource::getUrl('index') }}">
                                    Atender
                                </x-filament::link>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</div>

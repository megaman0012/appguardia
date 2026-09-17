{{--
    Las emergencias abiertas, con sonido.

    El widget se dibuja solo cuando hay alguna: un panel rojo permanente se
    vuelve parte del decorado y deja de mirarse a los dos dias.
--}}
@php($alertas = $this->getAlertas())

<div
    wire:poll.15s
    x-data="alertasEnVivo(@js(array_column($alertas, 'code')))"
    class="fi-wi"
>
    @if (count($alertas) > 0)
        <div class="rounded-xl border-2 border-danger-500 bg-danger-50 p-4 dark:bg-danger-950/30">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-base font-bold text-danger-700 dark:text-danger-400">
                    <span class="text-xl">🚨</span>
                    {{ count($alertas) === 1 ? 'Emergencia sin atender' : count($alertas) . ' emergencias sin atender' }}
                </h2>

                {{-- El navegador bloquea el audio hasta que la persona interactua
                     con la pagina al menos una vez. Sin este boton, el aviso
                     seria mudo sin que nadie sepa por que. --}}
                <button
                    type="button"
                    x-show="!sonidoListo"
                    x-on:click="activarSonido()"
                    class="rounded-lg bg-danger-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-danger-700"
                >
                    Activar sonido
                </button>

                <span x-show="sonidoListo" class="text-xs text-danger-600 dark:text-danger-400">
                    Sonido activo
                </span>
            </div>

            <div class="space-y-2">
                @foreach ($alertas as $a)
                    {{-- `data-alerta` es lo que el script compara para saber
                         cual es nueva y tiene que sonar. --}}
                    <div data-alerta="{{ $a['code'] }}"
                         class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-semibold text-gray-950 dark:text-white">
                                {{ $a['local'] }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $a['hace'] }}</span>
                        </div>

                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            <span class="font-medium">{{ $a['guardia'] }}</span>
                            @if ($a['motivo'])
                                — {{ $a['motivo'] }}
                            @endif
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                            <span class="rounded-full bg-danger-100 px-2 py-0.5 font-semibold uppercase text-danger-700 dark:bg-danger-900 dark:text-danger-300">
                                {{ $a['prioridad'] }}
                            </span>

                            @if ($a['mapa'])
                                <a href="{{ $a['mapa'] }}" target="_blank" rel="noopener"
                                   class="font-medium text-primary-600 hover:underline">
                                    Ver ubicación
                                </a>
                            @else
                                <span class="text-gray-400">Sin ubicación</span>
                            @endif

                            <a href="{{ \App\Filament\Resources\AlertasResource::getUrl('index') }}"
                               class="font-medium text-primary-600 hover:underline">
                                Atender
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            function alertasEnVivo(codigosIniciales) {
                return {
                    vistos: new Set(codigosIniciales),
                    sonidoListo: false,
                    audio: null,

                    init() {
                        /*
                         * Los codigos que ya estaban al cargar la pagina NO
                         * suenan: si no, entrar al panel con una alerta vieja
                         * abierta dispararia la alarma cada vez.
                         */
                        this.$watch('$el', () => {});

                        // Cada refresco de Livewire vuelve a montar el nodo, asi
                        // que la comparacion se hace contra lo que ya se vio.
                        document.addEventListener('livewire:navigated', () => this.revisar());
                        Livewire.hook('morph.updated', () => this.revisar());
                    },

                    activarSonido() {
                        try {
                            this.audio = new (window.AudioContext || window.webkitAudioContext)();
                            // Un pitido corto al activar, que sirve de prueba: si
                            // no se oye, el volumen esta bajo y mas vale saberlo
                            // ahora que durante una emergencia.
                            this.pitar();
                            this.sonidoListo = true;
                        } catch (e) {
                            console.warn('No se pudo iniciar el audio', e);
                        }
                    },

                    revisar() {
                        const actuales = Array.from(
                            this.$el.querySelectorAll('[data-alerta]')
                        ).map((n) => Number(n.dataset.alerta));

                        const nuevas = actuales.filter((c) => !this.vistos.has(c));

                        nuevas.forEach((c) => this.vistos.add(c));

                        if (nuevas.length > 0 && this.sonidoListo) {
                            this.pitar();
                        }
                    },

                    /*
                     * El sonido se genera, no se descarga: asi no hay que
                     * desplegar un archivo ni depender de que la ruta exista.
                     * Dos tonos alternados, que es lo que se reconoce como
                     * alarma y no como notificacion.
                     */
                    pitar() {
                        if (!this.audio) return;

                        const ahora = this.audio.currentTime;

                        [0, 0.35, 0.7].forEach((retraso, i) => {
                            const osc = this.audio.createOscillator();
                            const vol = this.audio.createGain();

                            osc.type = 'square';
                            osc.frequency.value = i % 2 === 0 ? 880 : 660;

                            vol.gain.setValueAtTime(0.0001, ahora + retraso);
                            vol.gain.exponentialRampToValueAtTime(0.25, ahora + retraso + 0.02);
                            vol.gain.exponentialRampToValueAtTime(0.0001, ahora + retraso + 0.28);

                            osc.connect(vol).connect(this.audio.destination);
                            osc.start(ahora + retraso);
                            osc.stop(ahora + retraso + 0.3);
                        });
                    },
                };
            }
        </script>
    @endpush
@endonce

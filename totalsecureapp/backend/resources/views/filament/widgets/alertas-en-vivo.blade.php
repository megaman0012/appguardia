{{--
    Las emergencias abiertas, con sonido.

    El widget se dibuja solo cuando hay alguna: un panel rojo permanente se
    vuelve parte del decorado y deja de mirarse a los dos dias.

    Todo el JavaScript va INLINE en el `x-data`, no en un `@push('scripts')`.
    Motivo: el widget aparece por primera vez dentro de un refresco de Livewire
    --justo cuando entra la emergencia-- y en ese momento los stacks del layout
    ya se emitieron, asi que un script empujado ahi nunca llega. Era el unico
    momento en que hacia falta.
--}}
@php($alertas = $this->getAlertas())

<div wire:poll.15s="comprobar">
    <div
        x-data="{
            audio: null,
            sonidoListo: false,

            activar() {
                try {
                    this.audio = new (window.AudioContext || window.webkitAudioContext)();
                    this.sonidoListo = true;
                    // Un pitido al activar sirve de prueba: si no se oye, el
                    // volumen esta bajo, y mas vale saberlo ahora que durante una
                    // emergencia.
                    this.pitar();
                } catch (e) {
                    console.warn('No se pudo iniciar el audio', e);
                }
            },

            /*
             * El sonido se genera, no se descarga: asi no hay que desplegar
             * ningun archivo ni depende de que una ruta exista. Tres tonos
             * alternados, que es lo que se reconoce como alarma y no como
             * notificacion de correo.
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
        {{-- El servidor avisa cuando hay una emergencia que no estaba. --}}
        x-on:emergencia-nueva.window="pitar()"
    >
        @if (count($alertas) > 0)
            <div class="rounded-xl border-2 border-danger-500 bg-danger-50 p-4 dark:bg-danger-950/30">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-base font-bold text-danger-700 dark:text-danger-400">
                        <span class="text-xl">🚨</span>
                        {{ count($alertas) === 1
                            ? 'Emergencia sin atender'
                            : count($alertas) . ' emergencias sin atender' }}
                    </h2>

                    {{-- El navegador bloquea el audio hasta que la persona
                         interactua con la pagina al menos una vez. Sin este
                         boton el aviso seria mudo y nadie sabria por que. --}}
                    <button type="button" x-show="!sonidoListo" x-on:click="activar()"
                            class="rounded-lg bg-danger-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-danger-700">
                        🔔 Activar sonido
                    </button>
                    <span x-show="sonidoListo" x-cloak
                          class="text-xs font-medium text-danger-600 dark:text-danger-400">
                        Sonido activo
                    </span>
                </div>

                <div class="space-y-2">
                    @foreach ($alertas as $a)
                        <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $a['local'] }}</span>
                                <span class="text-xs text-gray-500">{{ $a['hace'] }}</span>
                            </div>

                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium">{{ $a['guardia'] }}</span>
                                @if ($a['motivo']) — {{ $a['motivo'] }} @endif
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                <span class="rounded-full bg-danger-100 px-2 py-0.5 font-semibold uppercase text-danger-700 dark:bg-danger-900 dark:text-danger-300">
                                    {{ $a['prioridad'] }}
                                </span>

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
            </div>
        @endif
    </div>
</div>

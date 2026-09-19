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
    {{-- La logica de audio es la compartida de
         `partials/alarma-de-emergencia-js`: estaba copiada aca y en el
         componente global, y por eso el mismo fallo volvio cuatro veces.

         ⚠️ `keep-alive` en el sondeo: sin el, Livewire descarta el 95% de las
         comprobaciones con la pestana en segundo plano. --}}
    <div
        x-data="alarmaDeEmergencia()"
        x-init="escucharEmergencias()"
        x-on:emergencia-nueva.window="dispararAlarma()"
        wire:poll.15s.keep-alive="comprobar"
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
                        <span x-show="!listo">
                            El navegador tiene el sonido bloqueado. Pulse «Activar sonido»: sin un clic previo no deja sonar una alarma.
                        </span>
                        <span x-show="listo" x-cloak>
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
                                        x-show="!listo" x-on:click="activar()">
                        🔔 Activar sonido
                    </x-filament::button>

                    <x-filament::button size="sm" color="gray"
                                        x-show="listo" x-cloak x-on:click="sirena()">
                        Probar sonido
                    </x-filament::button>

                    {{-- Prueba el circuito COMPLETO: el servidor despacha el
                         aviso igual que cuando entra una emergencia real. Si
                         este suena, la alarma de verdad va a sonar. --}}
                    <x-filament::button size="sm" color="warning"
                                        x-show="listo" x-cloak
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

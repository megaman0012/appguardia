@php($d = $this->getDatos())

<x-filament::widget>
    <x-filament::card>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Canal de WhatsApp</p>

                <p @class([
                    'text-lg font-bold',
                    'text-success-600' => $d['color'] === 'success',
                    'text-warning-600' => $d['color'] === 'warning',
                    'text-danger-600' => $d['color'] === 'danger',
                ])>
                    {{ $d['etiqueta'] }}
                </p>

                @if ($d['estado']['numero'])
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Número conectado: <span class="font-mono">+{{ $d['estado']['numero'] }}</span>
                    </p>
                @endif

                @if ($d['estado']['detalle'])
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $d['estado']['detalle'] }}
                    </p>
                @endif
            </div>

            <div class="text-sm text-right">
                <p class="text-gray-500 dark:text-gray-400">Últimas 24 horas</p>
                <p class="text-success-600 font-semibold">{{ $d['enviados'] }} enviados</p>
                @if ($d['fallidos'])
                    <p class="text-danger-600 font-semibold">{{ $d['fallidos'] }} fallaron</p>
                @endif
                @if ($d['omitidos'])
                    <p class="text-gray-500">{{ $d['omitidos'] }} sin intentar</p>
                @endif
            </div>
        </div>

        @if ($d['estado']['estado'] !== 'abierta')
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Los avisos siguen apareciendo en la app aunque WhatsApp esté caído:
                el guardia los ve en «Turnos disponibles».
            </p>
        @endif
    </x-filament::card>
</x-filament::widget>

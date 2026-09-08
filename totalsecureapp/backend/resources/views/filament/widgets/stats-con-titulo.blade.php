{{--
    Igual que el stats-overview de Filament, pero con un titulo encima.

    Filament 2 no soporta encabezado en StatsOverviewWidget: su vista pinta las
    tarjetas y nada mas. Con dos filas de cuatro tarjetas cada una, sin titulos
    quedaban ocho numeros seguidos sin decir cual mide operacion y cual mide
    configuracion -- y son cosas distintas: una se mira todos los dias y la otra
    se mira hasta que queda en cero.

    El titulo sale de getEncabezado() en el widget.
--}}
<x-filament::widget class="filament-stats-overview-widget">
    <div
        {!! ($pollingInterval = $this->getPollingInterval()) ? "wire:poll.{$pollingInterval}" : '' !!}
    >
        @if (filled($encabezado = $this->getEncabezado()))
            <h2
                @class([
                    'mb-3 text-base font-bold tracking-tight',
                    'dark:text-white' => config('filament.dark_mode'),
                ])
            >
                {{ $encabezado }}
            </h2>

            @if (filled($ayuda = $this->getAyuda()))
                <p
                    @class([
                        '-mt-2 mb-3 text-sm text-gray-500',
                        'dark:text-gray-400' => config('filament.dark_mode'),
                    ])
                >
                    {{ $ayuda }}
                </p>
            @endif
        @endif

        <x-filament::stats :columns="$this->getColumns()">
            @foreach ($this->getCachedCards() as $card)
                {{ $card }}
            @endforeach
        </x-filament::stats>
    </div>
</x-filament::widget>

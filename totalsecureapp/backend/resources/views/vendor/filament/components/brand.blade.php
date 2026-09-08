{{--
    Marca del panel: logo + nombre.

    Sobrescribe `filament::components.brand`, que solo pintaba el texto de
    `config('filament.brand')`. El logo aparece en la barra lateral y en la
    pantalla de ingreso, que son los dos lugares donde Filament 2 usa este
    componente.

    El PNG es el escudo **sin fondo** (ver «La marca de la app» en AGENTS.md),
    asi que se ve igual en tema claro y oscuro sin necesidad de dos archivos.
--}}
<div class="filament-brand flex items-center gap-2">
    <img
        src="{{ asset('images/logo.png') }}"
        alt="{{ config('filament.brand') }}"
        class="h-8 w-8 shrink-0"
    >

    @if (filled($brand = config('filament.brand')))
        <span
            @class([
                'text-xl font-bold leading-5 tracking-tight',
                'dark:text-white' => config('filament.dark_mode'),
            ])
        >
            {{ $brand }}
        </span>
    @endif
</div>

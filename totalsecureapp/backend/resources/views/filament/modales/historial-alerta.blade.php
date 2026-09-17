{{--
    La linea de tiempo de una emergencia.

    Se usan las clases que ya trae Filament: este proyecto no compila CSS propio,
    asi que cualquier utilidad de Tailwind que no este en su hoja precompilada no
    existe.
--}}
<div class="space-y-3">
    @if ($cierre)
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Cómo se resolvió</p>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $cierre }}</p>
        </div>
    @endif

    @forelse ($filas as $f)
        <div class="flex gap-3">
            <div class="shrink-0">
                <x-filament::badge>{{ $f->ah_accion }}</x-filament::badge>
            </div>
            <div class="min-w-0">
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $f->ah_descripcion }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $f->usu_nmbcom ?? 'Sistema' }} ·
                    {{ \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i') }}
                </p>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">Esta alerta no tiene historial registrado.</p>
    @endforelse
</div>

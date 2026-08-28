{{--
    Grilla semanal del cuadrante.

    Los colores de estado van en `style` y no en clases de Tailwind a propósito:
    Filament 2 sirve un CSS ya compilado, y una clase que no use ninguna de sus
    vistas puede no existir en ese archivo. Con estilos en línea la celda se ve
    igual en cualquier instalación.
--}}
@php
    $colores = [
        'conflicto'  => ['fondo' => '#fee2e2', 'borde' => '#ef4444', 'texto' => '#991b1b'],
        'sin_cubrir' => ['fondo' => '#ffedd5', 'borde' => '#f97316', 'texto' => '#9a3412'],
        'descanso'   => ['fondo' => '#fef9c3', 'borde' => '#eab308', 'texto' => '#854d0e'],
        'ok'         => ['fondo' => '#f0fdf4', 'borde' => '#86efac', 'texto' => '#166534'],
    ];
    $etiquetas = [
        'conflicto'  => 'En dos lugares a la vez',
        'sin_cubrir' => 'Sin guardia asignado',
        'descanso'   => 'Menos de 8 h de descanso',
    ];
    $resumen = $grilla['resumen'];
@endphp

<x-filament::page>
    <x-filament::card>
        <div class="flex flex-wrap gap-6 text-sm">
            <div>
                <p class="text-gray-500 dark:text-gray-400">Franjas</p>
                <p class="text-xl font-bold">{{ $resumen['franjas'] }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Horas semanales</p>
                <p class="text-xl font-bold">{{ $resumen['horas'] }} h</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Sin cubrir</p>
                <p class="text-xl font-bold" style="color: {{ $resumen['sin_cubrir'] ? '#9a3412' : '#166534' }}">
                    {{ $resumen['sin_cubrir'] }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Choques</p>
                <p class="text-xl font-bold" style="color: {{ $resumen['conflictos'] ? '#991b1b' : '#166534' }}">
                    {{ $resumen['conflictos'] }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400">Descansos cortos</p>
                <p class="text-xl font-bold" style="color: {{ $resumen['descansos'] ? '#854d0e' : '#166534' }}">
                    {{ $resumen['descansos'] }}
                </p>
            </div>
        </div>

        @if ($resumen['conflictos'] || $resumen['sin_cubrir'])
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                Un choque impide generar los turnos; una franja sin cubrir no, pero
                ese puesto va a amanecer vacío.
            </p>
        @endif
    </x-filament::card>

    <x-filament::card>
        @if (empty($grilla['filas']))
            <p class="text-gray-500 dark:text-gray-400">
                Este cuadrante todavía no tiene franjas cargadas.
            </p>
        @else
            {{-- La grilla no se achica: en un teléfono se desplaza en horizontal --}}
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: separate; border-spacing: 4px; min-width: 900px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 4px 8px; white-space: nowrap;">Puesto</th>
                            @foreach ($grilla['dias'] as $dia)
                                <th style="text-align: left; padding: 4px 8px; font-size: 0.8rem;">{{ $dia }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grilla['filas'] as $fila)
                            <tr>
                                <td style="font-weight: 600; padding: 4px 8px; white-space: nowrap; vertical-align: top;">
                                    {{ $fila['puesto'] }}
                                </td>

                                @foreach (array_keys($grilla['dias']) as $numeroDia)
                                    <td style="vertical-align: top; min-width: 120px;">
                                        @forelse ($fila['celdas'][$numeroDia] as $celda)
                                            @php($c = $colores[$celda['estado']])
                                            <div style="background: {{ $c['fondo'] }}; border-left: 4px solid {{ $c['borde'] }}; border-radius: 4px; padding: 6px 8px; margin-bottom: 4px;">
                                                <div style="font-weight: 700; font-size: 0.8rem; color: {{ $c['texto'] }};">
                                                    {{ $celda['horario'] }}@if ($celda['cruza']) <span title="Cruza la medianoche">+1d</span>@endif
                                                </div>

                                                @forelse ($celda['guardias'] as $guardia)
                                                    <div style="font-size: 0.75rem; color: #374151;">{{ $guardia }}</div>
                                                @empty
                                                    <div style="font-size: 0.75rem; font-weight: 600; color: {{ $c['texto'] }};">
                                                        Sin asignar
                                                    </div>
                                                @endforelse

                                                @foreach ($celda['motivos'] as $motivo)
                                                    @if ($motivo !== 'sin_cubrir' || count($celda['guardias']))
                                                        <div style="font-size: 0.68rem; color: {{ $colores[$motivo]['texto'] }};">
                                                            ⚠ {{ $etiquetas[$motivo] ?? $motivo }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @empty
                                            <div style="height: 100%; min-height: 24px;"></div>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap gap-4 text-xs">
                @foreach ($etiquetas as $clave => $texto)
                    <span style="display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 12px; height: 12px; border-radius: 3px; background: {{ $colores[$clave]['fondo'] }}; border: 1px solid {{ $colores[$clave]['borde'] }};"></span>
                        {{ $texto }}
                    </span>
                @endforeach
            </div>
        @endif
    </x-filament::card>

    @if (!empty($grilla['guardias']))
        <x-filament::card>
            <h3 class="text-base font-bold mb-3">Carga semanal por guardia</h3>

            <table style="width: 100%; font-size: 0.85rem;">
                <thead>
                    <tr style="text-align: left; color: #6b7280;">
                        <th style="padding: 4px 8px;">Guardia</th>
                        <th style="padding: 4px 8px;">Turnos</th>
                        <th style="padding: 4px 8px;">Horas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grilla['guardias'] as $guardia)
                        <tr>
                            <td style="padding: 4px 8px;">{{ $guardia['nombre'] }}</td>
                            <td style="padding: 4px 8px;">{{ $guardia['turnos'] }}</td>
                            {{-- Más de 48 h semanales es materia de horas extra: conviene que salte a la vista --}}
                            <td style="padding: 4px 8px; font-weight: 600; color: {{ $guardia['horas'] > 48 ? '#991b1b' : 'inherit' }};">
                                {{ $guardia['horas'] }} h
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::card>
    @endif
</x-filament::page>

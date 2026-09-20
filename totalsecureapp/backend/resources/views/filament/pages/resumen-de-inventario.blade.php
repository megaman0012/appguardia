{{--
    Resumen de inventario: clientes en filas, productos en columnas.

    Las clases `ts-` vienen del tema propio
    (`resources/css/filament/admin/theme.css`). Antes esto iba todo en estilos en
    linea porque el proyecto no compilaba CSS; ahora si, y eso permite que la
    cabecera quede fija al desplazar y que el modo oscuro sea coherente -- dos
    cosas que en linea no se pueden hacer.

    ⚠️ `<x-filament::section>` NO existe en esta version de Filament y tumba la
    pagina entera en silencio. Va `<x-filament::card>`, que es lo que usan los
    widgets de este mismo proyecto.

    La tabla va en un contenedor con scroll propio: con mas productos no cabe, y
    sin eso la pagina entera se desplaza de lado y en tablet se pierde el menu.
--}}
<x-filament-panels::page>

    @php
        $productos = $this->productos();
        $filas     = $this->filas();
        $totales   = $this->totales();
        $desglose  = $this->desglose();
    @endphp

    <x-filament::card>
        @if (count($filas) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay inventario repartido todavía. Las cantidades salen de las listas
                de cada local.
            </p>
        @else
            <div class="ts-scroll-x">
                <table class="ts-matriz">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            @foreach ($productos as $p)
                                <th class="ts-num">{{ $p->ipc_nombre }}</th>
                            @endforeach
                            <th class="ts-num">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($filas as $f)
                            <tr wire:click="alternar({{ $f['org_code'] }})" class="ts-abrible"
                                title="Pulse para ver el desglose por local">
                                <td style="font-weight:600;">
                                    <span style="display:inline-block; width:1rem;">{{ $this->abierto === $f['org_code'] ? '▾' : '▸' }}</span>
                                    {{ $f['nombre'] }}
                                </td>

                                @foreach ($productos as $p)
                                    @php $c = $f['celdas'][$p->ipc_id] ?? null; @endphp
                                    <td class="ts-num">
                                        @if ($c === null)
                                            <span style="color:#9ca3af;">—</span>
                                        @else
                                            {{ (int) $c['distribuido'] }}
                                            {{-- Solo se muestra la comparacion cuando alguien declaro
                                                 una asignacion: si no, un «/0» pareceria un error. --}}
                                            @if ($c['asignado'] > 0)
                                                <span @class(['ts-alerta' => $c['diferencia'] < 0]) style="font-size:.72rem;{{ $c['diferencia'] < 0 ? '' : 'color:#6b7280;' }}">
                                                    / {{ (int) $c['asignado'] }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach

                                <td class="ts-num" style="font-weight:700;">
                                    {{ (int) $f['total'] }}
                                </td>
                            </tr>

                            @if ($this->abierto === $f['org_code'])
                                @forelse ($desglose as $d)
                                    <tr class="ts-hija">
                                        <td class="ts-sangria">{{ $d['nombre'] }}</td>
                                        @foreach ($productos as $p)
                                            <td class="ts-num">{{ isset($d['celdas'][$p->ipc_id]) ? (int) $d['celdas'][$p->ipc_id] : '—' }}</td>
                                        @endforeach
                                        <td class="ts-num">{{ (int) $d['total'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($productos) + 2 }}" class="ts-sangria" style="font-style:italic;">
                                            Este cliente no tiene listas de inventario en sus locales.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <td style="font-weight:700;">Total</td>
                            @foreach ($productos as $p)
                                <td class="ts-num" style="font-weight:700;">{{ (int) ($totales[$p->ipc_id] ?? 0) }}</td>
                            @endforeach
                            <td class="ts-num" style="font-weight:700;">{{ (int) $totales['general'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Cada celda es lo <strong>repartido</strong> en las listas de los locales del cliente.
                Cuando hay una asignación declarada en «Stock por cliente» se muestra
                <em>repartido / asignado</em>, y el número se pone en rojo si se repartió de más.
                Pulse una fila para ver el desglose por local.
            </p>
        @endif
    </x-filament::card>

</x-filament-panels::page>

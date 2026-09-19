{{--
    Resumen de inventario: clientes en filas, productos en columnas.

    ⚠️ **Este proyecto no compila CSS.** Solo existe lo que viene en la hoja
    precompilada de Filament, asi que aca no se pueden inventar clases de
    Tailwind: lo que no sea una clase que Filament ya use, va en estilo en linea.
    Se usa `<x-filament::section>`... **no**: ese componente NO existe en esta
    version y tumba la pagina entera en silencio. Va `<x-filament::card>`, que es
    lo que usan los widgets de este mismo proyecto.

    La tabla va dentro de un contenedor con scroll horizontal: con mas productos
    no cabe, y sin eso la pagina entera se desplaza de lado.
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
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:.8rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:.5rem .6rem; border-bottom:2px solid #d1d5db; white-space:nowrap;">
                                Cliente
                            </th>
                            @foreach ($productos as $p)
                                <th style="text-align:right; padding:.5rem .6rem; border-bottom:2px solid #d1d5db; white-space:nowrap;">
                                    {{ $p->ipc_nombre }}
                                </th>
                            @endforeach
                            <th style="text-align:right; padding:.5rem .6rem; border-bottom:2px solid #d1d5db;">
                                Total
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($filas as $f)
                            <tr wire:click="alternar({{ $f['org_code'] }})"
                                style="cursor:pointer; border-bottom:1px solid #e5e7eb;"
                                title="Pulse para ver el desglose por local">
                                <td style="padding:.5rem .6rem; font-weight:600; white-space:nowrap;">
                                    <span style="display:inline-block; width:1rem;">{{ $this->abierto === $f['org_code'] ? '▾' : '▸' }}</span>
                                    {{ $f['nombre'] }}
                                </td>

                                @foreach ($productos as $p)
                                    @php $c = $f['celdas'][$p->ipc_id] ?? null; @endphp
                                    <td style="text-align:right; padding:.5rem .6rem; white-space:nowrap;">
                                        @if ($c === null)
                                            <span style="color:#9ca3af;">—</span>
                                        @else
                                            {{ (int) $c['distribuido'] }}
                                            {{-- Solo se muestra la comparacion cuando alguien declaro
                                                 una asignacion: si no, un «/0» pareceria un error. --}}
                                            @if ($c['asignado'] > 0)
                                                <span style="color:{{ $c['diferencia'] < 0 ? '#b91c1c' : '#6b7280' }}; font-size:.72rem;">
                                                    / {{ (int) $c['asignado'] }}
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach

                                <td style="text-align:right; padding:.5rem .6rem; font-weight:700;">
                                    {{ (int) $f['total'] }}
                                </td>
                            </tr>

                            @if ($this->abierto === $f['org_code'])
                                @forelse ($desglose as $d)
                                    <tr style="background:rgba(0,0,0,.025); border-bottom:1px solid #f3f4f6;">
                                        <td style="padding:.35rem .6rem .35rem 2.2rem; color:#4b5563; white-space:nowrap;">
                                            {{ $d['nombre'] }}
                                        </td>
                                        @foreach ($productos as $p)
                                            <td style="text-align:right; padding:.35rem .6rem; color:#4b5563;">
                                                {{ isset($d['celdas'][$p->ipc_id]) ? (int) $d['celdas'][$p->ipc_id] : '—' }}
                                            </td>
                                        @endforeach
                                        <td style="text-align:right; padding:.35rem .6rem; color:#4b5563;">
                                            {{ (int) $d['total'] }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($productos) + 2 }}"
                                            style="padding:.35rem .6rem .35rem 2.2rem; color:#6b7280; font-style:italic;">
                                            Este cliente no tiene listas de inventario en sus locales.
                                        </td>
                                    </tr>
                                @endforelse
                            @endif
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <td style="padding:.55rem .6rem; border-top:2px solid #d1d5db; font-weight:700;">
                                Total
                            </td>
                            @foreach ($productos as $p)
                                <td style="text-align:right; padding:.55rem .6rem; border-top:2px solid #d1d5db; font-weight:700;">
                                    {{ (int) ($totales[$p->ipc_id] ?? 0) }}
                                </td>
                            @endforeach
                            <td style="text-align:right; padding:.55rem .6rem; border-top:2px solid #d1d5db; font-weight:700;">
                                {{ (int) $totales['general'] }}
                            </td>
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

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * El resumen de inventario, en Excel.
 *
 * ⚠️ **No usa `App\Filament\Tables\Descarga`, y no se puede.** Ese helper se
 * apoya en `fromTable()` de `pxlrbt/filament-excel`, que exporta la consulta
 * Eloquent de un `Resource`. Aca no hay consulta que exportar: cada celda es una
 * suma sobre las listas de los locales de un cliente, calculada en PHP. Por eso
 * esto arma el arreglo a mano.
 *
 * **Lleva el desglose por local, no solo el total del cliente.** En pantalla el
 * desglose se abre al pulsar; en un archivo no hay donde pulsar, y bajarse solo
 * los totales obliga a volver al panel para cada pregunta. Los locales van
 * sangrados debajo de su cliente, que es como se lee la pantalla.
 */
class ResumenDeInventarioExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    /**
     * @param  array  $filas      Las del cliente, ya calculadas por la pagina.
     * @param  array  $desgloses  [org_code => filas por local]
     * @param  \Illuminate\Support\Collection  $productos  Las columnas.
     * @param  array  $totales    La ultima fila.
     */
    public function __construct(
        private readonly array $filas,
        private readonly array $desgloses,
        private readonly \Illuminate\Support\Collection $productos,
        private readonly array $totales,
    ) {}

    public function title(): string
    {
        return 'Resumen de inventario';
    }

    public function headings(): array
    {
        return array_merge(
            ['Cliente / Local'],
            $this->productos->pluck('ipc_nombre')->all(),
            ['Total'],
        );
    }

    public function array(): array
    {
        $filas = [];

        foreach ($this->filas as $f) {
            $fila = [$f['nombre']];

            foreach ($this->productos as $p) {
                $fila[] = isset($f['celdas'][$p->ipc_id]) ? (int) $f['celdas'][$p->ipc_id] : '';
            }

            $fila[] = (int) $f['total'];
            $filas[] = $fila;

            // El desglose, sangrado con un espacio inicial: en Excel no hay
            // jerarquia visual, y sin esto un local parece otro cliente.
            foreach ($this->desgloses[$f['org_code']] ?? [] as $d) {
                $sub = ['    ' . $d['nombre']];

                foreach ($this->productos as $p) {
                    $sub[] = isset($d['celdas'][$p->ipc_id]) ? (int) $d['celdas'][$p->ipc_id] : '';
                }

                $sub[] = (int) $d['total'];
                $filas[] = $sub;
            }
        }

        $total = ['TOTAL'];

        foreach ($this->productos as $p) {
            $total[] = (int) ($this->totales[$p->ipc_id] ?? 0);
        }

        $total[] = (int) $this->totales['general'];
        $filas[] = $total;

        return $filas;
    }

    public function styles(Worksheet $hoja): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            $hoja->getHighestRow() => ['font' => ['bold' => true]],
        ];
    }
}

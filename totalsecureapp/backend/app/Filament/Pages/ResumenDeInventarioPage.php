<?php

namespace App\Filament\Pages;

use App\Exports\ResumenDeInventarioExport;
use App\Services\Inventario\ResumenDeInventario;
use App\Support\PerfilPanel;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\ProductoCatalogo;

/**
 * El resumen que pidio el cliente: clientes en filas, productos en columnas.
 *
 * **Por que es una pagina y no un recurso.** No hay una tabla que listar: cada
 * celda es la suma de las listas de todos los locales de un cliente para un
 * producto. Un `Resource` de Filament dibuja filas de un modelo, y aca la fila
 * no existe como registro.
 *
 * **Solo fue posible despues de fusionar el catalogo.** Mientras el mismo
 * producto tenia 133 ids distintos --uno por local-- no habia con que armar las
 * columnas; agrupar por el texto del nombre habria sacado productos fantasma,
 * porque la duplicacion manual ya habia dejado erratas.
 *
 * Cada fila se abre al pulsarla y muestra sus locales, que es el desglose que se
 * pidio. El calculo vive en `ResumenDeInventario`, compartido con «Stock por
 * cliente»: que las dos pantallas digan lo mismo es el motivo de que sea un
 * servicio y no dos consultas.
 */
class ResumenDeInventarioPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $title = 'Resumen de inventario';

    protected static ?string $navigationLabel = 'Resumen de inventario';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.resumen-de-inventario';

    /** Cliente cuyo desglose esta abierto. Null = ninguno. */
    public ?int $abierto = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Reportería';
    }

    public static function canAccess(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /**
     * El boton de descarga de la cabecera.
     *
     * ⚠️ No usa `App\Filament\Tables\Descarga`: ese helper exporta la consulta
     * Eloquent de un `Resource` con `fromTable()`, y aca no hay consulta que
     * exportar -- cada celda es una suma calculada en PHP.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargar')
                ->label('Descargar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->descargar()),
        ];
    }

    public function descargar(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filas = $this->filas();

        /*
         * El archivo lleva el desglose de TODOS los clientes, no solo el que
         * este abierto en pantalla: en un Excel no hay donde pulsar, y bajarse
         * los totales pelados obliga a volver al panel para cada pregunta.
         */
        $desgloses = [];

        foreach ($filas as $f) {
            $desgloses[$f['org_code']] = $this->desgloseDe($f['org_code']);
        }

        return Excel::download(
            new ResumenDeInventarioExport($filas, $desgloses, $this->productos(), $this->totales()),
            'resumen-inventario-' . now()->format('Ymd-His') . '.xlsx',
        );
    }

    /** Abre y cierra el desglose de un cliente. */
    public function alternar(int $orgCode): void
    {
        $this->abierto = $this->abierto === $orgCode ? null : $orgCode;
    }

    /** Las columnas: el catalogo activo, que hoy son cuatro productos. */
    public function productos(): \Illuminate\Support\Collection
    {
        return ProductoCatalogo::query()
            ->where('ipc_activo', true)
            ->orderBy('ipc_nombre')
            ->get(['ipc_id', 'ipc_nombre']);
    }

    /**
     * Una fila por cliente, con una celda por producto.
     *
     * ⚠️ Los locales sin cliente van en su propia fila, no se descartan: 51 de
     * los 188 locales estan asi, y esconderlos haria que el resumen cuadrara en
     * pantalla y estuviera mintiendo.
     */
    public function filas(): array
    {
        $locales = PerfilPanel::localesVisibles();
        $datos   = app(ResumenDeInventario::class)->porClienteYProducto($locales);

        $nombres = DB::table('organizacion')->pluck('org_descripcion', 'org_code');

        $filas = [];

        foreach ($datos as $d) {
            $org = (int) $d->org_code;

            $filas[$org] ??= [
                'org_code' => $org,
                'nombre'   => $org === ResumenDeInventario::SIN_CLIENTE
                    ? 'Sin cliente asignado'
                    : ($nombres[$org] ?? "Cliente {$org}"),
                'celdas'   => [],
                'total'    => 0.0,
            ];

            $filas[$org]['celdas'][(int) $d->producto_id] = [
                'asignado'    => (float) $d->asignado,
                'distribuido' => (float) $d->distribuido,
                'diferencia'  => (float) $d->diferencia,
            ];

            $filas[$org]['total'] += (float) $d->distribuido;
        }

        // El grupo sin cliente va al final: es una tarea pendiente, no un cliente.
        uasort($filas, function ($a, $b) {
            if ($a['org_code'] === ResumenDeInventario::SIN_CLIENTE) return 1;
            if ($b['org_code'] === ResumenDeInventario::SIN_CLIENTE) return -1;

            return $b['total'] <=> $a['total'];
        });

        return array_values($filas);
    }

    /** El desglose por local del cliente abierto en pantalla. */
    public function desglose(): array
    {
        return $this->abierto === null ? [] : $this->desgloseDe($this->abierto);
    }

    /** El desglose por local de un cliente cualquiera. Lo usa tambien la descarga. */
    public function desgloseDe(int $orgCode): array
    {
        $filas = [];

        foreach (app(ResumenDeInventario::class)
            ->porLocalYProducto($orgCode, PerfilPanel::localesVisibles()) as $d) {
            $ins = (int) $d->ins_code;

            $filas[$ins] ??= ['nombre' => $d->ins_descripcion, 'celdas' => [], 'total' => 0.0];
            $filas[$ins]['celdas'][(int) $d->producto_id] = (float) $d->cantidad;
            $filas[$ins]['total'] += (float) $d->cantidad;
        }

        return array_values($filas);
    }

    /** Los totales de la ultima fila: una columna es una suma vertical. */
    public function totales(): array
    {
        $t = ['general' => 0.0];

        foreach ($this->filas() as $f) {
            foreach ($f['celdas'] as $prod => $c) {
                $t[$prod] = ($t[$prod] ?? 0) + $c['distribuido'];
                $t['general'] += $c['distribuido'];
            }
        }

        return $t;
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RondaDetalleResource\Pages;
use App\Filament\Resources\RondaDetalleResource\RelationManagers;
use Modules\Administracion\Models\ronda_detalle;

use Filament\Schemas\Schema;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Tables\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\Action;
use Filament\Tables\Columns\ImageColumn;


use App\Support\PerfilPanel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RondaDetalleResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reportería';
    }
    protected static ?int $navigationSort = 4;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'detalle';
    protected static ?string $pluralModelLabel = 'detalles';
    protected static ?string $navigationLabel = 'Detalle de ronda';
    protected static ?string $model = ronda_detalle::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['rondaCabecera.institucion.cliente', 'users'];
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema{ return $schema->schema([]); }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rondaCabecera.institucion.cliente.org_descripcion')->size('sm')
                    ->label('Cliente')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rondaCabecera.institucion.ins_descripcion')->size('sm')
                    ->label('Local')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('users.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('rd_fecha_hora')->size('sm')
                    ->label('Fecha')
                    ->sortable()
                    ->toggleable()
                    ->dateTime(),

                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->width(35)
                    ->height(35)
                    ->circular()
                    ->view('tables.columns.imagen-modal'),

                TextColumn::make('rd_observacion')->size('sm')
                    ->label('Observación')
                    ->toggleable()
                    ->searchable(),

                /*ToggleColumn::make('rd_estado')
                    ->label('Estado')
                    ->searchable(false)
                    ->toggleable(),*/
            ])
            ->filters([])
            ->actions([
                Action::make('gmap')
                    ->label('Ubicación')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->rd_lat},{$record->rd_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary')
                    ->visible(fn($record) => is_null($record->rd_im_code)),
                Action::make('marcador_map')
                    ->label('Marcador')
                    ->url(fn($record) => $record->marcador
                        ? "https://www.google.com/maps/dir/{$record->rd_lat},{$record->rd_lng}/{$record->marcador->im_lat},{$record->marcador->im_lng}"
                        : '#'
                    )
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-flag')
                    ->color('success')
                    ->visible(fn($record) => !is_null($record->rd_im_code))
            ])
            ->bulkActions([
                Descarga::enLote('ronda-detalle'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListRondaDetalles::route('/'),
            //'create' => Pages\CreateRondaDetalle::route('/create'),
            //'edit' => Pages\EditRondaDetalle::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $ronda_id = request()->query('ronda');

        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA)
            ->where('rd_rc_id', $ronda_id );

        /*
         * ⚠️ Antes esto terminaba aca, filtrando SOLO por el `?ronda=` de la URL.
         *
         * `rc_id` es un entero consecutivo, asi que un Supervisor cambiaba el
         * numero a mano y leia el detalle de las rondas de otro cliente: a que
         * hora paso el guardia por cada punto, con foto y coordenadas. El
         * listado padre (`RondaCabeceraResource`) si acotaba, lo que hacia el
         * agujero menos visible: por la navegacion normal nunca se llegaba a una
         * ronda ajena.
         *
         * El alcance se aplica subiendo a la cabecera y no por `rd_ins_code`,
         * que el detalle tambien tiene: quien decide de que local es una ronda
         * es su cabecera, y asi este filtro dice exactamente lo mismo que el del
         * listado padre. Si alguna fila quedara con los dos valores distintos,
         * mandaria el de la cabecera en los dos lados y no en uno cada uno.
         */
        $locales = PerfilPanel::localesVisibles();

        if ($locales === null) {
            return $query;
        }

        if (empty($locales)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('rondaCabecera', function (Builder $q) use ($locales) {
            $q->whereIn('rc_ins_code', $locales);
        });
    }

}

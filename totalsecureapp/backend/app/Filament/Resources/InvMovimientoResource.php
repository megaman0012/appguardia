<?php

namespace App\Filament\Resources;

use App\Support\PerfilPanel;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Modules\Administracion\Models\UserHasInstitucion;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Session;
use App\Filament\Resources\InvMovimientoResource\Pages;
use App\Filament\Resources\InvMovimientoResource\RelationManagers;
use Modules\Administracion\Models\InvMovimiento;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;



class InvMovimientoResource extends Resource
{
    protected static ?string $model = InvMovimiento::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['institucion.organizacionSede.organizacion', 'institucion.organizacionSede.sede', 'lista', 'recep_user'];

    protected static ?string $navigationGroup = 'Reporteria';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'Inventario Equipamento';
    protected static ?string $navigationIcon = 'heroicon-o-switch-horizontal';
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mov_id')->size('sm')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                BadgeColumn::make('mov_tipo')->size('sm')
                    ->label('Estado')
                    ->colors([
                        'secondary' => 'Asignacion',
                        'warning' => 'Recepcion',
                        'success' => 'Devolucion',
                        'danger' => 'Finalizado',
                    ]),
                //Institucion
                TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['institucion'] ?? true),
                TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['institucion'] ?? true),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->searchable(),

                //Lista
                TextColumn::make('lista.lp_nombre')->size('sm')
                    ->label('Lista')
                    ->searchable(),
                TextColumn::make('lista.lp_descripcion')->size('sm')
                    ->label('Descripcion'),

                //Asignacion
                /*TextColumn::make('recep_asig_user.usu_nmbcom')->size('sm')
                    ->label('Asignacion Usuario')
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['recepcion'] ?? true),
                TextColumn::make('mov_recep_asig_fecha')->size('sm')
                    ->label('Asignacion Fecha')
                    ->sortable()
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['recepcion'] ?? true),
                TextColumn::make('mov_recep_asig_obsv')->size('sm')
                    ->label('Asignacion Observacion')
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['recepcion'] ?? true)
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->mov_recep_asig_obsv),*/

                //Recepcion
                TextColumn::make('recep_user.usu_nmbcom')->size('sm')
                    ->label('Recepcion Usuario')
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['recepcion'] ?? true),
                TextColumn::make('mov_recep_fecha')->size('sm')
                    ->label('Recepcion Fecha')
                    ->sortable()
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['recepcion'] ?? true),
                /*TextColumn::make('mov_recep_obsv')->size('sm')
                    ->label('Recepcion Observacion')
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['recepcion'] ?? true)
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->mov_recep_obsv),*/

                //Devolucion
                TextColumn::make('mov_devol_fecha')->size('sm')
                    ->label('Devolucion Fecha')
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['devolucion'] ?? true),
                /*TextColumn::make('mov_devol_obsv')->size('sm')
                    ->label('Devolucion Observacion')
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['devolucion'] ?? true)
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->mov_devol_obsv),*/

                //Finalizado
                /*TextColumn::make('mov_devol_entreg_user.usu_nmbcom')->size('sm')
                    ->label('Entregado a Usuario')
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['devolucion'] ?? true),
                TextColumn::make('mov_devol_entreg_fecha')->size('sm')
                    ->label('Entregado Fecha')
                    ->sortable()
                    ->searchable()
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['devolucion'] ?? true),*/
                /*TextColumn::make('mov_devol_entreg_obsv')->size('sm')
                    ->label('Entregado Observacion')
                    ->visible(fn ($livewire) => $livewire->tableFilters['rec_dev']['devolucion'] ?? true)
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->mov_devol_aprob_obsv),*/

                //Estado
                BooleanColumn::make('mov_estado')
                    ->label('Estado')
                    ->sortable()
                    ->searchable(false),
            ])
            ->filters([
                Filter::make('rec_dev')
                    ->form([
                        Toggle::make('recepcion')
                            ->label('Campos Recepcion')
                            ->default(true),
                        Toggle::make('devolucion')
                            ->label('Campos Devolucion')
                            ->default(true),
                        Toggle::make('institucion')
                            ->label('Campos Institucion')
                            ->default(true),
                    ])
                    ->query(fn (Builder $query) => $query),
                Filter::make('fch_recepcion')
                    ->label('Recepcion')
                    ->form([
                        DatePicker::make('recfrom')
                            ->label('Fecha Recepcion Desde'),
                        DatePicker::make('recuntil')
                            ->label('Fecha Recepcion Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['recfrom'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('mov_fecha_recepcion', '>=', $date)
                            )
                            ->when(
                                $data['recuntil'],
                                fn (Builder $query, $date) =>
                                $query->whereDate('mov_fecha_recepcion', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Action::make('verDetalle')->label('Detalles')
                    ->url(fn(InvMovimiento $record)=>
                    InvMovimientoDetalleResource::getUrl(
                            'index', [ 'mov' => $record->mov_id ]
                        )
                    )
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('Exportar a Excel'),
            ]);
    }

    // ------------------ RELATIONS ------------------
    public static function getRelations(): array { return [ ]; }

    // ------------------ PAGES ------------------
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvMovimientos::route('/'),
            //'create' => Pages\CreateInvMovimiento::route('/create'),
            //'edit' => Pages\EditInvMovimiento::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    protected static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeOperar();
    }

    /**
     * Bloquea la RUTA, no solo el menu.
     *
     * shouldRegisterNavigation() solo oculta el item del menu lateral: quien
     * escribiera la URL a mano entraba igual. Filament aborta con 403 cuando
     * canViewAny() es false (Pages\Page::authorizeResourceAccess).
     */
    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
        if(PerfilPanel::alcanceEsPorInstitucion()){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('mov_ins_code', $institucionesCodes);
        }
        return $query;
    }


}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlertasResource\Pages;
use App\Filament\Resources\AlertasResource\RelationManagers;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Modules\Administracion\Models\Alertas;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\OrganizacionSede;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;
use Session;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Administracion\Models\UserHasInstitucion;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class AlertasResource extends Resource
{
    public static function getNavigationGroup(): ?string {
        return 'Reporteria';
    }
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationLabel = 'Alertas';
    protected static ?string $model = Alertas::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation';
    public static function form(Form $form): Form{ return $form->schema([]); }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('al_code')->size('sm')
                    ->label('Codigo')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Usuario')
                    ->toggleable()
                    ->searchable(),
                BadgeColumn::make('al_estado_alerta')->size('sm')
                    ->label('Estado Alerta')
                    ->colors([
                        'success' => 'Finalizada',
                        'danger' => 'Pendiente',
                    ])
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('al_fecha')->size('sm')
                    ->label('Fecha')
                    ->sortable(),
                /*ToggleColumn::make('al_estado')
                    ->label('Estado')
                    ->searchable(false)
                    ->toggleable(),*/
            ])
            ->filters([
                Filter::make('al_fecha')
                ->label('Rango de fecha')
                ->form([
                    DatePicker::make('from')
                        ->label('Desde'),
                    DatePicker::make('until')
                        ->label('Hasta'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['from'],
                            fn (Builder $query, $date) =>
                            $query->whereDate('al_fecha', '>=', $date)
                        )
                        ->when(
                            $data['until'],
                            fn (Builder $query, $date) =>
                            $query->whereDate('al_fecha', '<=', $date)
                        );
                }),
            ])
            ->actions([
                Tables\Actions\Action::make('gmap')
                    ->label('Mapa')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->al_lat},{$record->al_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary'),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('Exportar a Excel'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListAlertas::route('/'),
            //'create' => Pages\CreateAlertas::route('/create'),
            //'edit' => Pages\EditAlertas::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder {
        $query = parent::getEloquentQuery();
        if(in_array( Session::get('usuPF'), ['Supervisor'] )){
            $institucionesCodes = UserHasInstitucion::where('ui_usu_id', Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');
            if ($institucionesCodes->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }
            return $query->whereIn('al_ins_code', $institucionesCodes);
        }
        return $query;
    }

}

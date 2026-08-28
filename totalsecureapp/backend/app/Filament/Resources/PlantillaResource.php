<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlantillaResource\Pages;
use App\Filament\Resources\PlantillaResource\RelationManagers\FranjasRelationManager;
use App\Support\PerfilPanel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\Plantilla;

/**
 * Cuadrante de cobertura de un local.
 *
 * Programar turnos de a uno no escala; aqui se define el patron semanal y de ahi
 * se generan los turnos del periodo con un solo paso.
 */
class PlantillaResource extends Resource
{
    protected static ?string $model = Plantilla::class;
    protected static ?string $navigationLabel = 'Cuadrante de turnos';
    protected static ?string $navigationIcon = 'heroicon-o-template';
    protected static ?int $navigationSort = 7;

    protected const RELACIONES_TABLA = ['institucion'];

    public static function getNavigationGroup(): ?string
    {
        return 'Operacion';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('pl_ins_code')
                ->label('Local')
                ->options(
                    OrganizacionInstitucion::where('ins_estado', 1)
                        ->with('ciudad')
                        ->orderBy('ins_descripcion')
                        ->get()
                        ->mapWithKeys(fn ($l) => [
                            $l->ins_code => $l->ins_descripcion
                                . ' — ' . (optional($l->ciudad)->cd_nombre ?? 'sin ciudad'),
                        ])
                )
                ->searchable()
                ->required()
                ->disabledOn('edit')
                ->helperText('Los puestos disponibles salen de este local'),

            TextInput::make('pl_nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(120)
                ->helperText('Por ejemplo: Cuadrante regular, Refuerzo de temporada'),

            DatePicker::make('pl_vigencia_desde')->label('Vigente desde'),
            DatePicker::make('pl_vigencia_hasta')->label('Vigente hasta'),

            Textarea::make('pl_observaciones')
                ->label('Observaciones')
                ->rows(2)
                ->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pl_nombre')->size('sm')->label('Cuadrante')->searchable()->sortable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')->label('Local')->sortable(),
                BadgeColumn::make('pl_estado')
                    ->label('Estado')
                    ->colors([
                        'secondary' => Plantilla::BORRADOR,
                        'success'   => Plantilla::PUBLICADA,
                        'warning'   => Plantilla::ARCHIVADA,
                    ]),
                TextColumn::make('franjas_count')->size('sm')->label('Franjas')->counts('franjas'),
                TextColumn::make('turnos_count')->size('sm')->label('Turnos generados')->counts('turnos'),
                TextColumn::make('pl_vigencia_desde')->size('sm')->label('Desde')->date('d/m/Y')->toggleable(),
                TextColumn::make('pl_vigencia_hasta')->size('sm')->label('Hasta')->date('d/m/Y')->toggleable(),
            ])
            ->defaultSort('pl_id', 'desc')
            ->actions([
                // Es la única forma que tiene el Supervisor de ver el detalle:
                // la pantalla de edición la tiene cerrada.
                Tables\Actions\Action::make('grilla')
                    ->label('Ver grilla')
                    ->icon('heroicon-o-view-grid')
                    ->color('secondary')
                    ->url(fn (Plantilla $record) => static::getUrl('grilla', ['record' => $record])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [FranjasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlantillas::route('/'),
            'create' => Pages\CreatePlantilla::route('/create'),
            'edit'   => Pages\EditPlantilla::route('/{record}/edit'),
            'grilla' => Pages\VerGrilla::route('/{record}/grilla'),
        ];
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    protected static function shouldRegisterNavigation(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /** Armar el cuadrante es del Lider Operativo; el Supervisor lo consulta. */
    public static function canCreate(): bool
    {
        return PerfilPanel::puedeAdministrarLocales();
    }

    public static function canEdit(Model $record): bool
    {
        return PerfilPanel::puedeAdministrarLocales();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);

        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $codigos = \Modules\Administracion\Models\UserHasInstitucion::where('ui_usu_id', \Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code');

            return $codigos->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('pl_ins_code', $codigos);
        }

        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('pl_ins_code', $localesDelPais);
        }

        return $query;
    }
}

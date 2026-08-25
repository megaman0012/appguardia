<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PuestoResource\Pages;
use App\Support\PerfilPanel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\Puesto;

/**
 * Puestos de trabajo de cada Local.
 *
 * Los define quien administra locales (Administrador y Lider Operativo); el
 * Supervisor los ve pero no los modifica, igual que con los locales.
 */
class PuestoResource extends Resource
{
    protected static ?string $model = Puesto::class;
    protected static ?string $navigationLabel = 'Puesto de trabajo';
    protected static ?string $navigationIcon = 'heroicon-o-location-marker';
    protected static ?int $navigationSort = 5;

    /** Relaciones que usan las columnas de la tabla (evita el N+1). */
    protected const RELACIONES_TABLA = ['institucion.ciudad.provincia.pais'];

    public static function getNavigationGroup(): ?string
    {
        return 'Ubicacion Geografica';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('pu_ins_code')
                ->label('Local')
                ->options(
                    OrganizacionInstitucion::where('ins_estado', 1)
                        ->with('ciudad')
                        ->orderBy('ins_descripcion')
                        ->get()
                        ->mapWithKeys(fn ($l) => [
                            // Se muestra la ciudad porque hay locales homonimos.
                            $l->ins_code => $l->ins_descripcion
                                . ' — ' . (optional($l->ciudad)->cd_nombre ?? 'sin ciudad'),
                        ])
                )
                ->searchable()
                ->required(),

            TextInput::make('pu_nombre')
                ->label('Puesto')
                ->required()
                ->maxLength(120)
                ->helperText('Garita de ingreso, andén de carga, sala de monitoreo…'),

            TextInput::make('pu_descripcion')
                ->label('Descripción')
                ->maxLength(255),

            TextInput::make('pu_lat')
                ->label('Latitud')
                ->maxLength(50)
                ->helperText('Opcional. Hoy la presencia se valida contra el local'),

            TextInput::make('pu_lng')
                ->label('Longitud')
                ->maxLength(50),

            Toggle::make('pu_estado')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')->sortable()->searchable(),
                TextColumn::make('institucion.ciudad.cd_nombre')->size('sm')
                    ->label('Ciudad')->sortable()->toggleable(),
                TextColumn::make('pu_nombre')->size('sm')
                    ->label('Puesto')->sortable()->searchable(),
                TextColumn::make('pu_descripcion')->size('sm')
                    ->label('Descripción')->toggleable(),
                TextColumn::make('turnos_count')->size('sm')
                    ->label('Turnos')->counts('turnos')->toggleable(),
                BooleanColumn::make('pu_estado')->label('Activo')->toggleable(),
            ])
            ->defaultSort('pu_nombre')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPuestos::route('/'),
            'create' => Pages\CreatePuesto::route('/create'),
            'edit'   => Pages\EditPuesto::route('/{record}/edit'),
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

    /** Definir puestos es de quien administra locales, no del Supervisor. */
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
                : $query->whereIn('pu_ins_code', $codigos);
        }

        // El Lider Operativo ve los puestos de los locales de su(s) pais(es).
        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('pu_ins_code', $localesDelPais);
        }

        return $query;
    }
}

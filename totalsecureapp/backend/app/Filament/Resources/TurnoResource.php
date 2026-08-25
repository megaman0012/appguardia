<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TurnoResource\Pages;
use App\Support\PerfilPanel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Modules\Administracion\Models\Puesto;
use Modules\Administracion\Models\Turno;
use Modules\MobileApp\Models\users;

/**
 * Programacion de turnos.
 *
 * Hasta ahora NADA creaba turnos: TurnoService solo vincula marcajes y cierra
 * los del dia, y no habia pantalla en el panel. Por eso la tabla estaba vacia y
 * el widget "Cumplimiento de turnos" mostraba siempre cero. Aqui se programan.
 *
 * Un turno se cubre en un puesto concreto del local, que es lo que da sentido a
 * la tabla puesto.
 */
class TurnoResource extends Resource
{
    protected static ?string $model = Turno::class;
    protected static ?string $navigationLabel = 'Turnos';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 6;

    protected const RELACIONES_TABLA = ['usuario', 'institucion', 'puesto'];

    public static function getNavigationGroup(): ?string
    {
        return 'Operacion';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tu_usu_id')
                ->label('Guardia')
                ->options(
                    users::where('usu_state', 1)
                        ->orderBy('usu_nmbcom')
                        ->get()
                        ->mapWithKeys(fn ($u) => [$u->id => $u->usu_nmbcom . ' — ' . $u->usu_cedula])
                )
                ->searchable()
                ->required(),

            Select::make('tu_ins_code')
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
                ->reactive(),

            Select::make('tu_puesto_id')
                ->label('Puesto')
                // Solo los puestos del local elegido: ofrecer los de otro local
                // permitiria programar un turno incoherente.
                ->options(fn (callable $get) => $get('tu_ins_code')
                    ? Puesto::where('pu_ins_code', $get('tu_ins_code'))
                        ->where('pu_estado', true)
                        ->orderBy('pu_nombre')
                        ->pluck('pu_nombre', 'pu_id')
                    : [])
                ->searchable()
                ->helperText('Opcional: no todos los locales dividen el trabajo en puestos'),

            DatePicker::make('tu_fecha')
                ->label('Fecha')
                ->required()
                ->default(now()),

            TimePicker::make('tu_hora_inicio_prevista')
                ->label('Hora de entrada')
                ->required()
                ->withoutSeconds(),

            TimePicker::make('tu_hora_fin_prevista')
                ->label('Hora de salida')
                ->required()
                ->withoutSeconds()
                ->helperText('Si el turno cruza medianoche, la salida puede ser menor que la entrada'),

            Select::make('tu_estado')
                ->label('Estado')
                ->options([
                    'programado'  => 'Programado',
                    'en_curso'    => 'En Curso',
                    'completado'  => 'Completado',
                    'ausente'     => 'Ausente',
                    'inasistente' => 'Inasistente',
                ])
                ->default('programado')
                ->required(),

            Textarea::make('tu_observaciones')
                ->label('Observaciones')
                ->rows(2)
                ->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tu_fecha')->size('sm')
                    ->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Guardia')->sortable()->searchable(),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Local')->sortable()->toggleable(),
                TextColumn::make('puesto.pu_nombre')->size('sm')
                    ->label('Puesto')->sortable()->toggleable()
                    ->default('—'),
                TextColumn::make('tu_hora_inicio_prevista')->size('sm')->label('Entrada'),
                TextColumn::make('tu_hora_fin_prevista')->size('sm')->label('Salida'),
                TextColumn::make('tu_marcada_entrada')->size('sm')
                    ->label('Marcó entrada')->dateTime('d/m H:i')->toggleable()->default('—'),
                BadgeColumn::make('estado_badge')
                    ->label('Estado')
                    ->colors([
                        'secondary' => fn ($state) => $state === 'Programado',
                        'primary'   => fn ($state) => $state === 'En Curso',
                        'success'   => fn ($state) => $state === 'Completado',
                        'danger'    => fn ($state) => in_array($state, ['Ausente', 'Inasistente'], true),
                    ]),
                TextColumn::make('minutos_tardanza_display')->size('sm')
                    ->label('Tardanza')->toggleable()->default('—'),
            ])
            ->defaultSort('tu_fecha', 'desc')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTurnos::route('/'),
            'create' => Pages\CreateTurno::route('/create'),
            'edit'   => Pages\EditTurno::route('/{record}/edit'),
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

    /**
     * Programar turnos es de quien administra la operacion.
     *
     * El Supervisor los consulta —es su tablero de control— pero no los define:
     * la planificacion la hace el Lider Operativo.
     */
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
                : $query->whereIn('tu_ins_code', $codigos);
        }

        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('tu_ins_code', $localesDelPais);
        }

        return $query;
    }
}

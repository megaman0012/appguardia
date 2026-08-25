<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacanteResource\Pages;
use App\Services\VacanteService;
use App\Support\PerfilPanel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
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
use Modules\Administracion\Models\TurnoPostulacion;
use Modules\Administracion\Models\TurnoVacante;

/**
 * Puestos que quedaron vacíos y hay que cubrir.
 *
 * Es la pantalla donde el supervisor se entera de que un guardia no llegó
 * mientras todavía se puede hacer algo. Antes esa información aparecía en el
 * cierre del día, a las 23:55, cuando ya no servía para nada.
 */
class VacanteResource extends Resource
{
    protected static ?string $model = TurnoVacante::class;
    protected static ?string $navigationLabel = 'Cobertura de turnos';
    protected static ?string $modelLabel = 'vacante';
    protected static ?string $pluralModelLabel = 'vacantes';
    protected static ?string $navigationIcon = 'heroicon-o-user-add';
    // Sin esto Filament derivaría la ruta del modelo: /admin/turno-vacantes.
    protected static ?string $slug = 'vacantes';
    protected static ?int $navigationSort = 8;

    protected const RELACIONES_TABLA = ['institucion', 'puesto', 'ausente'];

    public static function getNavigationGroup(): ?string
    {
        return 'Operacion';
    }

    /** Lo urgente es lo que espera decisión: por confirmar o abierto sin cubrir. */
    protected static function getNavigationBadge(): ?string
    {
        $pendientes = static::getEloquentQuery()->vivas()->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    protected static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tv_ins_code')
                ->label('Local')
                ->options(
                    OrganizacionInstitucion::where('ins_estado', 1)
                        ->orderBy('ins_descripcion')
                        ->pluck('ins_descripcion', 'ins_code')
                )
                ->searchable()
                ->required()
                ->reactive()
                ->disabledOn('edit'),

            Select::make('tv_puesto_id')
                ->label('Puesto')
                ->options(fn (callable $get) => $get('tv_ins_code')
                    ? Puesto::where('pu_ins_code', $get('tv_ins_code'))
                        ->where('pu_estado', true)
                        ->orderBy('pu_nombre')
                        ->pluck('pu_nombre', 'pu_id')
                    : [])
                ->searchable(),

            DatePicker::make('tv_fecha')->label('Fecha')->required(),
            Select::make('tv_motivo')
                ->label('Motivo')
                ->options(TurnoVacante::MOTIVOS)
                ->default('refuerzo')
                ->required(),

            TimePicker::make('tv_hora_inicio')->label('Entrada')->required()->withoutSeconds(),
            TimePicker::make('tv_hora_fin')->label('Salida')->required()->withoutSeconds(),

            Textarea::make('tv_observaciones')->label('Observaciones')->rows(2)->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('institucion.ins_descripcion')->size('sm')->label('Local')->sortable(),
                TextColumn::make('puesto.pu_nombre')->size('sm')->label('Puesto')->default('—'),
                TextColumn::make('tv_fecha')->size('sm')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('horario')
                    ->size('sm')
                    ->label('Horario')
                    ->getStateUsing(fn (TurnoVacante $record) => substr((string) $record->tv_hora_inicio, 0, 5)
                        . ' – ' . substr((string) $record->tv_hora_fin, 0, 5)),
                TextColumn::make('ausente.usu_nmbcom')->size('sm')->label('No cubrió')->default('—')->toggleable(),
                BadgeColumn::make('tv_motivo')
                    ->label('Motivo')
                    ->enum(TurnoVacante::MOTIVOS)
                    ->colors([
                        'danger'  => 'falta',
                        'primary' => 'refuerzo',
                        // Avisados con tiempo: el que avisa no es el que falta.
                        'warning' => static fn ($state): bool => in_array(
                            $state,
                            ['aviso', 'enfermedad', 'permiso'],
                            true
                        ),
                        'secondary' => TurnoVacante::BAJA,
                    ]),
                BadgeColumn::make('tv_estado')
                    ->label('Estado')
                    ->enum(TurnoVacante::ESTADOS)
                    ->colors([
                        'danger'  => TurnoVacante::DETECTADA,
                        'warning' => TurnoVacante::ABIERTA,
                        'success' => TurnoVacante::CUBIERTA,
                        // Cancelada y vencida comparten color: una sola clave por
                        // color, o la segunda pisaría a la primera.
                        'secondary' => static fn ($state): bool => in_array(
                            $state,
                            [TurnoVacante::CANCELADA, TurnoVacante::VENCIDA],
                            true
                        ),
                    ]),
                TextColumn::make('tv_observaciones')
                    ->size('sm')
                    ->label('Detalle')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('tv_alcance')
                    ->size('sm')
                    ->label('Alcance')
                    ->formatStateUsing(fn ($state) => $state === TurnoVacante::ALCANCE_CIUDAD ? 'Ciudad' : 'Local'),
                TextColumn::make('postulaciones_vigentes_count')
                    ->size('sm')
                    ->label('Postulados')
                    ->counts('postulacionesVigentes')
                    // Se usa $record y no $state: una columna solo inyecta
                    // livewire, record y rowLoop en sus closures.
                    ->color(fn (TurnoVacante $record) => ($record->postulaciones_vigentes_count ?? 0) > 0
                        ? 'success'
                        : 'secondary'),
            ])
            ->defaultSort('tv_id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tv_estado')
                    ->label('Estado')
                    ->options(TurnoVacante::ESTADOS),
                Tables\Filters\Filter::make('pendientes')
                    ->label('Solo las que esperan decisión')
                    ->query(fn (Builder $query) => $query->vivas()),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Confirmar y ofrecer')
                    ->icon('heroicon-o-speakerphone')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar que el puesto quedó vacío')
                    ->modalSubheading('Se ofrecerá primero a los guardias de este local. Si en media hora nadie se postula, se abre al resto de la ciudad.')
                    ->visible(fn (TurnoVacante $record) => $record->tv_estado === TurnoVacante::DETECTADA
                        && PerfilPanel::puedeOperar())
                    ->action(function (TurnoVacante $record) {
                        app(VacanteService::class)->abrir($record, \Session::get('usuID'));

                        Notification::make()
                            ->title('Turno ofrecido')
                            ->body('Los guardias habilitados ya pueden postularse.')
                            ->success()
                            ->send();
                    }),

                // Elegir quién cubre es del Líder Operativo: cubrir un puesto
                // ante el cliente es su responsabilidad. El Supervisor confirma
                // la falta y ve a los postulados, pero no asigna.
                Tables\Actions\Action::make('confirmarCobertura')
                    ->label('Elegir quién cubre')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TurnoVacante $record) => $record->tv_estado === TurnoVacante::ABIERTA
                        && PerfilPanel::puedeAdministrarLocales())
                    ->form(fn (TurnoVacante $record) => [
                        Select::make('tp_id')
                            ->label('Guardia que cubre')
                            ->options(static::opcionesDePostulantes($record))
                            ->required()
                            ->helperText('Entre paréntesis, las horas que ya tiene programadas este mes.'),
                    ])
                    ->action(function (TurnoVacante $record, array $data) {
                        try {
                            $r = app(VacanteService::class)
                                ->confirmar($record, (int) $data['tp_id'], \Session::get('usuID'));
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo confirmar')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Turno cubierto')
                            ->body('Se creó el turno de cobertura #' . $r['turno']->tu_id . '.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancelar')
                    ->label('Ya no hace falta')
                    ->icon('heroicon-o-x-circle')
                    ->color('secondary')
                    ->requiresConfirmation()
                    ->modalSubheading('Use esto si el guardia apareció o si el puesto no se va a cubrir.')
                    ->visible(fn (TurnoVacante $record) => $record->estaViva() && PerfilPanel::puedeOperar())
                    ->action(function (TurnoVacante $record) {
                        app(VacanteService::class)->cancelar($record, \Session::get('usuID'), 'Cerrada desde el panel');

                        Notification::make()->title('Vacante cerrada')->success()->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    /** @return array<int, string> */
    private static function opcionesDePostulantes(TurnoVacante $vacante): array
    {
        $servicio = app(VacanteService::class);

        return $vacante->postulaciones()
            ->with('usuario')
            ->where('tp_estado', TurnoPostulacion::POSTULADO)
            ->get()
            ->mapWithKeys(fn (TurnoPostulacion $p) => [
                $p->tp_id => sprintf(
                    '%s — %s (%sh este mes)',
                    optional($p->usuario)->usu_nmbcom ?? 'Guardia ' . $p->tp_usu_id,
                    optional($p->usuario)->usu_cedula ?? '',
                    $servicio->horasDelMes((int) $p->tp_usu_id)
                ),
            ])
            ->all();
    }

    public static function getRelations(): array
    {
        return [VacanteResource\RelationManagers\PostulacionesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVacantes::route('/'),
            'create' => Pages\CreateVacante::route('/create'),
            'view'   => Pages\ViewVacante::route('/{record}'),
        ];
    }

    protected static function shouldRegisterNavigation(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /** Un refuerzo pedido por el cliente lo carga quien opera el local. */
    public static function canCreate(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
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
                : $query->whereIn('tv_ins_code', $codigos);
        }

        $localesDelPais = PerfilPanel::localesDelUsuario();
        if ($localesDelPais !== null) {
            return empty($localesDelPais)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('tv_ins_code', $localesDelPais);
        }

        return $query;
    }
}

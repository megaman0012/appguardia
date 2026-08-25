<?php

namespace App\Filament\Resources\PlantillaResource\RelationManagers;

use App\Support\PerfilPanel;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Modules\Administracion\Models\PlantillaFranja;
use Modules\Administracion\Models\Puesto;
use Modules\MobileApp\Models\users;

/**
 * Franjas del cuadrante: qué puesto se cubre, qué día y en qué horario, con los
 * guardias asignados a cada una.
 *
 * Las asignaciones van dentro de la franja (repeater) y no como pantalla aparte:
 * definir la franja y decir quién la cubre es un solo acto mental.
 */
class FranjasRelationManager extends RelationManager
{
    protected static string $relationship = 'franjas';
    protected static ?string $recordTitleAttribute = 'pf_id';
    protected static ?string $title = 'Franjas de cobertura';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('pf_puesto_id')
                ->label('Puesto')
                // Solo los puestos del local de esta plantilla: uno de otro local
                // daría un turno incoherente y el generador lo rechazaría.
                ->options(function (RelationManager $livewire) {
                    return Puesto::where('pu_ins_code', $livewire->ownerRecord->pl_ins_code)
                        ->where('pu_estado', true)
                        ->orderBy('pu_nombre')
                        ->pluck('pu_nombre', 'pu_id');
                })
                ->required()
                ->searchable(),

            Select::make('pf_dia_semana')
                ->label('Día')
                ->options(PlantillaFranja::DIAS)
                ->required(),

            TimePicker::make('pf_hora_inicio')->label('Entrada')->required()->withoutSeconds(),
            TimePicker::make('pf_hora_fin')
                ->label('Salida')
                ->required()
                ->withoutSeconds()
                ->helperText('Si la salida es menor que la entrada, el turno cruza la medianoche'),

            Repeater::make('asignaciones')
                ->label('Guardias asignados')
                ->relationship()
                ->schema([
                    Select::make('pa_usu_id')
                        ->label('Guardia')
                        ->options(
                            users::where('usu_state', 1)
                                ->orderBy('usu_nmbcom')
                                ->get()
                                ->mapWithKeys(fn ($u) => [$u->id => $u->usu_nmbcom . ' — ' . $u->usu_cedula])
                        )
                        ->searchable()
                        ->required(),
                    Forms\Components\DatePicker::make('pa_desde')
                        ->label('Desde')
                        ->helperText('Vacío = siempre'),
                    Forms\Components\DatePicker::make('pa_hasta')
                        ->label('Hasta')
                        ->helperText('Para reemplazos temporales'),
                ])
                ->columns(3)
                ->columnSpan(2)
                ->defaultItems(1)
                ->helperText('Una franja sin guardias queda como hueco de cobertura, y se avisa al generar'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('puesto.pu_nombre')->label('Puesto')->sortable(),
                TextColumn::make('dia_nombre')->label('Día'),
                TextColumn::make('pf_hora_inicio')->label('Entrada'),
                TextColumn::make('pf_hora_fin')->label('Salida'),
                TextColumn::make('asignaciones_count')
                    ->label('Guardias')
                    ->counts('asignaciones')
                    // Cero guardias es un hueco de cobertura: se resalta.
                    // Se usa $record y no $state: una columna solo inyecta
                    // livewire, record y rowLoop en sus closures.
                    ->color(fn ($record) => ($record->asignaciones_count ?? 0) > 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('pf_dia_semana')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar franja')
                    ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => PerfilPanel::puedeAdministrarLocales()),
            ]);
    }
}

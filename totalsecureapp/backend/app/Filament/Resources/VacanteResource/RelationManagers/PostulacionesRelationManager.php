<?php

namespace App\Filament\Resources\VacanteResource\RelationManagers;

use App\Services\VacanteService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Modules\Administracion\Models\TurnoPostulacion;

/**
 * Los guardias que se ofrecieron para cubrir el turno.
 *
 * Se muestran las horas que cada uno ya tiene programadas en el mes. Sin ese
 * dato, la cobertura termina cayendo siempre en el mismo, que es el que mira más
 * el teléfono, no el que más lo necesita ni el que mejor descansó.
 */
class PostulacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'postulaciones';
    protected static ?string $recordTitleAttribute = 'tp_id';
    protected static ?string $title = 'Postulaciones';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('usuario.usu_nmbcom')->label('Guardia')->searchable(),
                TextColumn::make('usuario.usu_cedula')->size('sm')->label('Cédula'),
                TextColumn::make('horas_mes')
                    ->label('Horas del mes')
                    ->getStateUsing(fn (TurnoPostulacion $record) => app(VacanteService::class)
                        ->horasDelMes((int) $record->tp_usu_id) . ' h'),
                TextColumn::make('tp_ocurrido_en')
                    ->label('Se postuló')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TurnoPostulacion $record) => $record->tp_sincronizado_en
                        && $record->tp_ocurrido_en
                        && $record->tp_sincronizado_en->diffInMinutes($record->tp_ocurrido_en) > 5
                        ? 'Sincronizado después'
                        : null),
                BadgeColumn::make('tp_estado')
                    ->label('Estado')
                    ->colors([
                        'warning'   => TurnoPostulacion::POSTULADO,
                        'success'   => TurnoPostulacion::ACEPTADA,
                        'secondary' => static fn ($state): bool => in_array(
                            $state,
                            [TurnoPostulacion::RECHAZADA, TurnoPostulacion::RETIRADA],
                            true
                        ),
                    ]),
            ])
            ->defaultSort('tp_id')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisponibilidadExtrasResource\Pages;
use App\Support\PerfilPanel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Acceso\Models\users;

/**
 * Quien esta dispuesto a cubrir turnos extra.
 *
 * ⚠️ **Este interruptor no se podia tocar desde ningun lado del panel.** El
 * campo `usu_acepta_extras` existia, la API lo leia y lo escribia, y decide dos
 * cosas grandes: si el guardia ve las vacantes abiertas y si entra en la
 * convocatoria por WhatsApp cuando un puesto queda vacio. Pero solo se activaba
 * desde el Perfil dentro de la app -- y **la app vive en las tablets de los
 * puestos, no en el telefono del guardia**, asi que ofrecerse para turnos extra
 * obligaba a ir hasta un puesto.
 *
 * Vive en el grupo Operacion, junto a Turnos y Cobertura, porque es ahi donde se
 * usa: cuando hay que cubrir un puesto, lo primero es saber a quien se le puede
 * ofrecer.
 */
class DisponibilidadExtrasResource extends Resource
{
    protected static ?string $model = users::class;

    protected static ?string $navigationLabel = 'Disponibilidad para extras';
    protected static ?string $modelLabel = 'guardia';
    protected static ?string $pluralModelLabel = 'guardias';
    protected static ?int $navigationSort = 4;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-hand-raised';

    public static function getNavigationGroup(): ?string
    {
        return 'Operación';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('usu_cedula')->label('Cédula')->searchable()->size('sm'),
                TextColumn::make('usu_nmbcom')->label('Nombre')->searchable()->sortable()->size('sm'),

                TextColumn::make('usu_whatsapp')
                    ->label('WhatsApp')
                    ->size('sm')
                    // Sin numero cargado el aviso no llega, y eso explica por que
                    // un guardia disponible nunca contesta una convocatoria.
                    ->placeholder('sin número')
                    ->toggleable(),

                ToggleColumn::make('usu_acepta_extras')
                    ->label('Cubre extras'),

                ToggleColumn::make('usu_acepta_whatsapp')
                    ->label('Autoriza WhatsApp')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('usu_acepta_extras')->label('Cubre extras'),
                TernaryFilter::make('usu_acepta_whatsapp')->label('Autoriza WhatsApp'),
            ])
            ->defaultSort('usu_nmbcom')
            ->paginated([25, 50, 100]);
    }

    /**
     * Solo personal activo, y acotado por el alcance de quien mira: un
     * Supervisor gestiona la disponibilidad de su gente, no la de otro cliente.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('usu_state', 1);

        $locales = PerfilPanel::localesVisibles();

        if ($locales === null) {
            return $query;
        }

        if (empty($locales)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($q) use ($locales) {
            $q->select(\Illuminate\Support\Facades\DB::raw(1))
              ->from('user_has_institucion')
              ->whereColumn('user_has_institucion.ui_usu_id', 'users.id')
              ->where('user_has_institucion.ui_state', 1)
              ->whereIn('user_has_institucion.ui_ins_code', $locales);
        });
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeAsignarCobertura();
    }

    public static function canCreate(): bool
    {
        // Las personas se dan de alta en Usuarios. Aca solo se marca quien puede
        // cubrir extras.
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisponibilidadExtras::route('/'),
        ];
    }
}

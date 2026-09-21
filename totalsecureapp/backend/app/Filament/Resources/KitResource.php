<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KitResource\Pages;
use App\Filament\Resources\KitResource\RelationManagers\ItemsRelationManager;
use App\Filament\Tables\Descarga;
use App\Filament\Tables\FiltroDeEstado;
use App\Services\Inventario\AplicadorDeKit;
use App\Support\PerfilPanel;
use Filament\Actions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Modules\Administracion\Models\Kit;
use Modules\Administracion\Models\OrganizacionInstitucion;

/**
 * El kit de puesto: que equipo lleva un puesto y cuanto de cada cosa.
 *
 * **Por que existe.** Habia 132 listas de inventario y **130 identicas** --los
 * mismos 4 productos, cantidad 1--: una plantilla copiada a mano, con sus
 * erratas (`SEGURIDA FISICA` junto a `SEGURIDAD FISICA`). Dar inventario a un
 * local nuevo eran ~22 interacciones y agregar un producto a todos, 132
 * ediciones.
 *
 * ⚠️ **El kit no reemplaza a las listas: las escribe.** `inv_lista` e
 * `inv_lista_item` conservan su forma, porque son lo que lee la app del guardia
 * y contra lo que se registran los 23.799 movimientos. Ver
 * `App\Services\Inventario\AplicadorDeKit`.
 *
 * Un local puede apartarse del kit; entonces queda marcado y **deja de recibir
 * sus cambios**, que es lo que hace visible la excepcion. Antes un puesto
 * distinto era indistinguible del resto.
 */
class KitResource extends Resource
{
    protected static ?string $model = Kit::class;

    protected static ?string $slug = 'inv-kits';

    protected static string | \UnitEnum | null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kit de puesto';
    protected static ?string $pluralModelLabel = 'kits de puesto';
    protected static ?string $navigationLabel = 'Kits de puesto';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('ki_nombre')
                ->label('Nombre')
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true)
                ->helperText('Como lo llama la operación: «Seguridad física», «Seguridad aeroportuaria».'),

            Toggle::make('ki_activo')->label('Activo')->default(true),

            Textarea::make('ki_descripcion')
                ->label('Descripción')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ki_nombre')->label('Kit')->size('sm')
                    ->searchable()->sortable(),

                TextColumn::make('items_count')->label('Productos')->size('sm')
                    ->counts('items'),

                TextColumn::make('listas_count')->label('Puestos')->size('sm')
                    ->counts('listas')
                    ->tooltip('Locales cuya lista sale de este kit'),

                /*
                 * La columna que hace util todo esto: cuantos puestos se
                 * apartaron. Antes una excepcion era indistinguible del resto y
                 * solo se descubria mirando lista por lista.
                 */
                TextColumn::make('modificadas')->label('Apartados')->size('sm')
                    ->state(fn (Kit $r) => $r->listas()->where('li_modificada', true)->count())
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->tooltip('Puestos que cambiaron su lista y ya no siguen al kit'),

                TextColumn::make('ki_descripcion')->label('Descripción')->size('sm')
                    ->wrap()->limit(60)->toggleable(),
            ])
            ->filters([
                FiltroDeEstado::make('ki_activo', true, 'Estado'),
            ])
            ->actions([
                Actions\EditAction::make(),
                self::accionAplicar(),
            ])
            ->bulkActions([
                Descarga::enLote('inv-kits'),
            ])
            ->defaultSort('ki_nombre');
    }

    /**
     * «Aplicar a locales»: de ~22 interacciones por local a una seleccion.
     *
     * Es lo que convierte el kit en algo util y no en otra pantalla mas.
     */
    private static function accionAplicar(): Actions\Action
    {
        return Actions\Action::make('aplicar')
            ->label('Aplicar a locales')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('primary')
            ->modalHeading(fn (Kit $record) => 'Aplicar «' . $record->ki_nombre . '» a locales')
            ->modalDescription('Crea la lista de inventario de cada local elegido, o la pone al día '
                . 'si ya la tiene. Los puestos que se apartaron del kit se respetan.')
            ->schema([
                Select::make('locales')
                    ->label('Locales')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(fn () => OrganizacionInstitucion::query()
                        ->where('ins_estado', 1)
                        ->orderBy('ins_descripcion')
                        ->pluck('ins_descripcion', 'ins_code'))
                    ->helperText('Se puede elegir varios de una vez.'),

                Checkbox::make('forzar')
                    ->label('Devolver al kit también los puestos apartados')
                    ->helperText('⚠️ Pisa los cambios propios de esos puestos. Sin esto se respetan.'),
            ])
            ->action(function (Kit $record, array $data) {
                $r = app(AplicadorDeKit::class)->aplicar(
                    $record,
                    array_map('intval', $data['locales']),
                    (bool) ($data['forzar'] ?? false),
                );

                Notification::make()
                    ->title('Kit aplicado')
                    ->body(sprintf('%d creadas, %d actualizadas, %d respetadas por estar apartadas.',
                        $r['creadas'], $r['actualizadas'], $r['respetadas']))
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function canViewAny(): bool
    {
        return PerfilPanel::puedeOperar();
    }

    /** Definir el equipo estandar es configuración, no operación diaria. */
    public static function canCreate(): bool
    {
        return PerfilPanel::puedeConfigurarSistema();
    }

    /**
     * No se borra un kit del que cuelgan listas.
     *
     * Borrarlo dejaria 130 listas sin origen: seguirian funcionando --los items
     * son suyos-- pero se perderia para siempre cual era el estandar y que
     * puestos se apartaban de el.
     */
    public static function canDelete($record): bool
    {
        return $record->listas()->count() === 0;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListKits::route('/'),
            'create' => Pages\CreateKit::route('/create'),
            'edit'   => Pages\EditKit::route('/{record}/edit'),
        ];
    }
}

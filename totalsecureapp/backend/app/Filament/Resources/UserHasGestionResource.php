<?php

namespace App\Filament\Resources;

use App\Filament\Tables\FiltroDeEstado;

use App\Filament\Forms\SelectorDeUsuario;

use App\Support\PerfilPanel;
use Closure;
use App\Filament\Resources\UserHasGestionResource\Pages;
use App\Filament\Resources\UserHasGestionResource\RelationManagers;
use Filament\Tables\Actions\Action;
use Modules\Acceso\Models\user_has_gestions;
use Modules\Acceso\Models\users;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\BadgeColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Session;

class UserHasGestionResource extends Resource{

    public static function getNavigationGroup(): ?string{
        return 'Configuración';
    }
    protected static ?int $navigationSort = 4;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'gestión';
    protected static ?string $pluralModelLabel = 'gestiones';
    protected static ?string $navigationLabel = 'Gestiones';
    protected static ?string $model = user_has_gestions::class;

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila
     * dispara una consulta por relacion (N+1): con 25 filas por pagina eran
     * 126 consultas en vez de 6.
     */
    protected const RELACIONES_TABLA = ['usuario'];
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public static function form(Form $form): Form {
        return $form->schema([
            // Busca por nombre Y por cedula. Ver App\Filament\Forms\SelectorDeUsuario:
            // antes filtraba el texto de la etiqueta en el navegador, asi que
            // buscar por cedula solo funcionaba donde la etiqueta la incluia.
            SelectorDeUsuario::make('ug_user_id', 'Usuario')
                ->disabledOn('edit')
                ->required(),
            DatePicker::make('ug_ingreso')
                ->label('Ingreso')
                ->disabledOn('edit'),
            DatePicker::make('ug_egreso')
                ->label('Egreso')
                ->disabledOn('create'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ug_code')->size('sm')
                    ->label('Código')
                    ->searchable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_cedula')->size('sm')
                    ->label('Cédula')
                    ->searchable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')
                    ->label('Nombres')
                    ->searchable()
                    ->toggleable()
                    ->searchable(),
                BooleanColumn::make('ug_finish')->size('sm')
                    ->label('Finalizada')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ug_ingreso')->size('sm')
                    ->label('Inicio')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ug_egreso')->size('sm')
                    ->label('Fin')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
            ])
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('ug_finish', false, 'Gestión'),
            ])
            ->actions([
                /*Tables\Actions\EditAction::make()
                ->disabled(fn ($record) => $record->ug_egreso !== null),*/
                Action::make('Editar')
                    ->label('Editar')
                    ->visible(fn ($record) => $record->ug_finish == 0)
                    ->icon('heroicon-o-pencil')  // Icono de editar
                    ->url(fn ($record) => route('filament.resources.user-has-gestions.edit1', $record))
                    ->color('primary')
            ])
            ->bulkActions([ ])
            ;
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array {
        return [
            'index' => Pages\ListUserHasGestions::route('/'),
            'create' => Pages\CreateUserHasGestion::route('/create'),
            'edit1' => Pages\EditUserHasGestion::route('/{record}/edit'),
        ];
    }

    protected static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeGestionarPersonal();
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
        return PerfilPanel::puedeGestionarPersonal();
    }



    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(self::RELACIONES_TABLA);
    }
}

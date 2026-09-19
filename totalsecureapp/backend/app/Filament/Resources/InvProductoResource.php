<?php

namespace App\Filament\Resources;

use Filament\Actions;
use App\Filament\Tables\FiltroDeEstado;

use App\Support\PerfilPanel;

use Session;
use App\Filament\Resources\InvProductoResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Modules\Administracion\Models\ProductoCatalogo;
use Modules\Administracion\Models\UserHasInstitucion;
use App\Filament\Tables\Descarga;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms;
use Filament\Tables;

/**
 * Productos del inventario.
 *
 * Apunta a `inv_producto_catalogo` (el juego de tablas de FASE1), NO a
 * `inv_productos`. Antes leia el viejo, y la app movil escribe en el nuevo: un
 * producto creado desde el panel no existia para la tablet y al reves. Ver
 * AGENTS.md, seccion Inventario.
 *
 * **El catalogo es GLOBAL desde el 2026-09-19** (`ipc_ins_code` nulo).
 *
 * Fue por local durante un tiempo y salio mal: en produccion habia **532 filas
 * que eran 4 productos repetidos en 133 locales**. Un baston retractil es el
 * mismo objeto en los 133 sitios; lo que cambia por local es **cuantos hay**, y
 * eso ya vive en `inv_lista_item.lia_cantidad_default`. Agregar un quinto
 * producto eran 133 inserciones, y renombrar uno, 133 ediciones -- la
 * duplicacion ya se habia degradado sola.
 *
 * Fusionado con `inventario:fusionar-catalogo`, que reapunto 519 items de lista
 * y 23.349 detalles de movimiento. Ver `database/migrations/2026_09_19_100001`.
 *
 * ⚠️ **Por eso aca ya no se acota por local.** Un `whereIn('ipc_ins_code', ...)`
 * contra una columna que ahora es nula **no devuelve ninguna fila**: el catalogo
 * saldria vacio. Quien puede verlo se decide por perfil
 * (`puedeConfigurarSistema`), no por alcance geografico.
 */
class InvProductoResource extends Resource
{
    protected static ?string $model = ProductoCatalogo::class;

    /**
     * Fijado para que la URL no cambie.
     *
     * Filament deriva la ruta del nombre del modelo: al pasar de InvProducto a
     * ProductoCatalogo, `/admin/inv-productos` se habria convertido en
     * `/admin/producto-catalogos`, rompiendo enlaces y marcadores.
     */
    protected static ?string $slug = 'inv-productos';

    /**
     * Relaciones que usan las columnas de la tabla. Sin esto cada fila dispara
     * una consulta por relacion (N+1).
     */
    // El catalogo ya no cuelga de ningun local: no hay relacion que precargar.
    protected const RELACIONES_TABLA = [];

    protected static string | \UnitEnum | null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 1;
    // Filament arma con esto las migas, el boton «Crear …» y el aviso de
    // tabla vacia. Sin declararlo los deriva del nombre de la clase, y sale
    // «Producto Catalogos» o «User Has Biometrias».
    protected static ?string $modelLabel = 'producto';
    protected static ?string $pluralModelLabel = 'productos';
    protected static ?string $navigationLabel = 'Productos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            // No hay selector de local: el producto es del catalogo, no de un
            // sitio. Donde esta y cuantos hay lo dice la lista de cada local.
            TextInput::make('ipc_nombre')
                ->label('Nombre')
                ->required()
                // Unico en todo el sistema. Antes era unico DENTRO del local, que
                // es lo que permitio las 133 copias del mismo baston.
                ->unique(table: static::$model, ignoreRecord: true)
                ->helperText('Es el catálogo general: el mismo producto sirve para '
                    . 'todos los locales. La cantidad de cada local va en su lista.'),
            TextInput::make('ipc_especificacion')
                ->label('Especificación')
                ->required(),
            Textarea::make('ipc_descripcion')
                ->label('Descripción'),
            Toggle::make('ipc_activo')
                ->label('Activo')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ipc_id')->size('sm')
                    ->label('ID')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ipc_nombre')->size('sm')
                    ->label('Producto')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_especificacion')->size('sm')
                    ->label('Especificación')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_descripcion')->size('sm')
                    ->label('Descripción')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('ipc_stock_actual')->size('sm')
                    ->label('Stock')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('ipc_created_at')->size('sm')
                    ->label('Creado')
                    ->sortable()
                    ->searchable(),
                BooleanColumn::make('ipc_activo')
                    ->label('Activo')
                    ->toggleable()
                    ->searchable(false),
            ])
            ->filters([
                // Abre mostrando solo los activos. Ver App\Filament\Tables\FiltroDeEstado.
                FiltroDeEstado::make('ipc_activo', true, 'Estado'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Descarga::enLote('inv-producto'),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvProductos::route('/'),
            'create' => Pages\CreateInvProducto::route('/create'),
            'edit' => Pages\EditInvProducto::route('/{record}/edit'),
        ];
    }

    public static function canDelete($record): bool { return false; }

    public static function shouldRegisterNavigation(): bool {
        return PerfilPanel::puedeConfigurarSistema();
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
        return PerfilPanel::puedeConfigurarSistema();
    }

    /**
     * Sin acotar por local, y a proposito.
     *
     * ⚠️ El catalogo es global: `ipc_ins_code` es nulo en todas las filas. Un
     * `whereIn('ipc_ins_code', ...)` **no devolveria ninguna** y la pantalla
     * saldria vacia sin ningun error. Quien entra aca ya esta limitado por
     * `canViewAny()`, que exige perfil de Sistemas.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}

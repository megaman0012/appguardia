<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AvisoResource\Pages;
use App\Support\PerfilPanel;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Administracion\Models\AvisoEnvio;

/**
 * Qué avisos salieron, a quién y por dónde.
 *
 * Cuando un puesto amanece vacío, la primera pregunta es "¿le avisamos a
 * alguien?". Sin esta pantalla la única respuesta posible sería "debería haber
 * salido". Acá se ve el intento, haya llegado o no.
 */
class AvisoResource extends Resource
{
    protected static ?string $model = AvisoEnvio::class;
    protected static ?string $navigationLabel = 'Avisos enviados';
    protected static ?string $modelLabel = 'aviso';
    protected static ?string $pluralModelLabel = 'avisos';
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?int $navigationSort = 9;
    protected static ?string $slug = 'avisos';

    protected const RELACIONES_TABLA = ['usuario'];

    public static function getNavigationGroup(): ?string
    {
        return 'Operacion';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->size('sm')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('usuario.usu_nmbcom')->size('sm')->label('Destinatario')->searchable(),
                BadgeColumn::make('ae_canal')
                    ->label('Canal')
                    ->colors(['success' => 'whatsapp', 'primary' => 'push', 'secondary' => 'bitacora']),
                TextColumn::make('ae_destino')->size('sm')->label('Número')->default('—')->toggleable(),
                TextColumn::make('ae_titulo')->size('sm')->label('Aviso')->limit(30),
                BadgeColumn::make('ae_resultado')
                    ->label('Resultado')
                    ->enum(AvisoEnvio::RESULTADOS)
                    ->colors([
                        'success'   => AvisoEnvio::ENVIADO,
                        'danger'    => AvisoEnvio::FALLIDO,
                        'secondary' => AvisoEnvio::OMITIDO,
                    ]),
                TextColumn::make('ae_detalle')
                    ->size('sm')
                    ->label('Motivo')
                    // El motivo es lo que dice si hay que cargar un dato o
                    // levantar un servicio.
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('ae_tv_id')->size('sm')->label('Vacante')->toggleable(),
            ])
            ->defaultSort('ae_id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('ae_canal')
                    ->label('Canal')
                    ->options(['push' => 'Push', 'whatsapp' => 'WhatsApp', 'bitacora' => 'Bitácora']),
                Tables\Filters\SelectFilter::make('ae_resultado')
                    ->label('Resultado')
                    ->options(AvisoEnvio::RESULTADOS),
                Tables\Filters\Filter::make('no_llegaron')
                    ->label('Solo los que no llegaron')
                    ->query(fn (Builder $query) => $query->where('ae_resultado', '!=', AvisoEnvio::ENVIADO)),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAvisos::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
     * El alcance se hereda de la vacante que originó el aviso.
     *
     * Un aviso sin vacante (una prueba de envío, por ejemplo) solo lo ve quien
     * no tiene filtro, porque no hay local contra el cual acotarlo.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(self::RELACIONES_TABLA);

        $locales = null;

        if (PerfilPanel::alcanceEsPorInstitucion()) {
            $locales = \Modules\Administracion\Models\UserHasInstitucion::where('ui_usu_id', \Session::get('usuID'))
                ->where('ui_state', 1)
                ->pluck('ui_ins_code')
                ->all();
        } else {
            $locales = PerfilPanel::localesDelUsuario();
        }

        if ($locales === null) {
            return $query;
        }

        if (empty($locales)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('vacante', fn ($q) => $q->whereIn('tv_ins_code', $locales));
    }
}

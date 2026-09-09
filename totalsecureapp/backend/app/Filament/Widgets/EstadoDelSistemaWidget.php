<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InstitucionMarcadoresResource;
use App\Filament\Resources\OrganizacionInstitucionResource;
use App\Filament\Resources\UserHasRolesResource;
use App\Filament\Widgets\Concerns\AcotaPorAlcance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

/**
 * Lo que esta mal configurado y nadie ve.
 *
 * **Es la fila que sirve HOY.** El resto del escritorio mide operacion, y la
 * operacion recien arranca; esto mide huecos de configuracion, que existen desde
 * el primer dia y no se notan hasta que causan un problema:
 *
 *  - Un local sin punto QR acepta marcajes **sin poder comprobar la ubicacion**.
 *    Al migrar habia 25 asi, y 8 de ellos ya tenian 466 marcajes.
 *  - Un guardia sin perfil **no entra a ninguna parte**, ni al panel ni a la app,
 *    y el sintoma que llega es «no puedo entrar», no «me falta un perfil».
 *  - Un local que no registra nada en una semana puede ser un contrato que
 *    termino o un puesto abandonado. Las dos cosas hay que saberlas.
 *
 * Cada tarjeta lleva al listado donde se arregla: un numero que no dice adonde ir
 * se mira una vez y despues se ignora.
 */
class EstadoDelSistemaWidget extends StatsOverviewWidget
{
    use AcotaPorAlcance;

    protected static ?int $sort = 2;

    /*
     * ⚠️ Antes esto declaraba `$view = 'filament.widgets.stats-con-titulo'`, una
     * vista propia que existia **solo para poder poner un titulo**: Filament 2
     * no soportaba encabezado en `StatsOverviewWidget` y con dos filas de cuatro
     * tarjetas quedaban ocho numeros seguidos sin distinguir cual mide operacion
     * y cual mide configuracion.
     *
     * **Filament 3 lo trae de fabrica** con `getHeading()` y `getDescription()`,
     * asi que la vista se borro y los metodos `getEncabezado()`/`getAyuda()`
     * pasaron a los nombres de la libreria. Era uno de los arreglos que el
     * roadmap anotaba como ganancia de esta subida.
     */

    protected function getHeading(): ?string
    {
        return 'Estado del sistema';
    }

    protected function getDescription(): ?string
    {
        return 'Huecos de configuración que no dan error pero dejan datos sin poder auditar.';
    }

    /** Dias sin registrar nada para considerar un local sin actividad. */
    private const DIAS_SIN_ACTIVIDAD = 7;

    protected function getStats(): array
    {
        return [
            $this->sinPuntoQr(),
            $this->sinActividad(),
            $this->sinPerfil(),
            $this->sinUbicacionVerificada(),
        ];
    }

    /**
     * Locales sin marcador activo, y cuantos de ellos ya reciben marcajes.
     *
     * El segundo numero es el que importa: un local sin QR que todavia no opera
     * es una tarea pendiente, pero uno que ya esta marcando esta acumulando
     * asistencia que despues nadie va a poder auditar.
     */
    private function sinPuntoQr(): Card
    {
        $sinQr = $this->localesEnAlcanceQuery()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('institucion_marcadores')
                    ->whereColumn('im_ins_code', 'organizacion_institucion.ins_code')
                    ->where('im_estado', true);
            });

        $total = (clone $sinQr)->count();

        $conMarcajes = (clone $sinQr)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_has_biometria')
                    ->whereColumn('bio_ins_code', 'organizacion_institucion.ins_code');
            })
            ->count();

        return Card::make('Locales sin punto QR', $total)
            ->description($conMarcajes > 0
                ? "{$conMarcajes} ya reciben marcajes sin poder verificarse"
                : 'Ninguno con marcajes todavía')
            ->descriptionIcon($conMarcajes > 0 ? 'heroicon-s-exclamation-triangle' : null)
            ->color($conMarcajes > 0 ? 'danger' : ($total > 0 ? 'warning' : 'success'))
            ->icon('heroicon-o-qr-code')
            ->url(InstitucionMarcadoresResource::getUrl());
    }

    private function sinActividad(): Card
    {
        $desde = now()->subDays(self::DIAS_SIN_ACTIVIDAD);

        $total = $this->localesEnAlcanceQuery()
            ->where('ins_estado', true)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('user_has_biometria')
                ->whereColumn('bio_ins_code', 'organizacion_institucion.ins_code')
                ->where('bio_created_at', '>=', $desde))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('ronda_cabecera')
                ->whereColumn('rc_ins_code', 'organizacion_institucion.ins_code')
                ->where('rc_fecha_inicio', '>=', $desde))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('acceso')
                ->whereColumn('ac_ins_code', 'organizacion_institucion.ins_code')
                ->where('ac_created_at', '>=', $desde))
            ->count();

        return Card::make('Locales activos sin actividad', $total)
            ->description('Sin marcajes, rondas ni accesos en ' . self::DIAS_SIN_ACTIVIDAD . ' días')
            ->color($total > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-building-office')
            ->url(OrganizacionInstitucionResource::getUrl());
    }

    /**
     * Guardias activos sin ningun perfil.
     *
     * No se acota por local: un usuario sin perfil tampoco tiene por que tener
     * local, asi que filtrarlo por alcance lo esconderia justamente de quien
     * puede arreglarlo. Solo lo ve quien tiene alcance global.
     */
    private function sinPerfil(): Card
    {
        $visible = $this->localesEnAlcance() === null;

        $total = $visible
            ? DB::table('users')
                ->where('usu_state', 1)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('user_has_roles')
                    ->whereColumn('user_id', 'users.id'))
                ->count()
            : 0;

        return Card::make('Usuarios activos sin perfil', $visible ? $total : '—')
            ->description($visible
                ? ($total > 0 ? 'No pueden entrar a ninguna parte' : 'Todos tienen perfil')
                : 'Solo visible con alcance global')
            ->color($visible && $total > 0 ? 'danger' : 'success')
            ->icon('heroicon-o-user-minus')
            ->url($visible ? UserHasRolesResource::getUrl() : null);
    }

    /**
     * Marcajes que entraron sin poder comprobar la geocerca.
     *
     * `false` es «se acepto sin comprobar»; `null` es «fila anterior a la
     * migracion, no se sabe» y no se cuenta: v1 nunca comprobo la geocerca, asi
     * que contar sus 12.664 marcajes aqui llenaria la tarjeta de un problema que
     * ya no se puede arreglar.
     */
    private function sinUbicacionVerificada(): Card
    {
        $q = DB::table('user_has_biometria')
            ->where('bio_ubicacion_verificada', false)
            ->where('bio_created_at', '>=', now()->subDays(30));

        $locales = $this->localesEnAlcance();
        if ($locales !== null) {
            $q = empty($locales) ? $q->whereRaw('1 = 0') : $q->whereIn('bio_ins_code', $locales);
        }

        $total = $q->count();

        return Card::make('Marcajes sin verificar (30 días)', $total)
            ->description($total > 0
                ? 'El local no tenía punto QR al momento de marcar'
                : 'Todos los marcajes recientes se pudieron comprobar')
            ->color($total > 0 ? 'warning' : 'success')
            ->icon('heroicon-o-map-pin');
    }

    /** Locales dentro del alcance del perfil, como consulta reutilizable. */
    private function localesEnAlcanceQuery()
    {
        $q = DB::table('organizacion_institucion');
        $locales = $this->localesEnAlcance();

        if ($locales === null) {
            return $q;
        }

        return empty($locales) ? $q->whereRaw('1 = 0') : $q->whereIn('ins_code', $locales);
    }
}

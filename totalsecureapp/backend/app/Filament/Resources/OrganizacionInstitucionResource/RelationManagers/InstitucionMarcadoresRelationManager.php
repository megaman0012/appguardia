<?php

namespace App\Filament\Resources\OrganizacionInstitucionResource\RelationManagers;

use App\Filament\Resources\InstitucionMarcadoresResource;
use App\helpers;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\Concerns\CanCreate;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Support\Actions\Modal\Actions\Action as ModalAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\Actions\Action as FormAction;
class InstitucionMarcadoresRelationManager extends RelationManager
{

    protected static string $relationship = 'marcadores';

    protected static ?string $recordTitleAttribute = 'InstitucionMarcadores';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('im_ins_code')
                    ->default(fn ($livewire) => $livewire->ownerRecord->ins_code ?? null),

                Select::make('im_tipo')
                    ->label('Tipo')
                    ->options([
                        'Entrada' => 'Entrada',
                        'Punto Control' => 'Punto Control'
                    ])
                    ->required()
                    ->reactive(),

                TextInput::make('im_descripcion')
                    ->label('Descripción')
                    ->required(),

                TextInput::make('im_lat')
                    ->id('im_lat_input')
                    ->label('Latitud')
                    ->numeric()
                    ->required()
                    ->reactive(),

                TextInput::make('im_lng')
                    ->id('im_lng_input')
                    ->label('Longitud')
                    ->numeric()
                    ->required()
                    ->reactive(),

                Placeholder::make('mapa_preview')
                    ->label('Ubicación')
                    ->content(function () {
                        return new \Illuminate\Support\HtmlString('
                        <div wire:ignore id="map-container" style="position: relative;">
                            <div id="leaflet-map" style="width:100%; height:350px; border: 1px solid #d1d5db; border-radius: 8px; z-index: 1;"></div>
                            <button type="button" id="gps-btn" style="margin-top: 10px; padding: 8px 15px; background: #4f46e5; color: white; border-radius: 5px; cursor: pointer; border: none; font-weight: bold;">
                                📍 Obtener Ubicación GPS
                            </button>
                        </div>

                        <script>
                        (function() {
                            let map, marker;

                            function initMap() {
                                const latInp = document.getElementById("im_lat_input");
                                const lngInp = document.getElementById("im_lng_input");

                                let lat = latInp && latInp.value ? parseFloat(latInp.value) : 0;
                                let lng = lngInp && lngInp.value ? parseFloat(lngInp.value) : 0;

                                // Si el mapa ya existe, solo refrescamos su tamaño
                                if (map) {
                                    map.invalidateSize();
                                    return;
                                }

                                map = L.map("leaflet-map").setView([lat, lng], 16);
                                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                                    maxZoom: 19,
                                }).addTo(map);

                                marker = L.marker([lat, lng], { draggable: true }).addTo(map);

                                // Función para pasar datos de JS a Livewire
                                function sync(newLat, newLng) {
                                    latInp.value = newLat;
                                    lngInp.value = newLng;
                                    latInp.dispatchEvent(new Event("input"));
                                    lngInp.dispatchEvent(new Event("input"));
                                }

                                marker.on("dragend", function() {
                                    const p = marker.getLatLng();
                                    sync(p.lat.toFixed(8), p.lng.toFixed(8));
                                });

                                document.getElementById("gps-btn").addEventListener("click", function() {
                                    if (navigator.geolocation) {
                                        navigator.geolocation.getCurrentPosition(function(position) {
                                            const lt = position.coords.latitude;
                                            const lg = position.coords.longitude;

                                            map.setView([lt, lg], 17);
                                            marker.setLatLng([lt, lg]);
                                            sync(lt.toFixed(8), lg.toFixed(8));

                                            // Forzar a Leaflet a recalcular el tamaño tras la actualización
                                            setTimeout(() => { map.invalidateSize(); }, 100);
                                        });
                                    }
                                });

                                // Arreglar cuadros grises al cargar
                                setTimeout(() => { map.invalidateSize(); }, 500);
                            }

                            if (!window.L) {
                                let link = document.createElement("link");
                                link.rel="stylesheet"; link.href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
                                document.head.appendChild(link);
                                let script = document.createElement("script");
                                script.src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
                                script.onload = initMap;
                                document.body.appendChild(script);
                            } else {
                                initMap();
                            }
                        })();
                        </script>
                    ');
                    })
                    ->columnSpan('full'),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('im_code')->size('sm')
                    ->label('Codigo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('institucion.organizacionSede.sede.ps_descripcion')->size('sm')
                    ->label('Sede')
                    ->toggleable()
                    ->searchable(false),
                TextColumn::make('institucion.organizacionSede.organizacion.org_descripcion')->size('sm')
                    ->label('Organizacion')
                    ->toggleable()
                    ->searchable(false),
                TextColumn::make('institucion.ins_descripcion')->size('sm')
                    ->label('Institucion')
                    ->toggleable()
                    ->searchable(false),
                TextColumn::make('im_tipo')->size('sm')
                    ->label('Tipo')
                    ->searchable(false),
                TextColumn::make('im_descripcion')->size('sm')
                    ->label('Descripción')
                    ->searchable(),
                TextColumn::make('im_lat')->size('sm')
                    ->label('Latitud')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('im_lng')->size('sm')
                    ->label('Longitud')->toggleable(isToggledHiddenByDefault: true),
                BooleanColumn::make('im_estado')
                    ->label('Activo')
                    ->toggleable(),
            ])
            ->filters([])
            ->actions([
                Action::make('gmap')
                    ->label('Mapa')
                    ->url(fn($record) => "https://www.google.com/maps?q={$record->im_lat},{$record->im_lng}")
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map')
                    ->color('primary'),
                Action::make('qrcode')
                    ->label('qrcode')
                    ->url(fn ($record) => route('qrcode.point', helpers::CryptCypher($record->im_code)))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-qrcode')
                    ->color('primary'),
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['im_updated_user'] = auth()->id();
                        return $data;
                    })
                    ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                        helpers::control_log_filament($record->toArray(), 'InstitucionMarcadoresRelationManager', 'Edit','NOTICE', 'Editar Institucion Marcadores RelationManager');
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo Marcador')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['im_created_user'] = auth()->id();
                        $data['im_updated_user'] = auth()->id();
                        return $data;
                    })
                    ->after(function (\Illuminate\Database\Eloquent\Model $record) {
                        helpers::control_log_filament($record->toArray(), 'InstitucionMarcadoresRelationManager', 'Create','NOTICE', 'Crear Institucion Marcadores RelationManager');
                    }),
            ])
            ->bulkActions([]);
    }

}

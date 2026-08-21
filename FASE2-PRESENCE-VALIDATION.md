# FASE 2 — Servicio Central de Validación de Presencia

> **Estado:** 📋 Documentación Completada (2026-08-20)
> **Objetivo:** Un único servicio (`PresenceValidationService`) usado por Biometría, Rondas y Acceso para validar QR + GPS + geocerca.
> **Dependencias:** Fase 0 (completada)
> **Estimación:** 2-3 días

---

## 1. Análisis del Estado Actual

### 1.1 Lógica de Validación Actual por Controlador

| Controlador | Validación QR | Validación GPS | Cálculo Distancia | Validación Geocerca | Cooldown |
|-------------|---------------|----------------|-------------------|---------------------|----------|
| **BiometriaController** | ❌ No aplica | ❌ Solo captura | ❌ No calcula | ❌ No valida | ❌ No tiene |
| **RondaController** | ✅ Descifrado AES | ❌ Solo captura | ❌ No calcula | ❌ No valida | ✅ 5 min |
| **AccesoController** | ❌ No aplica | ❌ Solo captura | ❌ No calcula | ❌ No valida | ❌ No tiene |

### 1.2 Código Duplicado Identificado

**Validación de institución (copiado en TODOS los controladores):**
```php
// Este patrón se repite 7 veces en 3 controladores
$ins = UserHasInstitucion::where('ui_usu_id', $us->id)
    ->where('ui_ins_code', $request->institucion)
    ->where('ui_state', 1)
    ->first();
if(!$ins){
    return $this->message_json('errors', 'Usuario no vinculado a institucion');
}
```

**Ubicaciones exactas del código duplicado:**
- `BiometriaController.php:37-40`
- `RondaController.php:83-86`
- `RondaController.php:127-130`
- `RondaController.php:197-200`
- `RondaController.php:236-239`
- `RondaController.php:298-301`
- `AccesoController.php:53-56`
- `AccesoController.php:143-146`
- `AccesoController.php:186-189`

### 1.3 Descifrado QR (solo en RondaController)

```php
// RondaController.php:302-307
$dcTxt = $this->aesCypher($request->rc_qr, 2);
$partes = explode('_', $dcTxt);
if (!isset($partes[1]) || $partes[1] !== "TS") {
    return $this->message_json('errors', 'QrCode no fue generado en el sistema');
}
$codMark = $partes[0];
```

**Problemas:**
- Lógica de descifrado acoplada al controlador
- Formato QR hardcodeado (`"_TS"`)
- No hay validación de integridad del QR

---

## 2. Diseño del PresenceValidationService

### 2.1 Interfaz del Servicio

```php
<?php

namespace App\Services;

use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\OrganizacionInstitucion;

class PresenceValidationService
{
    /**
     * Resultado de una validación de presencia
     */
    public array $resultado = [
        'valido'       => false,
        'distancia_m'  => 0.0,
        'motivo'       => '',
        'marcador'     => null,
    ];

    /**
     * Validar presencia completa: QR + GPS + Geocerca
     *
     * @param string $qrCode        Código QR escaneado (cifrado)
     * @param float  $latitud       Latitud del dispositivo
     * @param float  $longitud      Longitud del dispositivo
     * @param int    $institucionId Código de la institución
     * @param int    $radioTolerancia Metros de tolerancia (default: 100)
     * @return array Resultado de validación
     */
    public function validarPresencia(
        string $qrCode,
        float $latitud,
        float $longitud,
        int $institucionId,
        int $radioTolerancia = 100
    ): array;

    /**
     * Solo validar GPS sin QR (para biometría)
     */
    public function validarUbicacion(
        float $latitud,
        float $longitud,
        int $institucionId,
        int $radioTolerancia = 100
    ): array;

    /**
     * Descifrar y validar código QR
     */
    public function descifrarQR(string $qrCode, int $institucionId): ?InstitucionMarcadores;

    /**
     * Calcular distancia entre dos puntos (Haversine)
     */
    public function calcularDistancia(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float;

    /**
     * Verificar si punto está dentro de geocerca
     */
    public function dentroDeGeocerca(
        float $latitud, float $longitud,
        float $marcaLat, float $marcaLng,
        int $radioMetros
    ): bool;
}
```

### 2.2 Fórmula Haversine (cálculo de distancia)

```php
/**
 * Calcular distancia entre dos puntos GPS en metros
 * Fórmula de Haversine
 */
public function calcularDistancia(
    float $lat1, float $lng1,
    float $lat2, float $lng2
): float {
    $radioTierra = 6371000; // metros

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $radioTierra * $c; // distancia en metros
}
```

### 2.3 Flujo de Validación

```
┌─────────────────────────────────────────────────────────────┐
│                    ENTRADA                                   │
│  qr_code (opcional) + latitud + longitud + institucion_id   │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  1. VALIDAR INSTITUCIÓN                                     │
│     └─ UserHasInstitucion → usuario vinculado?              │
│        └─ NO → Return error "Usuario no vinculado"          │
└──────────────────────────┬──────────────────────────────────┘
                           │ SI
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  2. SI HAY QR → VALIDAR QR                                  │
│     └─ descifrarQR() → Marcador válido?                     │
│        └─ NO → Return error motivo específico               │
│     └─ Marcador pertenece a institución?                    │
│        └─ NO → Return error "QR de otra institución"        │
│     └─ Marcador activo?                                     │
│        └─ NO → Return error "Marcador inactivo"             │
└──────────────────────────┬──────────────────────────────────┘
                           │ SI (o no hay QR)
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  3. CALCULAR DISTANCIA                                      │
│     └─ Haversine(lat/lng dispositivo, lat/lng marcador)     │
│     └─ Si no hay marcador → validar contra institución      │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  4. VALIDAR GEOCERCA                                        │
│     └─ distancia <= radio_tolerancia?                        │
│        └─ NO → Return error "Fuera de geocerca (Xm)"       │
└──────────────────────────┬──────────────────────────────────┘
                           │ SI
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  5. RESULTADO VÁLIDO                                        │
│     └─ valido: true                                         │
│     └─ distancia_m: X.X                                     │
│     └─ marcador: {id, descripcion, tipo}                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Cambios en Base de Datos

### 3.1 Nueva migración: Agregar radio_tolerancia a institución

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRadioToleranciaToInstitucion extends Migration
{
    public function up(): void
    {
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->integer('ins_radio_tolerancia_metros')->default(100)->after('ins_estado');
            $table->comment('Radio de tolerancia en metros para validación de presencia');
        });
    }

    public function down(): void
    {
        Schema::table('organizacion_institucion', function (Blueprint $table) {
            $table->dropColumn('ins_radio_tolerancia_metros');
        });
    }
}
```

### 3.2 Modelo Actualizado

```php
// OrganizacionInstitucion.php - Agregar a $fillable y $casts
protected $fillable = [
    // ... campos existentes
    'ins_radio_tolerancia_metros',
];

protected $casts = [
    // ... casts existentes
    'ins_radio_tolerancia_metros' => 'integer',
];
```

---

## 4. Refactorización de Controladores

### 4.1 BiometriaController (Refactorizado)

```php
<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\PresenceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;
use Modules\Administracion\Models\user_has_biometria;
use Modules\Administracion\Models\UserHasInstitucion;

class BiometriaController extends Controller
{
    use generalTrait;

    protected PresenceValidationService $presenceService;

    public function __construct(PresenceValidationService $presenceService)
    {
        $this->presenceService = $presenceService;
    }

    protected array $biometrix = [
        'rules' => [
            'file' => 'required',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            'is_entrada' => 'required|boolean',
            'institucion' => 'required|integer',
        ],
        'messages' => [
            'file.required' => 'Archivo de imagen es obligatorio',
            'latitud.required' => 'Ubicacion latitud es obligatorio',
            'longitud.required' => 'Ubicacion longitud es obligatorio',
            'is_entrada.required' => 'Campo tipo marcacion es obligatorio',
            'institucion.required' => 'Campo institucion es obligatorio',
        ],
    ];

    public function biometria(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        // Validar institución (usando servicio)
        $validarInst = $this->presenceService->validarUbicacion(
            $request->latitud,
            $request->longitud,
            $request->institucion
        );

        if (!$validarInst['valido']) {
            return $this->message_json('errors', $validarInst['motivo']);
        }

        // Validar campos
        $validator = Validator::make($request->all(), $this->biometrix['rules'], $this->biometrix['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        // Guardar imagen
        $file = $request->file('file');
        list($fileMoved, $fileName) = $this->storeFiles('biometria', $file, $us->id.'_'.$tk->tokenable_gs);
        if (!$fileMoved) {
            return $this->message_json('errors', 'Error al cargar imagen a servidor');
        }

        // Registrar biometría
        $biox = new user_has_biometria;
        $biox->bio_user_id = $us->id;
        $biox->bio_ug_code = $tk->tokenable_gs;
        $biox->bio_image_name = $fileName;
        $biox->bio_lat = $request->latitud;
        $biox->bio_lng = $request->longitud;
        $biox->bio_is_entrada = $request->is_entrada;
        $biox->bio_ins_code = $request->institucion;
        $biox->bio_state = true;
        $biox->bio_created_user = $us->id;
        $biox->bio_updated_user = $us->id;
        $biox->save();

        return response()->json([
            'message' => 'Biometría cargada con éxito',
            'distancia_m' => $validarInst['distancia_m'],
        ]);
    }
}
```

### 4.2 RondaController - Método detalle_qrcode (Refactorizado)

```php
/**
 * Antes (Líneas 290-344):
 * - Validación QR acoplada al controlador
 * - Sin cálculo de distancia
 * - Sin validación de geocerca
 *
 * Después:
 * - Usa PresenceValidationService
 * - Validación completa QR + GPS + Geocerca
 */
public function detalle_qrcode(Request $request): JsonResponse
{
    list($us, $tk) = $this->getSanctumSession($request);

    $validator = Validator::make($request->all(), $this->rondaxDetallexQrCode['rules'], $this->rondaxDetallexQrCode['messages']);
    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()]);
    }

    // Validación centralizada de presencia
    $validacion = $this->presenceService->validarPresencia(
        $request->rc_qr,
        $request->rd_lat,
        $request->rd_lng,
        $request->ins_code
    );

    if (!$validacion['valido']) {
        return $this->message_json('errors', $validacion['motivo']);
    }

    $marcador = $validacion['marcador'];

    // Verificar cooldown (5 minutos)
    $ronDet = ronda_detalle::where('rd_im_code', $marcador->im_code)
        ->where('rd_usu_id', $us->id)
        ->where('rd_ins_code', $request->ins_code)
        ->orderBy('rd_id', 'desc')
        ->first();

    if ($ronDet) {
        $fechaRegistro = Carbon::parse($ronDet->rd_fecha_hora);
        $ahora = Carbon::now();
        $diferencia = $ahora->diffInMinutes($fechaRegistro);
        if ($diferencia < 5) {
            return $this->message_json('errors', 'Ya registro este marcador espere 5 minutos');
        }
    }

    // Registrar detalle
    try {
        $rd = new ronda_detalle();
        $rd->rd_usu_id = $us->id;
        $rd->rd_ug_code = $tk->tokenable_gs;
        $rd->rd_ins_code = $request->ins_code;
        $rd->rd_rc_id = $request->rc_id;
        $rd->rd_im_code = $marcador->im_code;
        $rd->rd_observacion = $marcador->im_descripcion;
        $rd->rd_fecha_hora = date('Y-m-d H:i:s');
        $rd->rd_lat = $request->rd_lat;
        $rd->rd_lng = $request->rd_lng;
        $rd->rd_estado = 1;
        $rd->rd_created_user = $us->id;
        $rd->rd_updated_user = $us->id;
        $rd->save();

        return response()->json([
            'result' => 'success',
            'message' => 'Novedad Cargada Correctamente',
            'distancia_m' => $validacion['distancia_m'],
        ]);
    } catch (\Exception $e) {
        return $this->message_json('errors', $e->getMessage());
    }
}
```

### 4.3 AccesoController (Refactorizado)

```php
<?php

namespace Modules\MobileApp\Http\Controllers;

use App\generalTrait;
use App\Services\PresenceValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;
use Modules\Administracion\Models\Acceso;
use Modules\Administracion\Models\AccesoPersona;
use Modules\Administracion\Models\UserHasInstitucion;

class AccesoController extends Controller
{
    use generalTrait;

    protected PresenceValidationService $presenceService;

    public function __construct(PresenceValidationService $presenceService)
    {
        $this->presenceService = $presenceService;
    }

    protected array $accesox = [
        'rules' => [
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
            'institucion' => 'required|integer',
            'tipoAc' => 'required|integer',
            'identificacion' => 'required',
            'nombres' => 'required',
            'apellidos' => 'required',
            'nombAcomp' => 'required_if:isAcomp,true',
            'patente' => 'required_if:tipoAc,4',
        ],
        'messages' => [
            'latitud.required' => 'Campo latitud es obigatorio',
            'longitud.required' => 'Campo longitud es obigatorio',
            'institucion.required' => 'Campo institucion es obigatorio',
            'tipoAc.required' => 'Campo tipo de acceso es obigatorio',
            'identificacion.required' => 'Campo identificacion es obigatorio',
            'nombres.required' => 'Campo nombres es obigatorio',
            'apellidos.required' => 'Campo apellidos es obigatorio',
            'nombAcomp.required_if' => 'Campo nombre acompañante es obigatorio',
            'patente.required_if' => 'Campo patente es obligatorio',
        ],
    ];

    public function acceso(Request $request): JsonResponse
    {
        list($us, $tk) = $this->getSanctumSession($request);

        // Validación centralizada de presencia
        $validarInst = $this->presenceService->validarUbicacion(
            $request->latitud,
            $request->longitud,
            $request->institucion
        );

        if (!$validarInst['valido']) {
            return $this->message_json('errors', $validarInst['motivo']);
        }

        $validator = Validator::make($request->all(), $this->accesox['rules'], $this->accesox['messages']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        // ... resto del código igual
    }
}
```

---

## 5. Tests Unitarios

### 5.1 Archivo: `tests/Unit/PresenceValidationServiceTest.php`

```php
<?php

namespace Tests\Unit;

use App\Services\PresenceValidationService;
use Modules\Administracion\Models\InstitucionMarcadores;
use Modules\Administracion\Models\OrganizacionInstitucion;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PresenceValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PresenceValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PresenceValidationService();
    }

    /** @test */
    public function calcula_distancia_entre_dos_puntos_correctamente()
    {
        // Punto A: Quito, Ecuador (-0.1807, -78.4678)
        // Punto B: A 100 metros aproximadamente
        $distancia = $this->service->calcularDistancia(
            -0.1807, -78.4678,
            -0.1816, -78.4678
        );

        $this->assertGreaterThan(90, $distancia);
        $this->assertLessThan(110, $distancia);
    }

    /** @test */
    public function punto_dentro_de_geocerca_retorna_true()
    {
        $resultado = $this->service->dentroDeGeocerca(
            -0.1807, -78.4678,  // dispositivo
            -0.1807, -78.4678,  // marcador (mismo punto)
            100  // radio 100m
        );

        $this->assertTrue($resultado);
    }

    /** @test */
    public function punto_fuera_de_geocerca_retorna_false()
    {
        // A ~500 metros del marcador
        $resultado = $this->service->dentroDeGeocerca(
            -0.1807, -78.4678,
            -0.1850, -78.4678,
            100  // radio 100m
        );

        $this->assertFalse($resultado);
    }

    /** @test */
    public function qr_invalido_retorna_error()
    {
        $marcador = $this->service->descifrarQR('codigo_invalido', 1);

        $this->assertNull($marcador);
    }

    /** @test */
    public function qr_de_otra_institucion_retorna_error()
    {
        // Crear marcador en institución 1
        $marcador = InstitucionMarcadores::create([
            'im_ins_code' => 1,
            'im_numero' => 1,
            'im_tipo' => 'puerta',
            'im_descripcion' => 'Puerta Principal',
            'im_lat' => -0.1807,
            'im_lng' => -78.4678,
            'im_estado' => true,
            'im_created_user' => 1,
            'im_updated_user' => 1,
        ]);

        // Cifrar QR para institución 1
        $qrCifrado = \App\generalTrait::aesCypher($marcador->im_code . '_TS', 1);

        // Intentar validar desde institución 2
        $resultado = $this->service->descifrarQR($qrCifrado, 2);

        $this->assertNull($resultado);
    }

    /** @test */
    public function marcador_inactivo_retorna_error()
    {
        $marcador = InstitucionMarcadores::create([
            'im_ins_code' => 1,
            'im_numero' => 1,
            'im_tipo' => 'puerta',
            'im_descripcion' => 'Puerta Principal',
            'im_lat' => -0.1807,
            'im_lng' => -78.4678,
            'im_estado' => false,  // Inactivo
            'im_created_user' => 1,
            'im_updated_user' => 1,
        ]);

        $qrCifrado = \App\generalTrait::aesCypher($marcador->im_code . '_TS', 1);

        $resultado = $this->service->validarPresencia(
            $qrCifrado,
            -0.1807,
            -78.4678,
            1
        );

        $this->assertFalse($resultado['valido']);
        $this->assertStringContainsString('inactivo', $resultado['motivo']);
    }

    /** @test */
    public function validacion_completa_qr_gps_geocerca_exitosa()
    {
        $institucion = OrganizacionInstitucion::create([
            'ins_nombre' => 'Test Institución',
            'ins_estado' => true,
            'ins_radio_tolerancia_metros' => 100,
        ]);

        $marcador = InstitucionMarcadores::create([
            'im_ins_code' => $institucion->ins_code,
            'im_numero' => 1,
            'im_tipo' => 'puerta',
            'im_descripcion' => 'Puerta Principal',
            'im_lat' => -0.1807,
            'im_lng' => -78.4678,
            'im_estado' => true,
            'im_created_user' => 1,
            'im_updated_user' => 1,
        ]);

        $qrCifrado = \App\generalTrait::aesCypher($marcador->im_code . '_TS', 1);

        $resultado = $this->service->validarPresencia(
            $qrCifrado,
            -0.1807,  // Misma ubicación
            -78.4678,
            $institucion->ins_code
        );

        $this->assertTrue($resultado['valido']);
        $this->assertEquals(0, $resultado['distancia_m']);
        $this->assertNotNull($resultado['marcador']);
    }

    /** @test */
    public function validacion_fuera_de_radio_retorna_error()
    {
        $institucion = OrganizacionInstitucion::create([
            'ins_nombre' => 'Test Institución',
            'ins_estado' => true,
            'ins_radio_tolerancia_metros' => 100,
        ]);

        $marcador = InstitucionMarcadores::create([
            'im_ins_code' => $institucion->ins_code,
            'im_numero' => 1,
            'im_tipo' => 'puerta',
            'im_descripcion' => 'Puerta Principal',
            'im_lat' => -0.1807,
            'im_lng' => -78.4678,
            'im_estado' => true,
            'im_created_user' => 1,
            'im_updated_user' => 1,
        ]);

        $qrCifrado = \App\generalTrait::aesCypher($marcador->im_code . '_TS', 1);

        // Dispositivo a ~500 metros
        $resultado = $this->service->validarPresencia(
            $qrCifrado,
            -0.1850,
            -78.4678,
            $institucion->ins_code
        );

        $this->assertFalse($resultado['valido']);
        $this->assertStringContainsString('Fuera de geocerca', $resultado['motivo']);
        $this->assertGreaterThan(100, $resultado['distancia_m']);
    }
}
```

---

## 6. Plan de Implementación (Paso a Paso)

### Paso 1: Crear migración (15 min)
```bash
php artisan make:migration add_radio_tolerancia_to_institucion_table --table=organizacion_institucion
```
- Archivo: `database/migrations/XXXX_add_radio_tolerancia_to_institucion_table.php`
- Contenido: Ver Sección 3.1

### Paso 2: Actualizar modelo OrganizacionInstitucion (10 min)
- Archivo: `Modules/Administracion/Models/OrganizacionInstitucion.php`
- Agregar `ins_radio_tolerancia_metros` a `$fillable` y `$casts`

### Paso 3: Crear PresenceValidationService (45 min)
```bash
touch app/Services/PresenceValidationService.php
```
- Archivo: `app/Services/PresenceValidationService.php`
- Contenido: Ver Sección 2.1 + 2.2

### Paso 4: Refactorizar BiometriaController (30 min)
- Archivo: `Modules/MobileApp/Http/Controllers/BiometriaController.php`
- Cambios: Ver Sección 4.1
- Eliminar: Validación manual de institución
- Agregar: Inyección de dependencia + uso del servicio

### Paso 5: Refactorizar RondaController (45 min)
- Archivo: `Modules/MobileApp/Http/Controllers/RondaController.php`
- Cambios: Ver Sección 4.2
- Eliminar: Lógica de descifrado QR en `detalle_qrcode()`
- Agregar: Inyección de dependencia + uso del servicio

### Paso 6: Refactorizar AccesoController (30 min)
- Archivo: `Modules/MobileApp/Http/Controllers/AccesoController.php`
- Cambios: Ver Sección 4.3
- Eliminar: Validación manual de institución
- Agregar: Inyección de dependencia + uso del servicio

### Paso 7: Crear tests unitarios (60 min)
```bash
php artisan make:test PresenceValidationServiceTest --unit
```
- Archivo: `tests/Unit/PresenceValidationServiceTest.php`
- Contenido: Ver Sección 5.1

### Paso 8: Ejecutar tests y verificar (15 min)
```bash
php artisan test --unit=PresenceValidationServiceTest
```

### Paso 9: Ejecutar migración (5 min)
```bash
php artisan migrate
```

### Paso 10: Verificar endpoints existentes (15 min)
```bash
# Probar biometría
curl -X POST http://localhost:3031/api/mobile/biometria \
  -H "Authorization: Bearer {token}" \
  -F "file=@test.jpg" \
  -F "latitud=-0.1807" \
  -F "longitud=-78.4678" \
  -F "is_entrada=1" \
  -F "institucion=1"

# Probar ronda con QR
curl -X POST http://localhost:3031/api/mobile/ronda/detalle-qr \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"ins_code":1,"rc_id":1,"rc_qr":"...","rd_lat":-0.1807,"rd_lng":-78.4678}'
```

---

## 7. Checklist de Verificación

| # | Verificación | Estado |
|---|--------------|--------|
| 1 | Migración crea campo `ins_radio_tolerancia_metros` | ⬜ |
| 2 | Modelo OrganizacionInstitucion tiene el campo en $fillable | ⬜ |
| 3 | PresenceValidationService existe en `app/Services/` | ⬜ |
| 4 | Servicio tiene método `calcularDistancia()` (Haversine) | ⬜ |
| 5 | Servicio tiene método `validarPresencia()` | ⬜ |
| 6 | Servicio tiene método `validarUbicacion()` | ⬜ |
| 7 | Servicio tiene método `descifrarQR()` | ⬜ |
| 8 | BiometriaController usa PresenceValidationService | ⬜ |
| 9 | RondaController usa PresenceValidationService | ⬜ |
| 10 | AccesoController usa PresenceValidationService | ⬜ |
| 11 | Tests unitarios pasan (8+ tests) | ⬜ |
| 12 | Endpoints existentes funcionan igual | ⬜ |
| 13 | Error "Fuera de geocerca" se muestra correctamente | ⬜ |
| 14 | Error "QR de otra institución" funciona | ⬜ |
| 15 | Error "Marcador inactivo" funciona | ⬜ |

---

## 8. Rollback (Plan de Reversión)

Si algo falla:

```bash
# 1. Revertir migración
php artisan migrate:rollback

# 2. Restaurar controladores originales
git checkout -- Modules/MobileApp/Http/Controllers/BiometriaController.php
git checkout -- Modules/MobileApp/Http/Controllers/RondaController.php
git checkout -- Modules/MobileApp/Http/Controllers/AccesoController.php

# 3. Eliminar servicio
rm app/Services/PresenceValidationService.php

# 4. Eliminar tests
rm tests/Unit/PresenceValidationServiceTest.php
```

---

## 9. Documentos Relacionados

| Archivo | Descripción |
|---------|-------------|
| `planificacion_pasos.md` | Roadmap general del proyecto |
| `ANALISIS-ESQUEMA-ACTUAL.md` | Diagrama ER y esquema de BD |
| `FASE1-INVENTARIO-UNIFICADO.md` | Documentación Fase 1 |
| `RESUMEN-AVANCE.md` | Resumen para reanudar trabajo |

---

**Última actualización:** 2026-08-20

# Documentación Técnica - APP DE GUARDIA (Total Secure App)

## Resumen del Proyecto

Aplicación móvil para gestión de guardias de seguridad, construida con:
- **Frontend**: React Native (Expo SDK 57)
- **Backend**: Laravel 8.75 (PHP) con módulos
- **Base de datos**: PostgreSQL
- **Servidor**: Docker (puerto 3031)

---

## Origen de Componentes

### Tabla de Origen
| Componente | Origen | Trabajo realizado |
|------------|--------|-------------------|
| Backend Laravel (coredt360) | **MIGRADO** del original | Adaptación a PHP 8.3 + PostgreSQL |
| Panel legacy AdminLTE (`/acceso/login`) | **ORIGINAL** | Correcciones menores para nuevo entorno |
| Panel Filament (`/admin`) | **NUEVO** | Construido desde cero |
| App Expo (móvil) | **NUEVO** | Construida desde cero |
| API MobileApp (`Modules/MobileApp`) | **NUEVO** | Construido desde cero |

### Detalle de Origen

#### Backend Laravel - MIGRADO
- Proyecto original: `coredt360` / HagpAsist
- Migrado a: PHP 8.3 + PostgreSQL + Docker
- Módulos heredados: `Acceso`, `Administracion`, `Formularios`
- Estado: Funcional en Docker (puerto 3031)

#### Panel Legacy AdminLTE - ORIGINAL
- Ubicación: `/acceso/login`
- Tecnología: Vistas AdminLTE de Laravel
- Módulos: Acceso, Administracion, Formularios
- Estado: Funcional, correcciones menores aplicadas

#### Panel Filament - NUEVO
- Ubicación: `/admin`
- Tecnología: Filament 3.x
- Función: Panel administrativo moderno para gestionar entidades de la app
- Entidades: Rondas, Accesos, Novedades, Alertas, Inventario, etc.

#### App Expo - NUEVA
- Ubicación: `totalsecureapp/src/`
- Tecnología: React Native (Expo SDK 57)
- Función: App móvil para guardias de seguridad
- Estado: Compilada (APK ~99MB)

#### API MobileApp - NUEVA
- Ubicación: `backend/Modules/MobileApp/`
- Tecnología: Laravel Module
- Función: API REST para consumir desde la app móvil
- Endpoints: 25+ endpoints verificados

---

## Estructura del Proyecto

```
appguardia/
├── apk_extracted/              # APK extraído para análisis
├── appdeguardias.apk           # APK compilada
├── HISTORIAL_DE_CHAT.md        # Historial de desarrollo
└── totalsecureapp/             # Código fuente
    ├── src/                    # Frontend React Native
    │   ├── components/         # Componentes reutilizables
    │   ├── context/            # Contextos (AuthContext)
    │   ├── navigation/         # Navegación (AppNavigator)
    │   ├── screens/            # 18 pantallas
    │   ├── services/           # API y notificaciones
    │   └── utils/              # Constantes, helpers
    ├── backend/                # Backend Laravel
    │   ├── Modules/            # Módulos Laravel
    │   │   ├── MobileApp/      # API para app móvil
    │   │   ├── Acceso/         # Gestión de acceso
    │   │   ├── Administracion/ # Administración general
    │   │   └── Formularios/    # Formularios
    │   ├── routes/api.php      # Rutas principales
    │   └── .env                # Configuración
    └── app.json                # Configuración Expo
```

---

## Stack Tecnológico

### Frontend (React Native/Expo)
| Paquete | Versión | Función |
|---------|---------|---------|
| expo | ~57.0.12 | Framework React Native |
| react | 19.2.3 | UI Library |
| react-native | 0.86.2 | Runtime móvil |
| @react-navigation/native | ^7.3.16 | Navegación |
| axios | ^1.19.0 | HTTP Client |
| expo-camera | ~57.0.3 | Cámara/QR |
| expo-location | ~57.0.9 | GPS/Ubicación |
| expo-notifications | ~57.0.10 | Push Notifications |
| @react-native-async-storage/async-storage | 2.2.0 | Storage local |

### Backend (Laravel)
| Componente | Versión/Detalle |
|------------|-----------------|
| Laravel | 8.75 |
| PHP | 8.x |
| PostgreSQL | Base de datos |
| Sanctum | Tokens de autenticación |
| Filament | Panel admin (opcional) |
| Docker | Contenedor backend |

---

## Pantallas de la App (18 screens)

| Screen | Función | Endpoint API |
|--------|---------|--------------|
| LoginScreen | Inicio de sesión | POST /api/login |
| PasswordResetRequestScreen | Solicitud cambio contraseña | POST /api/solicitud_paswchg |
| PasswordResetScreen | Procesar cambio contraseña | POST /api/procesar_paswchg |
| SeleccionInstitucionScreen | Selección de institución | POST /api/instituciones |
| HomeScreen | Dashboard principal | - |
| PerfilScreen | Perfil de usuario | GET /api/user |
| RondaListScreen | Listado de rondas | POST /api/rondas |
| RondaDetalleScreen | Detalle de ronda | POST /api/rondas_detalle |
| ScannerQRScreen | Escaneo QR para rondas | POST /api/rondas_detalle_qrcode |
| AccesoListScreen | Listado de accesos | POST /api/accesosbyinst |
| AccesoFormScreen | Registro de acceso | POST /api/acceso |
| NovedadListScreen | Listado de novedades | POST /api/novedad_listbydate |
| NovedadCreateScreen | Crear novedad | POST /api/novedad_create |
| AlertasScreen | Alertas del día | POST /api/alert/today |
| InventarioScreen | Listas de inventario | POST /api/inventario/listbyinst |
| InventarioDetalleScreen | Detalle inventario | POST /api/inventario/listsave |
| BiometriaScreen | Registro biométrico | POST /api/biometria |
| ProfileSelectionScreen | **ENDPOINT NO EXISTE** | - |

---

## Endpoints API (Backend Laravel)

### Autenticación (sin auth)
| Método | Endpoint | Descripción | Controller |
|--------|----------|-------------|------------|
| POST | /api/login | Iniciar sesión | LoginController@login |
| POST | /api/solicitud_paswchg | Solicitud cambio pass | LoginController@solicitud_cambiopass |
| POST | /api/procesar_paswchg | Procesar cambio pass | LoginController@procesar_cambiopass |

### Con aut Sanctum
| Método | Endpoint | Descripción | Controller |
|--------|----------|-------------|------------|
| POST | /api/instituciones | Listar instituciones | InstitucionController@allInstitucions |
| POST | /api/biometria | Registro biométrico | BiometriaController@biometria |
| POST | /api/acceso | Registrar acceso | AccesoController@acceso |
| POST | /api/accesosbyinst | Accesos por institución | AccesoController@getAccesosByInst |
| POST | /api/accesout | Salida de acceso | AccesoController@accesOut |
| POST | /api/rondas | Listar rondas | RondaController@allRonda |
| POST | /api/rondas_gestion | Gestión de ronda | RondaController@ronda_gestion |
| POST | /api/rondas_detalle | Detalle de ronda | RondaController@detalle_by_id_ronda |
| POST | /api/rondas_detalle_gestion | Gestión detalle ronda | RondaController@detalle_gestion |
| POST | /api/rondas_detalle_qrcode | QR Code ronda | RondaController@detalle_qrcode |
| POST | /api/novedad_create | Crear novedad | NovedadController@create |
| POST | /api/novedad_listbydate | Novedades por fecha | NovedadController@listByDate |
| POST | /api/token/save | Guardar push token | NotificacionController@saveToken |
| POST | /api/token/remove | Eliminar push token | NotificacionController@removeToken |
| POST | /api/alert/today | Alertas del día | AlertaController@today |
| POST | /api/notification/institution | Notif por institución | NotificacionController@sendToInstitution |
| POST | /api/notification/user | Notif por usuario | NotificacionController@sendToUser |
| POST | /api/notification/bulk | Notif masiva | NotificacionController@sendBulk |
| POST | /api/inventario/listbyinst | Listas inventario | InventarioController@allListByInst |
| POST | /api/inventario/listsave | Guardar movimiento | InventarioController@saveListMov |
| POST | /api/inventario/finishsave | Finalizar movimiento | InventarioController@finishListMov |

---

## Consumo de Recursos

### Red
- **Protocolo**: HTTP (no HTTPS en desarrollo)
- **Puerto**: 3031
- **Host**: 192.168.100.212 (red local)
- **Timeout API**: 15 segundos
- **Autenticación**: Bearer Token (Sanctum)

### Almacenamiento Local (AsyncStorage)
| Key | Tipo | Descripción |
|-----|------|-------------|
| token | string | Token de autenticación |
| user | JSON | Datos del usuario |
| institucion | JSON | Institución seleccionada |
| pushToken | string | Token de notificaciones push |
| pushTokenIns | string | Código de institución push |

### Hardware Utilizado
| Permisios | Uso |
|-----------|-----|
| Cámara | Escaneo QR en rondas |
| Ubicación (GPS) | Registro de rondas y accesos |
| Notificaciones | Push notifications |
| Micrófono | **Declarado pero no utilizado** |

---

## Base de Datos (PostgreSQL)

### Esquema principal: public
| Tabla | Descripción |
|-------|-------------|
| users | Usuarios del sistema |
| personal_access_tokens | Tokens Sanctum |
| institutions | Instituciones |
| user_has_institucion | Relación usuario-institución |
| user_has_gestion | Gestiones asignadas |
| ronda_cabecera | Cabeceras de rondas |
| ronda_detalle | Detalles de rondas |
| institucion_marcadores | Marcadores QR |
| accesos | Registros de acceso |
| acceso_personas | Personas que acceden |
| inv_lista_producto | Listas de inventario |
| inv_lista_producto_item | Items de inventario |
| inv_movimiento | Movimientos inventario |
| inv_movimiento_detalle | Detalles movimientos |
| parametros | Parámetros del sistema |
| roles | Roles (Supervisor, Vigilante) |

---

## Configuración de Producción

### Backend (.env)
```
APP_ENV=local          # ← Cambiar a "production"
APP_DEBUG=true         # ← Cambiar a "false"
DB_HOST=127.0.0.1     # ← Servidor real
DB_DATABASE=coredt360
DB_USERNAME=totalsecure
DB_PASSWORD=***        # ← Configurar
```

### Frontend (app.json)
```json
{
  "expo": {
    "extra": {
      "apiHost": "192.168.100.212"  # ← IP del servidor
    }
  }
}
```

---

## Verificación de Funcionamiento (2026-08-18)

### Estado del Sistema

| Componente | Estado | Detalle |
|------------|--------|---------|
| Backend Docker | ✅ | Contenedores `ts_app`, `ts_db`, `ts_nginx` activos |
| API REST (puerto 3031) | ✅ | POST /api/login responde con JSON |
| Frontend Expo | ✅ | TypeScript compila sin errores |
| Web Export | ✅ | Bundle estático en `dist/` (~1MB) |
| Servidor Web (8081) | ✅ | Escucha en 0.0.0.0, accesible desde red local |
| Flujo completo | ✅ | Login → Institución → Home → Todos los módulos |

### Cómo Ejecutar el Frontend en el Navegador

**Opción 1 - Desarrollo (hot-reload):**
```bash
cd totalsecureapp
npx expo start --web --port 8081
# Abrir http://192.168.100.212:8081
```

**Opción 2 - Export estático (producción web):**
```bash
npx expo export --platform web
# Servir carpeta dist/
npx serve dist -l 8081 --no-clipboard
```

### Notas Técnicas
- El error "React Native DevTools" al iniciar Expo es normal en Linux sin GUI (Electron). No afecta la app.
- El frontend compila ~937 módulos, genera bundle de ~1MB.
- Backend Docker usa PostgreSQL 16, PHP-FPM 8.3, nginx como proxy inverso.
- Todos los endpoints API verificados funcionan correctamente.

---

## Tareas Pendientes

### 1. Firebase/Google Play (EN PROCESO)
**Estado**: NO configurado - Falta google-services.json

#### Pasos para configurar:
1. Ir a https://console.firebase.google.com/
2. Crear proyecto (usar nombre: `app-de-guardia` o similar)
3. Agregar app Android (package: `com.dt360.coreapp`)
4. Descargar `google-services.json`
5. Colocar en: `totalsecureapp/android/app/google-services.json`
6. En Firebase Console → Project Settings → General → Copiar "Project ID"
7. Configurar en app.json:
   ```json
   "extra": {
     "apiHost": "192.168.100.212",
     "eas": {
       "projectId": "TU_PROJECT_ID_AQUI"
     }
   }
   ```

**NOTA**: La app usa Expo Push Notifications, NO Firebase directamente.
El `google-services.json` es necesario para compilation pero las notificaciones
van por Expo Push Token service.

#### Archivos que se crearán:
- `android/app/google-services.json` (descargado de Firebase)
- `eas.json` (configuración de EAS Build)

### 2. Firma de Publicación
- [ ] Generar keystore propio (no debug)
- [ ] Configurar `eas.json` con credentials
- [ ] Compilar APK/AAB firmado

### 3. ProfileSelectionScreen
- [ ] Verificar si se usa o eliminar
- [ ] No tiene endpoint asociado

### 4. Configuración Producción
- [ ] Variables .env reales
- [ ] HTTPS habilitado
- [ ] CORS configurado para dominio real

### 5. Piloto
- [ ] Probar en celular real
- [ ] Verificar conectividad con backend

---

## Endpoints NO utilizados por la app

| Endpoint | Estado |
|----------|--------|
| /api/notification/institution | Solo backend |
| /api/notification/user | Solo backend |
| /api/notification/bulk | Solo backend |
| ProfileSelectionScreen | **SIN ENDPOINT** |

---

*Documento generado: 2026-08-17 | Última actualización: 2026-08-19*

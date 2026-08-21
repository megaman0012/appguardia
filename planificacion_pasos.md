# Total Secure — Análisis Técnico v2 (Real) y Roadmap de Actualización v2.0

> **Última actualización:** 2026-08-19 — Estado real del código verificado

---

## 1. Stack y Arquitectura REAL

| Componente | Tecnología | Versión |
|------------|------------|---------|
| **Frontend** | React Native (Expo) | SDK 57, TypeScript |
| **Backend** | Laravel | 8.75, PHP 8.3 |
| **Base de datos** | PostgreSQL | Puerto 5432 (Docker) |
| **Auth API** | Laravel Sanctum | Token-based (mobile) |
| **Auth Web** | Session-based | Filament/Livewire admin |
| **Docker** | docker-compose | Backend en puerto 3031 |
| **Notificaciones** | expo-notifications | Push token device-based |

### Estructura del proyecto

```
appguardia/
├── totalsecureapp/              ← Frontend React Native
│   ├── src/
│   │   ├── components/          CameraCapture.tsx
│   │   ├── context/             AuthContext.tsx (login, perfiles, instituciones)
│   │   ├── navigation/          AppNavigator.tsx (auth stack + app stack)
│   │   ├── screens/             18 pantallas
│   │   ├── services/            api.ts (axios), notifications.ts
│   │   └── utils/               constants.ts, format.ts, location.ts, AsyncStorageHelper.ts
│   ├── backend/                 ← Laravel API
│   │   ├── Modules/MobileApp/   ← API REST para la APK
│   │   │   ├── Http/Controllers/
│   │   │   ├── Routes/api.php
│   │   │   └── Models/
│   │   ├── Modules/Acceso/
│   │   ├── Modules/Administracion/
│   │   └── Modules/Formularios/
│   └── app.json
├── DOCUMENTACION_PROYECTO.md
├── HISTORIAL_DE_CHAT.md
├── planificacion_pasos.md       ← ESTE ARCHIVO
└── planificacion/
```

---

## 2. Modelo de Datos REAL (verificado en código)

```
auth_users (Sanctum)
 ├─ perfiles_usuario (id, usuario_id, perfil_id)
 │    └─ perfil (Administrador, Administrador General, Supervisor, Vigilante)
 └─ usuario_instituciones (id, usuario_id, institucion_id)

institucion (id, nombre, logo, direccion, geo_lat, geo_lng, activo)
 ├─ marcador (id, institucion_id, tipo, descripcion, lat, lng, qr_code, activo)
 ├─ lista (id, institucion_id, nombre, descripcion, activo)
 │    └─ producto (id, lista_id, nombre, descripcion, especificacion)
 ├─ movimiento (id, institucion_id, lista_id, codigo, estado, usuario_id)
 │    └─ movimiento_detalle (id, movimiento_id, producto_id, cantidad_default, cantidad_recepcion, recibido, observacion, estado)
 ├─ biometria (id, institucion_id, usuario_id, cedula, tipo, fecha, foto_url, lat, lng)
 ├─ alerta (id, institucion_id, usuario_id, codigo, estado, fecha, lat, lng)
 ├─ acceso (id, institucion_id, usuario_id, fecha_entrada, fecha_salida, entrevista, apellidos, imagen, temperatura, patente, sello, neumaticos, carro, p_carga_llave, kms)
 ├─ ronda (id, institucion_id, usuario_id, fecha_inicio, fecha_fin, estado)
 │    └─ ronda_detalle (id, ronda_id, marcador_id, fecha, imagen_url, observacion)
 └─ novedad (id, institucion_id, usuario_id, foto_url, descripcion, fecha, lat, lng)
```

### Entidades que faltan (no implementadas)

| Entidad | Propósito | Prioridad |
|---------|-----------|-----------|
| `turno` | Planificación de guardias por institución/fecha/hora | Alta |
| `acceso_vehiculo` | Separar datos vehiculares del acceso general | Media |
| `alerta_historial` | Escalamiento y tiempos de respuesta | Alta |
| `rol_permiso` (RBAC) | Reemplazar perfiles hardcodeados | Media |
| `offline_sync` | Soporte offline-first en APK | Baja |

---

## 3. Estado Real por Funcionalidad

### ✅ FUNCIONAL (completado y operativo)

| # | Funcionalidad | Frontend | Backend | Estado |
|---|--------------|----------|---------|--------|
| 1 | Login multi-perfil | `LoginScreen.tsx` | `LoginController@autenticar` | ✅ Completo |
| 2 | Selección de institución | `SeleccionInstitucionScreen.tsx` | `InstitucionController@porUsuario` | ✅ Completo |
| 3 | Home con navegación | `HomeScreen.tsx` | — | ✅ Completo |
| 4 | Perfil de usuario | `PerfilScreen.tsx` | `UserController@perfil` | ✅ Completo |
| 5 | Biometría (marcación) | `BiometriaScreen.tsx` | `BiometriaController@marcar` | ✅ Completo |
| 6 | Rondas (CRUD) | `RondaListScreen.tsx`, `RondaDetalleScreen.tsx` | `RondaController@index`, `@registrar` | ✅ Completo |
| 7 | Scanner QR | `ScannerQRScreen.tsx` | — (usa cámara local) | ✅ Completo |
| 8 | Accesos (CRUD) | `AccesoListScreen.tsx`, `AccesoFormScreen.tsx` | `AccesoController@index`, `@registrar` | ✅ Completo |
| 9 | Novedades (CRUD) | `NovedadListScreen.tsx`, `NovedadCreateScreen.tsx` | `NovedadController@index`, `@registrar` | ✅ Completo |
| 10 | Inventario (CRUD) | `InventarioScreen.tsx`, `InventarioDetalleScreen.tsx` | `InventarioController` | ✅ Completo |
| 11 | Alertas (listado) | `AlertasScreen.tsx` | `AlertaController@index` | ✅ Funcional |
| 12 | Reset de contraseña | `PasswordResetRequestScreen.tsx`, `PasswordResetScreen.tsx` | `PasswordResetController` | ✅ Completo |
| 13 | API REST (25+ endpoints) | — | `Modules/MobileApp/Routes/api.php` | ✅ Completo |

### ⚠️ PARCIAL (funciona pero limitado)

| # | Funcionalidad | Limitación |
|---|--------------|------------|
| 14 | Alertas | Solo listado, sin escalamiento ni tiempo de respuesta |
| 15 | Perfiles/roles | 4 roles hardcodeados, no RBAC granular |
| 16 | Inventario | Dos jerarquías separadas (listas/productos vs movimientos) |
| 17 | PerfilSelectionScreen | Usa endpoints que no existen (`/acceso/seleccionar_perfil`, `/acceso/procesar_perfil`) |

### ❌ NO IMPLEMENTADO

| # | Funcionalidad | Descripción |
|---|--------------|-------------|
| 18 | Modelo de turnos | Sin planificación de guardias |
| 19 | Validación centralizada QR/GPS | Cada módulo valida por su cuenta |
| 20 | Acceso peatonal/proveedor | Solo soporta vehicular |
| 21 | Offline-first | Sin soporte para sitios sin señal |
| 22 | Portal cliente separado | API conectada al mismo backend admin |
| 23 | Optimización de queries | Sin índices compuestos, sin eager loading |

---

## 4. Deuda Técnica Identificada

| # | Problema | Ubicación | Impacto |
|---|---------|-----------|---------|
| 1 | `AsyncStorageHelper.ts` tiene caracteres corruptos (`├│` en comentarios) | `src/utils/` | Puede causar errores de encoding |
| 2 | `ProfileSelectionScreen` usa endpoints inexistentes | `src/screens/` | Pantalla rota si se accede |
| 3 | `API_URL` hardcodeada a `192.168.100.212:3031` | `src/utils/constants.ts` | No funciona en otros entornos |
| 4 | Plugins duplicados en `app.json` | `totalsecureapp/` | Warning en build |
| 5 | Sin variables de entorno para backend (`.env` production) | `backend/` | Riesgo de configuración manual |
| 6 | Validación de GPS duplicada en Biometria, Ronda, Acceso | Controllers | Mantenimiento difícil |

---

## 5. Roadmap de v2.0 — Fases (orden corregido)

> **Regla:** completar cada fase antes de pasar a la siguiente. Cada fase es independiente y entregable.

---

### FASE 0 — Auditoría del código y esquema actual
**Estado:** ✅ COMPLETADA (2026-08-19)  
**Objetivo:** Tener el estado real de BD, modelos, migraciones y relaciones antes de tocar nada.  
**Entregable:** `ANALISIS-ESQUEMA-ACTUAL.md` con diagrama ER Mermaid + deuda técnica documentada.  
**Estimación:** 1-2 días

**Prompt:**
```
Actúa como arquitecto de software Laravel. Analiza este repositorio de Total Secure 
(Laravel 8.75 + PostgreSQL, módulos en Modules/).

1. Lista todos los modelos Eloquent, sus migraciones y relaciones (hasMany, belongsTo, 
   belongsToMany), incluyendo soft deletes, timestamps y campos nullable.
2. Genera un diagrama entidad-relación en formato Mermaid a partir del esquema real de BD.
3. Identifica: falta de índices en columnas usadas en filtros/búsqueda (institucion_id, 
   fecha, cedula, estado), falta de foreign keys con onDelete definido, campos que deberían 
   ser enum/estado y hoy son string libre.
4. Identifica duplicación de lógica entre los módulos Biometria, Rondas y Acceso 
   (validación de marcador/QR/GPS).
5. Entrega todo como ANALISIS-ESQUEMA-ACTUAL.md en la raíz del proyecto.

No modifiques código todavía, solo analiza y documenta.
```

---

### FASE 1 — Normalización de base de datos e inventario unificado
**Estado:** 📋 Documentación Completada (2026-08-20)  
**Objetivo:** Consolidar las dos jerarquías de inventario (Listas>Productos vs Inventario Equipamiento) en un solo modelo.  
**Dependencias:** Fase 0  
**Estimación:** 2-3 días  
**Documentación:** Ver `FASE1-INVENTARIO-UNIFICADO.md`

**Prompt:**
```
Sobre el esquema documentado en ANALISIS-ESQUEMA-ACTUAL.md, diseña e implementa la 
consolidación del módulo de inventario:

1. Crea un modelo unificado: 
   - producto_catalogo (id, institucion_id, nombre, descripcion, especificacion, activo)
   - lista (agrupador lógico, ya existente, relacionado a producto_catalogo via pivot)
   - movimiento (cabecera: institucion_id, lista_id, estado [entrega|devolucion|baja], 
     usuario_id, created_at)
   - movimiento_detalle (movimiento_id, producto_catalogo_id, cantidad_default, 
     cantidad_real, recibido bool, observacion, estado)
2. Escribe una migration + seeder que migre los datos existentes, preservando IDs y fechas.
3. Agrega un campo calculado o accessor "stock_actual" por producto_catalogo.
4. Escribe tests de migración que verifiquen el conteo de registros antes/después.
5. Antes de correr la migración en producción, genera un dump de respaldo.
```

---

### FASE 2 — Servicio central de Validación de Presencia
**Estado:** 📋 Documentación Completada (2026-08-20)  
**Objetivo:** Un único servicio (`PresenceValidationService`) usado por Biometría, Rondas y Acceso para validar QR + GPS + geocerca.  
**Dependencias:** Fase 0  
**Estimación:** 2-3 días  
**Documentación:** Ver `FASE2-PRESENCE-VALIDATION.md`

**Prompt:**
```
Crea un servicio central PresenceValidationService en app/Services/ que reciba:
- marcador_id (o código QR escaneado)
- coordenadas GPS del dispositivo
- radio de tolerancia en metros (configurable por institución)

Y devuelva: válido/inválido, distancia calculada, y motivo de rechazo si aplica.

Refactoriza los controladores de Biometria, RondaDetalle y Acceso para que usen este 
servicio en lugar de validar QR/GPS de forma independiente.

Agrega un campo radio_tolerancia_metros a la tabla institucion (default 100).

Escribe tests unitarios del servicio cubriendo: marcador válido dentro de radio, 
fuera de radio, QR de otra institución, marcador inactivo.
```

---

### FASE 3 — Modelo de Turnos y Planificación
**Estado:** 📋 Documentación Completada (2026-08-20)  
**Objetivo:** Sin esto, biometría y rondas son solo un log; con esto, se vuelven verificación contra un plan.  
**Dependencias:** Fase 2  
**Estimación:** 3-4 días  
**Documentación:** Ver `FASE3-TURNOS.md`

**Prompt:**
```
Diseña e implementa un módulo de Turnos:

1. Modelo turno (id, institucion_id, usuario_id, marcador_id_esperado, fecha, 
   hora_inicio_prevista, hora_fin_prevista, estado [programado|en_curso|completado|ausente])
2. Al registrar una marcación de Biometria, si existe un turno programado para ese 
   usuario/institución/fecha, vincula la marcación al turno y calcula:
   - minutos_tardanza (si aplica)
   - marco_desde_institucion_correcta (bool, cruzando con PresenceValidationService)
3. Crea un job/comando programado (Laravel Scheduler) que, al cerrar el día, marque como 
   "ausente" los turnos programados sin marcación de entrada.
4. Expón una vista "Cumplimiento de Turnos" con columnas: 
   usuario, institución, turno esperado, marcó, tardanza, estado.

No rompas el flujo actual: el vínculo a turno debe ser opcional (nullable).
```

---

### FASE 4 — Alertas con flujo de escalamiento real
**Estado:** 📋 Documentación Completada (2026-08-20)  
**Objetivo:** Convertir el log de alertas en un flujo con tiempos de respuesta y notificación real.  
**Dependencias:** Ninguna (independiente)  
**Estimación:** 2-3 días  
**Documentación:** Ver `FASE4-ALERTAS.md`

**Prompt:**
```
Extiende el módulo de Alertas:

1. Agrega a la tabla alerta: prioridad (alta/media/baja), asignado_a (usuario_id, nullable, 
   supervisor), fecha_atencion, tiempo_respuesta_segundos (calculado), 
   estado ampliado (pendiente|en_atencion|finalizada|cancelada).
2. Al crear una alerta, dispara un evento AlertaCreada.
3. Crea un Listener que notifique en tiempo real al panel admin y envíe push a supervisores.
4. Agrega un widget de "Alertas activas" en el dashboard, visible solo para Supervisor/Admin.
5. Escribe tests del cálculo de tiempo_respuesta_segundos y del disparo del evento.
```

---

### FASE 5 — Generalizar el módulo de Acceso
**Estado:** 📋 Documentación Completada (2026-08-20)  
**Objetivo:** Separar campos vehiculares, agregar validación por tipo, soporte múltiples entradas/salidas y pre-registro de visitantes.  
**Dependencias:** Fase 2 (PresenceValidationService)  
**Estimación:** 2-3 días  
**Documentación:** Ver `FASE5-ACCESOS.md`  
**Entregable:** 5 tablas (acceso modificada + 4 nuevas), 5 modelos, controller refactorizado, frontend con campos dinámicos, 8 tests.

---

### FASE 6 — RBAC granular (reemplazo de perfiles fijos)
**Estado:** 📋 Documentación Completada (2026-08-20)  
**Objetivo:** Reemplazar los roles hardcodeados por permisos por acción/módulo usando Spatie Permission.  
**Dependencias:** Fase 0  
**Estimación:** 1.5 días (~9 horas)  
**Documentación:** Ver `FASE6-RBAC.md`  
**Entregable:** 31 permisos móviles, middleware CheckPermission, PerfilController, ProfileSelectionScreen corregido, BelongsToInstitution trait.

**Prompt:**
```
Implementa permisos granulares usando spatie/laravel-permission:

1. Crea permisos por módulo y acción (ej: inventario.ver, inventario.editar, alertas.atender).
2. Migra los 4 perfiles actuales a roles de Spatie con permisos equivalentes.
3. Mantén el selector post-login funcionando igual.
4. Agrega scope de datos por institución al rol Supervisor y Vigilante.
5. Escribe tests que confirmen que un Vigilante no puede ver reportería de otra institución.
```

---

### FASE 7 — Sincronización offline de la APK
**Estado:** ⏳ PENDIENTE  
**Objetivo:** Que Biometría, Rondas, Acceso y Novedades no pierdan registros en sitios sin señal.  
**Dependencias:** Fases 2, 3, 4, 5  
**Estimación:** 3-4 días (solo contrato backend)

**Prompt:**
```
Diseña el contrato de API para soporte offline-first:

1. Define endpoints idempotentes (POST con client_uuid como idempotency key).
2. Backend rechaza duplicados por client_uuid sin error visible.
3. Documenta el flujo en API-OFFLINE-SYNC.md.
4. Agrega columnas client_uuid (unique) y sincronizado_en (timestamp nullable) a las tablas.
```

---

### FASE 8 — Separación API / Portal cliente
**Estado:** ⏳ PENDIENTE  
**Objetivo:** API REST separada para portal cliente (solo lectura, filtrada por institución).  
**Dependencias:** Fases 1-6  
**Estimación:** 3-4 días

**Prompt:**
```
Extrae una capa API REST separada del panel admin:

1. Exponer solo lectura de reportería (biometría, alertas, rondas, novedades, acceso).
2. Usar Laravel Sanctum para autenticación (ya configurado).
3. Filtrar por instituciones del token autenticado.
4. Documenta endpoints con openapi.yaml.
```

---

### FASE 9 — QA, performance y despliegue
**Estado:** ⏳ PENDIENTE  
**Objetivo:** Cerrar v2 con confiabilidad.  
**Dependencias:** Todas las fases  
**Estimación:** 2-3 días

**Prompt:**
```
Revisa el proyecto completo tras las fases anteriores:

1. Analiza queries N+1 en controllers con relaciones; corrige con eager loading.
2. Agrega índices compuestos para filtros más usados.
3. Configura cache de consultas para dashboard con invalidación por evento.
4. Escribe CHECKLIST-DESPLIEGUE-V2.md con backup pre-migración y rollback por fase.
```

---

## 6. Resumen de Estado

| Fase | Descripción | Estado | Estimación |
|------|-------------|--------|------------|
| 0 | Auditoría código + esquema | ✅ Completada | 1-2 días |
| 1 | Inventario unificado | 📋 Documentación | 2-3 días |
| 2 | Validación presencia central | 📋 Documentación | 2-3 días |
| 3 | Modelo de turnos | 📋 Documentación | 3-4 días |
| 4 | Alertas con escalamiento | 📋 Documentación | 2-3 días |
| 5 | Acceso generalizado | 📋 Documentación | 2-3 días |
| 6 | RBAC granular | 📋 Documentación | 1.5 días |
| 7 | Offline sync (backend) | ⏳ Pendiente | 3-4 días |
| 8 | API portal cliente | ⏳ Pendiente | 3-4 días |
| 9 | QA + despliegue | ⏳ Pendiente | 2-3 días |

**Tiempo total estimado:** 23-33 días de desarrollo

### Orden de ejecución recomendado

```
Fase 0 (auditoría)
 ├─→ Fase 1 (inventario) ──────────────────────┐
 ├─→ Fase 2 (validación presencia)              │
 │    └─→ Fase 3 (turnos) ─────────────────────┤
 ├─→ Fase 4 (alertas) ─────────────────────────┤
 ├─→ Fase 5 (acceso) ──────────────────────────┤
 └─→ Fase 6 (RBAC) ────────────────────────────┤
                                                 ├─→ Fase 7 (offline)
                                                 ├─→ Fase 8 (API cliente)
                                                 └─→ Fase 9 (QA)
```

### Correcciones al documento original

| Información original | Estado real |
|---------------------|-------------|
| "Filament" como panel admin | Laravel + Livewire (sin Filament) |
| "PostgreSQL" implícito | ✅ Confirmado |
| "117+ alertas, 755+ rondas, 554+ movimientos" | Datos de ejemplo del análisis original, no confirmados |
| "Sanctum no configurado" | ✅ Ya configurado (mobile auth) |
| Fase 8 asume falta de API REST | Ya existe API REST completa en MobileApp |

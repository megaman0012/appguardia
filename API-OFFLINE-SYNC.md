# API Offline Sync — Contrato (Fase 7)

> **Alcance:** contrato del backend. La implementación de la cola en la APK (Android)
> queda fuera de esta fase; este documento define lo que la APK debe consumir.

---

## 1. Problema

El guardia trabaja en sitios sin señal. Si el registro se envía en el momento y
falla, se pierde. Y si la APK reintenta a ciegas, el mismo marcaje entra dos veces.

Los 4 flujos afectados son biometría, rondas, acceso y novedades: todos crean un
registro que **no debe perderse ni duplicarse**.

---

## 2. Idempotencia por `client_uuid`

El dispositivo genera un UUID v4 **por registro**, lo guarda junto al registro en
su base local, y lo reenvía idéntico en cada reintento. El backend lo usa como
idempotency key.

| Situación | Respuesta |
|---|---|
| `client_uuid` nuevo | Crea el registro. `duplicado: false` |
| `client_uuid` ya recibido | **HTTP 200** con el registro existente. `duplicado: true` |
| `client_uuid` ausente | Crea siempre (comportamiento previo a Fase 7, compatible) |
| `client_uuid` mal formado | Error de validación (`nullable|uuid`) |

**Un duplicado no es un error.** Responde 200 con la misma forma que un alta nueva,
para que la APK marque el registro como sincronizado y lo borre de su cola sin
mostrarle nada al guardia. Esa es la razón de que no se use 409.

La unicidad la garantiza un índice `UNIQUE` en base de datos, no solo la consulta
previa: si dos reintentos entran en paralelo, uno gana y el otro recupera el
registro ya escrito en vez de fallar (ver §6).

---

## 3. Campos del contrato

Ambos son opcionales, así que un cliente viejo sigue funcionando sin cambios.

| Campo | Tipo | Descripción |
|---|---|---|
| `client_uuid` | UUID v4 | Idempotency key generada en el dispositivo. Único por registro, estable entre reintentos. |
| `ocurrido_en` | `Y-m-d H:i:s` | Momento real del evento en campo. Si no llega, se asume "ahora". Una fecha futura se recorta al momento actual (reloj de dispositivo adelantado). |

### Por qué `ocurrido_en` es necesario

Antes de esta fase los controladores escribían `date('Y-m-d H:i:s')`, es decir la
hora del **servidor**. Un registro hecho a las 07:00 sin señal y sincronizado a las
11:00 quedaba fechado 11:00: el dato de campo salía mal y la latencia era
inauditable. Con `ocurrido_en` el evento conserva su hora real y la diferencia
contra `sincronizado_en` **es** la latencia de red en campo.

---

## 4. Endpoints

Los 5 endpoints que crean registros aceptan `client_uuid` y `ocurrido_en`:

| Endpoint | Tabla | Fecha del evento | Permiso |
|---|---|---|---|
| `POST api/biometria` | `user_has_biometria` | `bio_created_at` | `biometria.marcar` |
| `POST api/rondas_detalle_gestion` | `ronda_detalle` | `rd_fecha_hora` | `rondas.gestionar` |
| `POST api/rondas_detalle_qrcode` | `ronda_detalle` | `rd_fecha_hora` | `rondas.scannear_qr` |
| `POST api/acceso` | `acceso` | `ac_created_at` | `acceso.registrar` |
| `POST api/novedad_create` | `novedad` | `nv_fecha_hora` | `novedades.crear` |

`biometria` y `acceso` no tienen columna de fecha propia, así que el evento se
fecha en su `created_at`.

### Ejemplo

```http
POST /api/novedad_create
Authorization: Bearer <token>
Content-Type: application/json

{
  "ins_code": 1,
  "nv_observacion": "Portón forzado",
  "nv_lat": "-33.45",
  "nv_lng": "-70.66",
  "client_uuid": "bbbbbbbb-bbbb-4bbb-8bbb-000000000001",
  "ocurrido_en": "2026-08-21 15:19:52"
}
```

Primer envío:
```json
{ "result": "success", "message": "Novedad Cargada Correctamente",
  "nv_id": 2, "client_uuid": "bbbb...0001", "duplicado": false }
```

Reintento (mismo `client_uuid`), **también 200**:
```json
{ "result": "success", "message": "Novedad ya sincronizada",
  "nv_id": 2, "client_uuid": "bbbb...0001", "duplicado": true }
```

---

## 5. Esquema

Migración `2026_08_21_300001_add_offline_sync_columns`. Por cada tabla, con su
prefijo (`bio_`, `rd_`, `ac_`, `nv_`):

| Columna | Tipo | Notas |
|---|---|---|
| `<pref>_client_uuid` | `varchar(36)` nullable **unique** | En Postgres varios `NULL` no colisionan, así que las filas históricas y los clientes que no lo envían siguen siendo válidos. |
| `<pref>_sincronizado_en` | `timestamp` nullable | Momento en que el servidor recibió el registro. |

Tablas: `user_has_biometria`, `ronda_detalle`, `acceso`, `novedad`.

---

## 6. Orden de operaciones en el backend

El orden importa y no es intercambiable:

```
1. Autenticación + permiso (middleware)
2. Validación del payload
3. ¿Existe ya este client_uuid?  ──sí──► 200 con el registro existente. FIN.
4. Validación GPS / geocerca
5. Guardado de la foto en disco
6. INSERT (protegido por el índice unique)
      └─ si choca con el unique: recupera el registro ganador y responde 200
7. 200 con el registro nuevo
```

**El paso 3 va antes del 4 y del 5, deliberadamente:**

- **Antes de la foto (5):** un reintento no vuelve a escribir la imagen ni deja
  archivos huérfanos en disco.
- **Antes del GPS (4):** el guardia ya no está en el punto cuando la cola
  reintenta horas después. Revalidar la geocerca rechazaría un registro legítimo.
- **En el QR de rondas, antes del guard de "espere 5 minutos":** si no, un
  reintento del mismo escaneo recibiría ese error en vez de un éxito. El guard
  sigue activo para escaneos genuinamente nuevos: lo que lo distingue es el
  `client_uuid`.

---

## 7. Flujo esperado en la APK

```
Registro en campo
   │
   ├─► genera client_uuid (v4) + ocurrido_en (hora local del dispositivo)
   ├─► guarda en SQLite local con estado = pendiente
   │
   └─► intenta enviar
         ├── 200 (duplicado true o false) ─► marca sincronizado, saca de la cola
         ├── 4xx de validación ───────────► marca error, no reintenta, avisa
         └── error de red / 5xx ──────────► deja pendiente, reintenta en background
```

Reglas para la APK:

1. **El `client_uuid` no se regenera nunca en un reintento.** Es lo único que
   evita el duplicado.
2. **`duplicado: true` es éxito**, no un error que mostrar.
3. **`ocurrido_en` se captura al crear el registro**, no al enviarlo.
4. Reintentos con espera creciente (backoff), no en bucle inmediato.
5. Un 4xx de validación no se reintenta: el payload no va a mejorar solo.
6. La cola se procesa en orden de `ocurrido_en` para que la secuencia de una ronda
   llegue coherente.

---

## 8. Auditoría de latencia en campo

`sincronizado_en - <fecha del evento>` da cuánto tardó en llegar cada registro:

```sql
SELECT nv_id,
       nv_fecha_hora                                  AS ocurrio,
       nv_sincronizado_en                             AS llego,
       nv_sincronizado_en - nv_fecha_hora             AS latencia
FROM novedad
WHERE nv_client_uuid IS NOT NULL
ORDER BY latencia DESC;
```

Sirve para detectar instituciones con mala cobertura: si la latencia mediana de un
sitio es de horas, es un problema de señal en ese sitio, no de la APK.

---

## 9. Estado

| Punto del checklist | Estado |
|---|---|
| Endpoints idempotentes con `client_uuid` | ✅ 5 endpoints |
| Duplicado devuelve 200 sin error visible | ✅ `duplicado: true` |
| Columnas `client_uuid` + `sincronizado_en` | ✅ 4 tablas, con índice unique |
| Documentación del flujo | ✅ este archivo |
| Implementación de la cola en la APK | ⏳ fuera del alcance de la fase |

Verificado con 16 tests en `tests/Unit/OfflineSyncTest.php` y pruebas en vivo de
`novedad_create`, `acceso` y `rondas_detalle_qrcode` (incluido el caso del guard de
5 minutos).

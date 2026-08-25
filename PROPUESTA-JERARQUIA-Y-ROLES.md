# Propuesta: jerarquía territorial y nivel de Líder Operativo

Análisis del estado actual y propuesta de mejora, a partir del modelo de negocio
real: empresa de seguridad con operaciones en aeropuertos, clientes
multinacionales (p. ej. DHL) y presencia en varios países.

---

## 1. El modelo de negocio que hay que representar

```
Cliente (DHL)
 └─ País (Ecuador, Colombia)
     └─ Provincia
         └─ Ciudad
             └─ Local            ← donde efectivamente trabajan los guardias
                 └─ Puesto de trabajo   (si el local es grande)
```

---

## 2. Qué hay hoy

| Tabla | Filas | Qué es en realidad |
|---|---|---|
| `organizacion` | **0** | El **Cliente**. Tiene `org_pais`, pero es **texto libre** y el formulario del panel **ni siquiera lo muestra** |
| `sede` | **0** | Sin significado en este negocio: solo sigla y descripción. Herencia del sistema hospitalario |
| `organizacion_sede` | **0** | Puente cliente↔sede. Sin uso |
| `organizacion_institucion` | 3 | El **Local**. Las 3 filas tienen `ins_so_code` **NULL**: están huérfanas, sin cliente |

Faltan por completo: País, Provincia y Ciudad como niveles reales, y el **Puesto
de trabajo**.

**Por eso no se puede seleccionar país:** el campo existe como texto libre, no
está en el formulario, y no hay catálogo detrás.

### La restricción de diseño que manda

`ins_code` está en **17 tablas**:

```
acceso · acceso_preregistro · alertas · bitacora · institucion_marcadores
inv_lista · inv_listas_productos · inv_movimiento_cabecera · inv_movimientos
inv_producto_catalogo · novedad · organizacion_institucion · ronda_cabecera
ronda_detalle · turno · user_has_biometria · user_has_institucion
```

Es el eje de todo el sistema: rondas, accesos, alertas, novedades, biometría,
turnos, inventario, el alcance del portal cliente y el trait
`BelongsToInstitution`.

**Conclusión: el Local no se mueve.** La jerarquía se construye *alrededor* de
`organizacion_institucion`, no reemplazándola. Todo lo que sigue es **aditivo**.

### El momento es el correcto

`organizacion`, `sede` y `organizacion_sede` están **vacías**, y los 3 locales
están huérfanos. No hay datos productivos que migrar. Hacerlo después de cargar
clientes reales sería mucho más caro.

---

## 3. Propuesta A — geografía normalizada (recomendada)

### Tablas nuevas

```
pais         pa_id, pa_iso2 ('EC'), pa_iso3, pa_nombre, pa_estado
provincia    pr_id, pr_pa_id → pais, pr_nombre, pr_estado
ciudad       cd_id, cd_pr_id → provincia, cd_nombre, cd_estado
puesto       pu_id, pu_ins_code → local, pu_nombre, pu_descripcion,
             pu_lat, pu_lng, pu_estado
```

### Cambios sobre lo existente

```
organizacion_institucion  (el Local)
  + ins_cliente_id  → organizacion    (de qué cliente es este local)
  + ins_cd_id       → ciudad          (dónde está)
    ins_so_code                       queda obsoleto
    ins_ciudad (texto)                queda obsoleto, se migra a ins_cd_id
```

`sede` y `organizacion_sede` se retiran (vacías, sin significado aquí).

### Por qué el Local apunta a Cliente **y** a Ciudad, en vez de una cadena rígida

Un cliente opera en varios países; una ciudad pertenece a un solo país. Si el
local apunta a los dos, se obtienen **todos** los cortes sin duplicar el cliente
por país:

- "DHL en Ecuador" → locales con `cliente = DHL` y `ciudad→provincia→país = EC`
- "Todo Guayas" → locales con `ciudad→provincia = Guayas`
- "Todos los locales de DHL" → `cliente = DHL`

Una cadena literal `cliente → país → provincia → ciudad → local` obligaría a
crear un registro "DHL-Ecuador" y otro "DHL-Colombia", con el nombre del cliente
repetido y sin ninguna ventaja.

### Puesto de trabajo

Hoy lo más parecido es `turno.tu_marcador_code`, pero los marcadores son **puntos
QR de ronda**, no puestos. Son cosas distintas: un puesto es una posición fija
(garita, andén, sala de carga); un marcador es un punto que el guardia escanea al
pasar.

Mínimo viable:

```
turno  + tu_puesto_id → puesto        (el turno se cubre en un puesto concreto)
```

Y opcionalmente, más adelante: asignar guardias a puestos habituales, y filtrar
rondas/novedades por puesto.

### Impacto en lo que ya funciona

| Componente | Impacto |
|---|---|
| `BelongsToInstitution` y las 17 tablas | **Ninguno.** `ins_code` no se toca |
| Portal cliente (`user_has_institucion`) | Ninguno. Gana filtros de agregación por cliente/país |
| App móvil | Ninguno. Sigue trabajando contra `ins_code` |
| Panel | Resources nuevos (País, Provincia, Ciudad, Puesto) y selectores en el formulario del Local |
| `/api/instituciones` | Puede devolver el contexto geográfico, sin romper el contrato actual |

---

## 4. Propuesta B — descartada

Cadena literal con una tabla intermedia "operación cliente-país". Más joins, el
cliente duplicado por país, y ningún corte que la propuesta A no dé. No aporta.

---

## 5. Nivel de Líder Operativo

### Hallazgo: el panel **ya** está diseñado para tres niveles

Las pantallas de administración ya están correctamente restringidas en el código:

| Resource | Perfiles que lo ven |
|---|---|
| `UsersResource` | `Administrador`, `Administrador General` |
| `UserHasRolesResource` | `Administrador`, `Administrador General` |
| `UserHasInstitucionResource` | `Administrador`, `Administrador General` |
| `UserHasGestionResource` | `Administrador`, `Administrador General` |
| `OrganizacionResource`, `SedeResource`, `OrganizacionSedeResource` | `Administrador`, `Administrador General` |
| `InvProductoResource` | `Administrador`, `Administrador General` |
| `OrganizacionInstitucionResource`, `InvListaProductoResource`, `InvMovimientoResource` | + `Supervisor` |

**Pero los roles `Administrador` y `Administrador General` NO existen en la base.**
Solo existen `Supervisor`, `Vigilante` y `Cliente`.

Consecuencia: **todas esas pantallas están muertas hoy.** Nadie puede crear un
usuario, asignarle un rol ni vincularlo a un local desde el panel. Es exactamente
el vacío descrito: no hay quién dé de alta a un guardia.

O sea: la separación que se pide **ya está implementada**, solo falta crear el
rol y decidir cómo se llama.

### Propuesta

Crear el rol con nombre propio del negocio —**`Lider Operativo`**— en vez de
reutilizar `Administrador`, que no dice nada sobre la operación. Eso exige
actualizar las 11 listas de perfiles y `canAccessFilament()`.

**Y centralizarlas.** Hoy los perfiles autorizados están escritos a mano en 12
archivos como `in_array(Session::get('usuPF'), ['Administrador', ...])`. Agregar
un rol obliga a tocar los 12 y olvidarse de uno deja una pantalla mal expuesta o
inaccesible. Propuesta: un único lugar (config o helper) del tipo
`PerfilPanel::puedeGestionarUsuarios()`, y que los resources lo consulten.

### Reparto de responsabilidades propuesto

| Acción | Vigilante | Supervisor | Líder Operativo | Cliente |
|---|---|---|---|---|
| App móvil: rondas, accesos, novedades, biometría | ✅ | ✅ | — | — |
| Ver estado de guardias y turnos | — | ✅ | ✅ | — |
| Atender y escalar alertas | — | ✅ | ✅ | — |
| **Crear y editar usuarios (guardias)** | — | **❌** | **✅** | — |
| **Asignar guardia a local / puesto** | — | **❌** | **✅** | — |
| **Asignar roles** | — | **❌** | **✅** | — |
| Crear locales y puestos | — | ❌ | ✅ | — |
| Catálogo de productos de inventario | — | ❌ | ✅ | — |
| Reportería de su propio cliente | — | — | — | ✅ |

El Supervisor **observa y responde**; el Líder Operativo **da de alta y asigna**.
Es la separación que se pidió.

Los permisos actuales del **Vigilante quedan intactos**, como se indicó.

### Permisos nuevos a sembrar

Sección nueva (`ps_codigo` 20, "Gestión de personal"):

```
usuarios.ver                    usuarios.crear
usuarios.editar                 usuarios.asignar_rol
usuarios.asignar_institucion    usuarios.asignar_puesto
```

Todos para `Lider Operativo`. Ninguno para `Supervisor`.

### Punto a decidir

Hoy el `Supervisor` puede **editar** locales (`OrganizacionInstitucionResource`).
Bajo el reparto propuesto eso debería ser **solo lectura** para él: crear y
modificar locales es del Líder Operativo. Conviene confirmarlo.

---

## 6. Orden sugerido

Cada paso deja el sistema funcionando; no hace falta hacerlos todos de una vez.

1. **Líder Operativo** — crear el rol, centralizar las listas de perfiles,
   sembrar los permisos. Desbloquea de inmediato las pantallas que ya existen.
   *Es el de mayor beneficio por esfuerzo: el código ya está hecho.*
2. **Geografía** — `pais`, `provincia`, `ciudad` + sus resources y el selector en
   el formulario del Local. Resuelve la observación del país.
3. **Cliente** — enganchar el local a `organizacion`, exponer el formulario del
   cliente, retirar `sede`/`organizacion_sede`.
4. **Puesto de trabajo** — tabla `puesto` y `tu_puesto_id` en turnos.
5. **Agregaciones** — filtros por cliente/país/provincia en el panel y en el
   portal.

Los pasos 1 y 2 se pueden hacer en paralelo: no se tocan entre sí.

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| Tocar `ins_code` rompería 17 tablas | La propuesta **no lo toca**. Todo es aditivo |
| Cargar clientes reales antes de la jerarquía encarece la migración | Hacerlo ahora, con las tablas vacías |
| Olvidar un `in_array` de perfiles al agregar el rol | Centralizar los perfiles antes de crear el rol (paso 1) |
| Datos de ciudad en texto libre (`ins_ciudad`) | Migrar a `ins_cd_id` con un mapeo manual: hoy son 3 filas |

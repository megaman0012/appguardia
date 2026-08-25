# Propuesta: jerarquía territorial y modelo de cinco roles

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

### El análisis global del cliente: por eso NO se separan

La preocupación es correcta y es justamente la razón de esta decisión. Si se
crearan "DHL Ecuador" y "DHL Colombia" como **clientes distintos**, una consulta
de gerencia sobre DHL tendría que saber de antemano cuántas filiales existen y
sumarlas a mano; al abrir un tercer país, cualquier reporte que no se actualice
quedaría incompleto **sin avisar**.

Con el diseño propuesto, DHL es **un solo registro**. El país no está en el
cliente: está en la ciudad del local. Los tres cortes salen de la misma tabla:

```sql
-- Global del cliente (lo que pide gerencia)
SELECT count(*) FROM alertas a
  JOIN organizacion_institucion l ON l.ins_code = a.al_ins_code
 WHERE l.ins_cliente_id = :dhl;

-- El mismo cliente, un país
SELECT count(*) FROM alertas a
  JOIN organizacion_institucion l ON l.ins_code = a.al_ins_code
  JOIN ciudad c    ON c.cd_id = l.ins_cd_id
  JOIN provincia p ON p.pr_id = c.cd_pr_id
 WHERE l.ins_cliente_id = :dhl AND p.pr_pa_id = :ecuador;

-- Comparar países del mismo cliente, en una sola consulta
SELECT pa.pa_nombre, count(*) FROM alertas a
  JOIN organizacion_institucion l ON l.ins_code = a.al_ins_code
  JOIN ciudad c    ON c.cd_id  = l.ins_cd_id
  JOIN provincia p ON p.pr_id  = c.cd_pr_id
  JOIN pais pa     ON pa.pa_id = p.pr_pa_id
 WHERE l.ins_cliente_id = :dhl
 GROUP BY pa.pa_nombre;
```

Abrir un país nuevo es insertar una fila en `ciudad`. **Ningún reporte existente
se toca**, y el global de DHL los incluye automáticamente.

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

## 5. Roles

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

### Propuesta: cinco roles

| Rol | Para quién | Qué hace | Alcance de datos |
|---|---|---|---|
| **Vigilante** | Guardia en terreno | App móvil: rondas, accesos, novedades, biometría | Sus locales |
| **Supervisor** | Jefe de turno / zona | Observa el estado de guardias y turnos, atiende y escala alertas | Sus locales |
| **Líder Operativo** | Jefe de operaciones | **Da de alta guardias**, les asigna rol, local y puesto. Crea locales y puestos | *A definir — ver abajo* |
| **Administrador** | Departamento de Sistemas | Todo, incluida la configuración técnica: clientes, geografía, catálogos, parámetros, bitácora | **Global, sin filtro** |
| **Cliente** | Dueño de la organización | Portal de solo lectura (API Fase 8) | Sus locales |

La separación que se pidió queda explícita: el **Supervisor observa y responde**;
el **Líder Operativo da de alta y asigna**. Los permisos del **Vigilante quedan
intactos**.

### El rol `Administrador` ya está cableado

Las 11 pantallas administrativas ya exigen `Administrador` o
`Administrador General`. Crear el rol **`Administrador`** con ese nombre exacto
las habilita **sin tocar una línea de código**. Es literalmente insertar una fila.

`Administrador General` puede quedar como sinónimo sin usar, o eliminarse de las
listas para no confundir.

### El `Líder Operativo` sí exige tocar código

Ese nombre no aparece en ninguna lista. Hay que agregarlo a las pantallas que le
correspondan y a `canAccessFilament()`. Reparto sugerido:

| Pantalla | Administrador | Líder Operativo | Supervisor |
|---|---|---|---|
| Usuarios, asignar rol, vincular a local, gestiones | ✅ | ✅ | — |
| Locales y Puestos | ✅ | ✅ | solo lectura |
| Clientes, País/Provincia/Ciudad | ✅ | — | — |
| Catálogo de productos de inventario | ✅ | — | — |
| Rondas, accesos, alertas, novedades, inventario | ✅ | ✅ | ✅ |
| Parámetros, bitácora, logs | ✅ | — | — |

La diferencia de fondo: el **Líder Operativo mueve personas**; el
**Administrador además define el mundo** (clientes, geografía, catálogos) y ve el
total para Sistemas.

### Centralizar los perfiles antes de crear los roles

Hoy los perfiles autorizados están escritos a mano en **12 archivos**, así:

```php
in_array(Session::get('usuPF'), ['Administrador', 'Administrador General'])
```

Con cinco roles esto se vuelve frágil: agregar uno obliga a tocar los doce, y
olvidar uno deja una pantalla mal expuesta o inaccesible — que es exactamente
cómo `UsersResource` terminó invisible para todos.

**Antes de crear los roles**, llevar esas listas a un solo lugar:

```php
PerfilPanel::puedeGestionarUsuarios()   // Administrador, Lider Operativo
PerfilPanel::puedeConfigurarSistema()   // Administrador
PerfilPanel::puedeOperar()              // + Supervisor
```

### ⚠️ Punto abierto: ¿el Líder Operativo es global o acotado?

**Hoy el panel filtra los datos SOLO para el perfil `Supervisor`.** Cualquier
otro perfil que entre ve **todo, sin filtro** (`getEloquentQuery()` devuelve la
consulta sin acotar).

Para el `Administrador` eso es justo lo que se quiere: ve el total.

Para el `Líder Operativo` **hay que decidirlo**, y cambia el trabajo:

- **Opción 1 — global.** Un solo líder para toda la empresa. No hay nada que
  programar: hereda el comportamiento actual.
- **Opción 2 — acotado por país.** Un líder de Ecuador y otro de Colombia, cada
  uno gestiona solo a los suyos. Exige una tabla `user_has_pais` (o similar) y
  aplicar ese filtro en las pantallas de gestión.
- **Opción 3 — acotado por cliente.** Un líder por cuenta grande (p. ej. todo
  DHL). Exige `user_has_cliente` y el filtro equivalente.

`user_has_institucion` **no sirve** para esto: es a nivel de local, demasiado
granular para alguien que gestiona una operación entera.

Dado que ya hay operaciones en Ecuador y Colombia, la **opción 2** parece la más
probable, pero conviene confirmarlo antes de implementar: es la diferencia entre
no programar nada y agregar una dimensión de alcance nueva.

### Permisos nuevos a sembrar

Sección nueva (`ps_codigo` 20, "Gestión de personal"):

```
usuarios.ver                    usuarios.crear
usuarios.editar                 usuarios.asignar_rol
usuarios.asignar_institucion    usuarios.asignar_puesto
```

Para `Administrador` y `Lider Operativo`. Ninguno para `Supervisor`.

### Punto a decidir

Hoy el `Supervisor` puede **editar** locales (`OrganizacionInstitucionResource`).
Bajo este reparto debería ser **solo lectura** para él.

---

## 6. Orden sugerido

Cada paso deja el sistema funcionando; no hace falta hacerlos todos de una vez.

1. **Roles** — centralizar las listas de perfiles, crear `Administrador` (que ya
   está cableado y no exige código) y `Lider Operativo`, sembrar los permisos.
   Desbloquea de inmediato pantallas que ya existen. *Mayor beneficio por
   esfuerzo.* Requiere antes decidir el alcance del Líder Operativo.
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

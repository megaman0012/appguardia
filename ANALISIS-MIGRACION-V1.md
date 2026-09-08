# Análisis de la base real de v1 → v2

> Sobre `coredt360_bk.sql` (32 MB), volcado de **Navicat** el 2026-09-07 16:46 desde
> un servidor rotulado **`Produccion`**. Motor **MariaDB 10.4.32**, esquema
> `coredt360`. 41 tablas, 423 columnas, sin vistas, triggers ni procedimientos.
>
> Analizado cargándolo en un MariaDB 10.4 desechable, **no** contra el dump a ojo.
> Todos los números de abajo son consultas reales.

---

## 0. Primero: el dump está dentro del repo y no estaba ignorado

`coredt360_bk.sql` quedó en la raíz del repo, que está en GitHub. Trae datos de
878 personas reales —cédulas, correos, hashes de contraseña, GPS— y **un
`git add -A` lo habría publicado**. Comprobado que nunca entró a ningún commit, y
agregado a `.gitignore` (`*.sql`, `*.dump`, `*.sql.gz`, con excepción para el
script de init del contenedor).

**Sacarlo del historial después habría sido reescribirlo**, con el repo ya
clonado en otras máquinas. Conviene además no dejarlo ahí: su lugar es fuera del
árbol de trabajo.

---

## 1. La hipótesis fácil no funciona

La idea era: v1 y v2 son el mismo linaje (`coredt360`), así que **restaurar una
copia de v1 y correrle `php artisan migrate`** dejaría que las 48 migraciones
hagan el trabajo. Se descartó, y conviene saber por qué antes de intentarlo.

**v1 tiene 8 migraciones registradas.** La última es
`2024_11_29_190201_create_pacientes_table` — la herencia del sistema
hospitalario. 7 de esas 8 existen como archivo en v2; la de `pacientes` la
eliminó la limpieza del 2026-08-24 (inofensivo: `migrate` no corre lo que no
tiene archivo).

El problema es el otro lado: **8 migraciones no crearon 41 tablas.** La mayor
parte del esquema de v1 se hizo a mano, fuera de Laravel. Y en v2 alguien
escribió migraciones *de recuperación* que crean esas mismas tablas como código:

| Migración de v2 | Crea | ¿v1 la registró? |
|---|---|---|
| `2024_12_01_000000_create_mobile_app_business_tables` | 17 tablas | **No** |
| `2026_08_12_130000_create_organizacion_structure_tables` | 3 | **No** |
| `2026_08_12_100000_create_web_catalog_tables` | 5 | **No** |
| `2026_08_12_110000_create_web_log_tables` | 2 | **No** |
| `2024_11_27_150000_create_parametros_table` | 1 | **No** |

Ninguna usa `Schema::hasTable` como guarda (verificado: 0 ocurrencias en las
cinco). Sobre la base de v1 intentarían **crear tablas que ya existen** y
`migrate` se cae en la primera.

Y hay un segundo motivo, más de fondo: **v1 es MariaDB y v2 es PostgreSQL.** El
volcado es sintaxis MySQL y no entra en Postgres.

### El camino que sí sirve

**Esquema nuevo de v2 desde las migraciones (que ya funciona: 48 migraciones,
292 pruebas verdes) y ETL de solo los datos.**

Con una consecuencia que hay que tener presente: las migraciones que
**transforman** datos ya estarán aplicadas cuando lleguen las filas, así que
**no van a transformar nada**. Lo que hacían hay que hacerlo a mano en el ETL:

- `2026_08_27_100001_eliminar_sede` — rescatar el cliente que colgaba de la sede.
- `2026_08_24_300001_backfill_ciudad_en_locales` — resolver la ciudad de cada local.

En desarrollo esas migraciones no tenían nada que transformar. **Aquí sí.**

---

## 2. Cuánto dato hay

Producción de verdad, no una prueba. Del 2025-04-07 al 2026-09-07 (17 meses).

| Tabla | Filas | Nota |
|---|---:|---|
| `user_has_institucion` | 38.246 | Legítimo: 877 usuarios × hasta 125 locales. **Sin pares duplicados** |
| `ronda_detalle` | 37.616 | 29.243 con foto |
| `inv_movimiento_detalles` | 23.790 | |
| `user_has_biometria` | 12.664 | **todas** con foto |
| `personal_access_tokens` | 11.531 | Tokens de sesión: no migrar, se regeneran |
| `acceso` | 9.769 | |
| `ronda_cabecera` | 8.666 | |
| `inv_movimientos` | 5.963 | |
| `log` / `log_trafico` | 1.750 / 1.152 | Auditoría; decidir si vale la pena |
| `acceso_persona` | 1.636 | Visitantes registrados |
| `users` | **878** | |
| `user_has_roles` / `user_has_gestions` | 932 / 886 | |
| `organizacion_institucion` | **137** | Los locales |
| `institucion_marcadores` | **118** | Puntos QR |
| `organizacion` | **21** | Los clientes |
| `alertas` | 278 | |
| `novedad` | 7 | |
| `sede` / `organizacion_sede` | **2 / 23** | Ver §3.1 |
| `bitacora`, `failed_jobs`, `password_resets`, `productos`, `user_has_permissions` | 0 | Vacías: no son problema |

---

## 3. Los cuatro choques reales

### 3.1 `sede` no es lo que v2 creyó: son **países**

`AGENTS.md` dice que `sede` era «un nivel intermedio (organización → sede →
institución)» que «**nunca se usó** — las tres tablas estaban vacías y ningún
local tenía sede». Eso era cierto **en la base de desarrollo**. En producción:

```
sede:  ps_code 1 = "Ecuador" (EC, activo)
       ps_code 2 = "Chile"   (CL, inactivo)
organizacion_sede: 23 filas, cliente ↔ país
```

`sede` se estaba usando como **catálogo de países**, y `organizacion_sede` dice
en qué países opera cada cliente. Los **137 locales tienen `ins_so_code`**, así
que el rescate del cliente hacia `ins_cliente_id` funciona para todos.

Lo que se perdería si nadie lo mira es **el país**: en v2 el país de un local no
se guarda, se deduce por `ciudad → provincia → país`. Y eso importa porque
`PerfilPanel::localesDelUsuario()` acota al Líder Operativo por país, y **un
local sin ciudad no pertenece a ningún país, así que ningún líder lo ve**.

> ⚠️ **v2 sembró Ecuador y Colombia. Los datos reales dicen Ecuador y Chile.**
> Los 137 locales están en Ecuador; Chile está inactivo y sin locales. Habría que
> confirmar si Colombia es real (el README dice que se opera ahí) o si quedó del
> seed, y si Chile hay que conservarlo.

### 3.2 La ciudad es texto libre y hay que construir el catálogo

v1: `ins_ciudad varchar(250)`, escrito a mano. v2: `ins_ciudad_id`, FK al
catálogo `ciudad`. **v2 tiene 24 provincias sembradas y una sola ciudad**
(Guayaquil).

Los 16 valores reales, con su conteo:

| v1 (texto) | Locales | Qué hacer |
|---|---:|---|
| `Guayaquil` | 95 | ya existe en v2 |
| `QUITO` | 16 | crear (Pichincha) |
| `MANTA` | 11 | crear (Manabí) |
| `CUENCA` | 2 | crear (Azuay) |
| `AMBATO` | 2 | crear (Tungurahua) |
| `PORTOVIEJO`, `IBARRA`, `STO. DOMINGO`, `DURÁN`, `NARANJAL`, `EL TRIUNFO`, `Nobol`, `VILLAMIL PLAYAS` | 1 c/u | crear |
| `SAN CRISTOBAL`, `BALTRA` | 1 c/u | crear (Galápagos) |
| **`MANTENIMIENTO`** | 1 | **no es una ciudad** — decisión humana |

Son **15 ciudades por crear**, cada una atada a su provincia. Y hay que
normalizar mayúsculas y abreviaturas (`STO. DOMINGO` → Santo Domingo,
`VILLAMIL PLAYAS` → General Villamil). El caso `MANTENIMIENTO` es dato sucio:
alguien escribió un área en el campo de ciudad, y ese local **quedaría fuera del
alcance de todo líder** si se deja así.

### 3.3 `acceso`: 7 columnas cambiaron de tabla, y **tienen datos**

Ocho columnas de `acceso` no existen en el `acceso` de v2. No están perdidas
—v2 las normalizó en `acceso_vehiculo`— pero un ETL columna a columna **las
tiraría en silencio**:

| v1 `acceso` | Filas con dato | v2 |
|---|---:|---|
| `ac_empresa` | **8.975** | `acceso_vehiculo.av_empresa` |
| `ac_patente` | 1.326 | `av_patente` |
| `ac_is_neumatico` | 694 | `av_is_neumatico` |
| `ac_pta_llave` | 652 | `av_pta_llave` |
| `ac_is_sello` | 634 | `av_is_sello` |
| `ac_is_carro` | 602 | `av_is_carro` |
| `ac_kms` | 362 | `av_kms` |
| `ac_nombre_contrato` | 36 | **ningún destino** |

O sea: el ETL de accesos **se parte en dos** — las columnas base a `acceso`, y una
fila en `acceso_vehiculo` por cada acceso que traiga alguno de esos siete campos.
`ac_empresa` con 8.975 filas es el que más pesa: es casi cada acceso.

#### `ac_nombre_contrato`: qué es, en realidad

`AGENTS.md` dice que la migración `2026_08_21_100002` lo borra «sin destino», y
eso es cierto **de la migración**, pero no del modelo de v2. Mirando los 36
valores se ve qué guardaba: **el nombre de la persona a la que se visitaba, o del
responsable que autorizaba la entrada**, en texto libre y sin ninguna
validación:

```
Transmonserrate (4)   Romel Murillo (4)   Jean (2)   Carlos (2)
"Srta Anguelina Guevara  Asistente de Gerencia"
"Claudia Maldonado;samia chejin;Alejandro Ramirez"
"Responsable : Andrea Pasmiño"   "A dejar carga"   "Sn"
0986765524   0978696095          <- numeros de telefono
```

O sea: un campo donde cada guardia escribió lo que quiso — nombres, cargos,
varias personas separadas por `;`, teléfonos, y `Sn` («sin nombre»).

**Y v2 sí tiene el destino natural: `acceso_visitante.avi_persona_visita`**, que
es exactamente «la persona a la que se visita». No hay que aceptar la pérdida ni
meterlo en observaciones: va a esa columna, tal cual, sin intentar interpretarlo.

Son 36 filas de 9.769 (0,4%), así que cualquiera de las dos decisiones es
defendible. Copiarlo cuesta una línea en el ETL.

### 3.4 Inventario: v2 tiene los dos juegos de tablas

v1 trae `inv_productos`, `inv_listas_productos`, `inv_lista_producto_items`,
`inv_movimientos`, `inv_movimiento_detalles`. **v2 tiene esas mismas y además**
`inv_producto_catalogo`, `inv_lista`, `inv_lista_item`,
`inv_movimiento_cabecera`, `inv_movimiento_detalle` (singular).

Esto **no es una decisión de migración: es una inconsistencia que v2 ya tiene**, y
conviene verla antes de cargar nada.

`FASE1-INVENTARIO-UNIFICADO.md` diseñó el juego **nuevo** para reemplazar al
viejo. La app móvil ya se movió; el panel **no**:

| Quién | Modelos | Tablas | Juego |
|---|---|---|---|
| **App móvil** (`InventarioController`) | `Lista`, `ListaItem`, `MovimientoCabecera`, `MovimientoDetalle`, `ProductoCatalogo` | `inv_lista`, `inv_lista_item`, `inv_movimiento_cabecera`, `inv_movimiento_detalle`, `inv_producto_catalogo` | **nuevo** |
| **Panel Filament** (4 resources) | `InvProducto`, `InvMovimiento`, `InvMovimientoDetalle`, `InvListaProducto` | `inv_productos`, `inv_movimientos`, `inv_movimiento_detalles`, `inv_listas_productos` | **viejo** |

**El guardia escribe en unas tablas y el panel lee otras.** Un inventario hecho
desde la tablet hoy no aparece en «Inventario» del panel, y al revés. No lo
provoca la migración: pasa ya.

Por eso cargar los 23.790 movimientos en cualquiera de los dos juegos deja a la
mitad del sistema ciego. **El orden correcto es arreglar el panel primero** —
apuntar los 4 resources al juego nuevo, que es el diseño de FASE1 y donde escribe
la app— y después cargar, al juego nuevo.

---

## 4. Las imágenes: **no están en el dump**

Respuesta directa a la pregunta: **no hay una sola columna BLOB en las 41 tablas.**
La base guarda solo el **nombre del archivo**; los archivos viven en el disco del
servidor de v1, en otra carpeta.

**Cuántos hay que buscar: ~41.913 archivos.**

| Tabla | Columna | Archivos |
|---|---|---:|
| `user_has_biometria` | `bio_image_name` | **12.664** (todas las filas) |
| `ronda_detalle` | `rd_foto` | **29.243** (de 37.616) |
| `novedad` | `nv_foto` | 6 |
| `bitacora` | `bt_foto` | 0 (tabla vacía) |

**Dónde buscarlas en el servidor de v1** — la convención (`generalTrait::storeFiles`)
es la misma en las dos versiones:

```
<raiz-del-proyecto>/public/images/<modulo>/<AAAA>/<MM>/<DD>/<archivo>
```

con `<modulo>` ∈ `biometria`, `rondas`, `accesos`, `novedad`, `bitacora`. El
nombre del archivo es `<user_id>_<ug_code>_<timestamp>.jpg`; ejemplo real:
`426_427_1788802977.jpg`.

> ### ⚠️ La ruta se **calcula desde la fecha del registro**, no se guarda
>
> ```php
> $fecha = Carbon::parse($this->nv_fecha_hora)->format('Y/m/d');
> $imagePath = public_path('images/novedad/' . $fecha . '/' . $this->nv_foto);
> if (file_exists($imagePath) && !empty($this->nv_foto)) { ... }
> return null;
> ```
>
> Hay que **copiar el árbol de carpetas tal cual**. Si se vuelcan los 42.000
> archivos en una sola carpeta, o se pierde un nivel, el accessor devuelve `null`
> y **las fotos desaparecen sin un solo error en el log**: el panel muestra la
> fila sin imagen, como si nunca hubiera habido foto.
>
> Y como la carpeta viene de la fecha del registro, **el ETL tiene que preservar
> las fechas originales** (`bio_created_at`, `rd_fecha`, `nv_fecha_hora`). Si se
> cargan con `now()`, las rutas apuntan al día de la migración y ninguna foto
> aparece.

Rango a copiar: **2025-04-07 a 2026-09-07**, unos 17 meses de carpetas diarias.

### Cargadas y verificadas (2026-09-07)

Recibidas como `images.zip` (1,3 GB, 46.503 entradas) y descomprimidas en
`totalsecureapp/backend/public/images/`. **1,4 GB, ~45.300 archivos.**

**La verificación que importa no es contar archivos, es comprobar que las rutas
que la base calcula existan.** Se generó la ruta esperada de cada registro
(`<modulo>/<AAAA/MM/DD de la fecha del registro>/<nombre>`) y se buscó en disco:

| Módulo | Rutas esperadas | Encontradas | Faltan |
|---|---:|---:|---:|
| `biometria` | 12.664 | **12.664** | **0** |
| `rondas` | 29.243 | **29.243** | **0** |
| `novedad` | 6 | **6** | **0** |

Y se comprobó de punta a punta que se sirven: una foto real responde **200
`image/jpeg`** por HTTP.

Al descomprimir se excluyeron los tres archivos de la raíz del zip
(`acceso-login.jpg`, `favicon.ico`, `logo.png`): son **assets de la interfaz que
sí están versionados**, y sobreescribirlos habría ensuciado el repo. Verificado
que quedaron intactos.

> **`public/images/` está en `.gitignore` del backend** (`/public/images`, línea
> 7), así que los 45.300 archivos no entran al repo. Se comprobó con
> `git check-ignore` sobre una ruta concreta **antes** de descomprimir: sobre un
> directorio que todavía no existe la comprobación no es concluyente.

### Dos cosas que aparecieron en el zip

**1. Las fotos de accesos SÍ están referenciadas.** ⚠️ Corrige lo que decía antes
esta sección.

Se afirmó que `acceso` en v1 no tenía columna de foto y que las 3.333 fotos de
`accesos/` eran huérfanas. **Es falso.** `acceso.ac_foto` existe, es de tipo
`text`, y **3.428 filas la tienen poblada**. La búsqueda de columnas de imagen que
llevó a esa conclusión exigía la forma `tipo(numero)` —`varchar(255)`— y por eso
pasó por alto una columna `text`.

Verificado como las demás: **3.428 referencias, 3.237 rutas distintas, todas
presentes en disco, 0 faltantes.** (Algunos accesos comparten archivo, de ahí la
diferencia entre filas y rutas.) Sobran 95 archivos en disco sin ninguna fila que
los referencie.

`ac_foto` existe también en v2, así que el ETL las trae sin nada especial.

**2. Un `2025.rar` de 83 MB dentro del directorio web.** Alguien archivó las fotos
de 2025 y dejó el .rar en `public/images/accesos/`. Nginx sirve todo `public/`,
así que **era descargable por HTTP** (comprobado: respondía 206 a un range
request) — y con el puerto 80 abierto a internet eso queda público. Movido a
`datos-v1/`, fuera del directorio servido; comprobado que ahora responde 404 y que
las fotos siguen dando 200.

---

## 4-bis. El catálogo de ciudades, ya construido

Migración `2026_09_07_200001_sembrar_ciudades_de_v1`, con el mismo patrón
idempotente (`updateOrInsert` por clave natural) que el sembrado de países y
provincias. Crea **15 ciudades**; Guayaquil ya existía.

**Mapeo verificado contra los 137 locales: 137/137, ninguno queda sin ciudad.**

| v1 (texto libre) | → v2 `ciudad` | Provincia | Locales |
|---|---|---|---:|
| `Guayaquil` | Guayaquil | Guayas | 95 |
| `MANTENIMIENTO` | **Guayaquil** | Guayas | 1 |
| `QUITO` | Quito | Pichincha | 16 |
| `MANTA` | Manta | Manabí | 11 |
| `CUENCA` | Cuenca | Azuay | 2 |
| `AMBATO` | Ambato | Tungurahua | 2 |
| `PORTOVIEJO` | Portoviejo | Manabí | 1 |
| `IBARRA` | Ibarra | Imbabura | 1 |
| `STO. DOMINGO` | Santo Domingo | Sto. Domingo de los Tsáchilas | 1 |
| `DURÁN` | Durán | Guayas | 1 |
| `NARANJAL` | Naranjal | Guayas | 1 |
| `EL TRIUNFO` | El Triunfo | Guayas | 1 |
| `Nobol` | Nobol | Guayas | 1 |
| `VILLAMIL PLAYAS` | Playas | Guayas | 1 |
| `SAN CRISTOBAL` | San Cristóbal | Galápagos | 1 |
| `BALTRA` | Baltra | Galápagos | 1 |

Tres decisiones de normalización que conviene poder discutir, no adivinar:

- **`VILLAMIL PLAYAS` → Playas, provincia del Guayas.** Se confunde con Santa
  Elena porque queda en la costa entre las dos; el cantón Playas es del Guayas.
- **`SAN CRISTOBAL` y `BALTRA` son islas, no ciudades.** Se cargan con el nombre
  que usa la operación y no el oficial (Puerto Baquerizo Moreno / Santa Cruz),
  porque es el que reconoce quien mira el panel. Baltra es donde está el
  aeropuerto, lo que encaja con que haya clientes en aeropuertos.
- **`MANTENIMIENTO` no es una ciudad**: alguien escribió un área en ese campo. Va
  a Guayaquil por decisión del usuario. Sin eso, ese local **no pertenecería a
  ningún país y ningún Líder Operativo lo vería**.

**Colombia queda desactivada (`pa_estado = false`), no borrada.** «Por ahora solo
Ecuador» implica que puede volver, y el README dice que se opera ahí; borrarla
obligaría a recrearla con otro id. Chile no se trae: en v1 estaba inactivo y sin
locales.

---

## 5. Lo que no tiene origen, y arranca vacío

Nace sin datos, y está bien que así sea: son funciones que v1 no tenía.

`puesto`, `turno`, `plantilla`, `plantilla_franja`, `plantilla_asignacion`,
`turno_vacante`, `turno_postulacion`, `aviso_envio`, `user_has_pais`, `pais`,
`provincia`, `ciudad`, `acceso_historial`, `acceso_preregistro`,
`acceso_vehiculo`, `acceso_visitante`, `alertas_detalle`, `alertas_historial`.

Dos consecuencias operativas:

- **Cuadrantes y turnos hay que armarlos desde cero** para los 137 locales. Es
  trabajo de negocio, no de migración: no existe el dato en v1.
- **`user_has_pais` vacío significa que ningún Líder Operativo ve nada.**
  `PerfilPanel::localesDelUsuario()` devuelve `[]` (no `null`) para un líder sin
  países, y eso es deliberado: una configuración incompleta no debe convertirse
  en acceso global. Hay que poblarlo como parte de la carga.

---

## 6. Lo que sí calza sin problema

- **Ninguna colisión de cédulas** con los usuarios que creé en v2
  (`0912345678` admin, `1234567890` demo).
- **Ninguna cédula duplicada ni vacía** en las 878 filas de `users`.
- **44 correos repetidos**, pero v2 **no** tiene UNIQUE en `usu_email` ni en
  `usu_cedula`, así que no rompen la carga.
- `user_has_institucion` **sin pares duplicados** en 38.246 filas.
- Los 137 locales **todos** con `ins_so_code`, así que el rescate del cliente
  cubre el 100%.

---

## 7. Consecuencia del fail-open de la geocerca

Con lo corregido el 2026-09-07: si un local no tiene marcador activo, sus
marcajes entran como **ubicación no verificada** en vez de aceptarse en silencio.

Hay **118 marcadores para 137 locales**, así que hay locales sin marcador. Antes
de dar la asistencia por buena conviene mirar cuántos locales quedan sin punto
QR y cargarlos: si no, su asistencia entra marcada como no verificable — visible,
pero no auditable.

---

## 8. Decisiones tomadas (2026-09-07)

| # | Decisión |
|---|---|
| 1 | **Imágenes**: las sube el usuario. Pendiente de recibir `public/images/`. |
| 2 | **Países: solo Ecuador.** Se quita Colombia del catálogo sembrado y no se trae Chile (estaba inactivo y sin locales en v1). |
| 3 | **Se migran los 137 locales**, creando las 15 ciudades que faltaban. El local con ciudad `MANTENIMIENTO` → Guayaquil. **Hecho**: migración `2026_09_07_200001_sembrar_ciudades_de_v1`. |
| 4 | **`ac_nombre_contrato` se copia** a `acceso_visitante.avi_persona_visita`. No se pierde nada. |
| 5 | **Panel arreglado primero**, y la carga va al juego nuevo. **Hecho**: los 4 resources, el RelationManager y el seeder apuntan a `inv_producto_catalogo` / `inv_lista` / `inv_lista_item` / `inv_movimiento_cabecera` / `inv_movimiento_detalle`. Ver AGENTS.md, sección «Inventario: un solo juego de tablas». |
| 6 | **`log` y `log_trafico`: empezar limpio.** No se migran las 2.902 filas de auditoría vieja. |

## 9. Qué falta para poder ejecutar

1. **`public/images/` del servidor de v1** (~42.000 archivos). Es lo único que no
   está en el dump y sin eso las fotos se pierden. **En curso.**
2. **Nada más.** El ETL está escrito y corrido; ver abajo.

### Lo que el ETL de inventario tiene que hacer, ahora que el destino está fijo

Los 23.790 movimientos de v1 **no se copian fila a fila**: en v1 una fila es el
ciclo completo y en v2 cada fila es un evento. Hay que **partir cada movimiento
viejo en hasta tres eventos**, según qué etapas tengan fecha:

| Si tiene… | Se emite un `inv_movimiento_cabecera` con |
|---|---|
| `mov_recep_fecha` | `mc_tipo = 'recepcion'`, `mc_fecha = mov_recep_fecha`, usuario `mov_recep_user`, `mc_lat/lng` de `mov_recep_lat/lng` |
| `mov_devol_fecha` | `mc_tipo = 'devolucion'`, `mc_fecha = mov_devol_fecha`, usuario `mov_devol_user`, lat/lng de `mov_devol_*` |
| `mov_recep_asig_fecha` sin recepción | queda como `mc_estado = 'pendiente'` |

Y los detalles: `md_cant_asign` → `md_cantidad_default`, `md_cant_recep` →
`md_cantidad_real` para el evento de recepción, `md_cant_devol` → el de
devolución. `md_exist` → `md_recibido`, y `md_estado` (booleano) hay que
traducirlo a `ok`/`falta`/`danado` comparando esperado contra contado.

**Los productos son por local en v2.** Los 16 de `inv_productos` eran globales;
hay que crear una fila de `inv_producto_catalogo` **por cada (local, producto)**
que aparezca usado, o las listas quedarían apuntando a productos de otro local.

Con eso definido, el ETL es escribible y verificable tabla por tabla.

---

## Anexo: el MariaDB de análisis

Quedó un contenedor `v1_analisis` (MariaDB 10.4) con el dump cargado, para poder
seguir consultando sin recargar 32 MB cada vez. **Tiene datos de producción.**
Cuando no se necesite:

```bash
docker rm -f v1_analisis
```


---

# El ETL: escrito, corrido y verificado (2026-09-07)

```bash
php artisan etl:v1 --lista        # etapas y su orden
php artisan etl:v1 <etapa>        # una etapa
php artisan etl:v1 --todo         # todas, en orden
```

`app/Services/Etl/EtlV1.php` + `app/Console/Commands/EtlV1Command.php`.

**Cada etapa informa origen y destino, y que los dos números coincidan ES la
verificación.** Si una no cuadra, el comando se detiene: seguir cargaría datos
que cuelgan de lo que no entró.

## Precondición: base recién migrada

El ETL **conserva las claves primarias de v1**. Es lo que hace triviales las
claves ajenas —un `ronda_detalle` sigue apuntando al mismo `ronda_cabecera`— y el
precio es que el destino tiene que estar vacío. Cada etapa lo exige y dice cómo
llegar a ese estado:

```bash
php artisan migrate:fresh --force     # SIN db:seed
php artisan etl:v1 --todo
php artisan usuario:crear --rol=Administrador ...   # el admin va DESPUÉS
```

La geografía (2 países, 24 provincias, 15 ciudades) y los roles y permisos los
siembran **migraciones**, así que `migrate:fresh` los deja listos. El usuario
administrador hay que recrearlo después: `migrate:fresh` lo borra.

## Resultado contra los datos reales

| Etapa | Origen → Destino | |
|---|---|---|
| `clientes` | 21 → 21 | |
| `locales` | 137 → 137 | 0 sin ciudad, 0 sin cliente |
| `usuarios` | 878 → 878 | |
| `gestiones` | 886 → 886 | |
| `roles` | 932 → 919 | 13 descartados, explicados abajo |
| `vinculos` | 38.246 → 38.246 | |
| `marcadores` | 118 → 118 | 25 locales quedan sin punto QR |
| `rondas` | 46.282 → 46.282 | cabecera + detalle |
| `biometria` | 12.664 → 12.664 | |
| `accesos` | 11.405 → 11.405 | + 8.993 vehículo, + 36 visitante |
| `novedades` | 7 → 7 | |
| `alertas` | 278 → 278 | |
| `inventario` | 132 listas | 532 productos, 11.879 eventos, 23.790 detalles |
| `varios` | 194 → 194 | bitácora, tokens push, parámetros |

Y de punta a punta: el panel abre con los clientes reales (DHL, LA FABRIL,
LATAM), los locales con su ciudad, y **una foto de biometría de abril de 2025 se
sirve con HTTP 200 `image/jpeg`** desde la pantalla de marcajes.

## Lo que casi salió muy mal: los ids de rol

**Significan cosas distintas en cada versión.**

| id | v1 | v2 |
|---|---|---|
| 1 | Administrador General | **Supervisor** |
| 2 | Administrador | **Vigilante** |
| 3 | Supervisor | **Cliente** |
| 4 | Vigilante | **Administrador** |
| 5 | Administrador Institucion | **Lider Operativo** |
| 6 | Consola Notificacion | **Consola** |

Copiar `user_has_roles` conservando `role_id` habría convertido a los **867
vigilantes en Administradores** y a los 50 supervisores en Clientes: todo el RBAC
al revés, sin un solo error en el log. Se traduce **por nombre**, con una tabla
explícita en `EtlV1::ROLES`.

Verificado después de cargar: 856 vigilantes, 50 supervisores, 9 consola, 4
administradores — idéntico a v1 agrupado por nombre.

Los 13 descartados son explicables: 11 filas repetidas en v1, más 2 usuarios que
tienen los dos «Administrador», que en v2 son uno solo y chocarían en la clave
primaria compuesta.

## Los otros choques que aparecieron con datos reales

Ninguno se veía en el esquema; todos aparecieron al cargar.

- **Nueve `tinyint` que en Postgres son `boolean`.** Postgres rechaza un `1`
  entero donde espera booleano. El casteo va por el **tipo de destino**, leído de
  `information_schema`: mapear ~130 columnas a mano en 20 tablas era garantizar un
  error silencioso.
- **Fechas `0000-00-00` de MySQL**, que Postgres rechaza. 69 en
  `usu_email_verified_at`. Significan «sin fecha»: van a null.
- **`organizacion.org_created_user` es varchar y trae `'admin'`** en tres filas.
  No es un id y no hay a quién apuntar: va a null, contado en las notas.
- **15 columnas que v2 exige y v1 permitía vacías.** Solo `users` tiene filas así:
  665 sin nombres y apellidos separados, 505 sin correo. Se guardan vacías — el
  nombre completo (`usu_nmbcom`) nunca falta, así que no se pierde a nadie. En una
  columna **numérica no se rellena**: un 0 en una clave ajena crearía un vínculo a
  un local inexistente, que es peor que fallar.
- **`alertas.al_estado_alerta` viene `'Finalizada'`** y el CHECK de v2 solo acepta
  minúsculas. Choque de mayúsculas, no de significado: se normaliza.
- **Las claves primarias de v1 no siguen un patrón**: unas son `*_code` y otras
  `*_id` (`rc_id`, `rd_id`, `nv_id`, `bt_id`). Asumirlo cuesta un fallo por tabla.
- **`av_patente` y `av_kms` eran varchar(20) y no alcanzaban.** En producción son
  texto libre que el guardia escribió: `"Placa GTK-8594 Trailblazer auto"` (31),
  `"CRJ 0809. - SELLO ROTO LEVAPAN. CRJ 3684"` (43). Truncar a 20 no era opción,
  así que se ensancharon a 100 (migración `2026_09_07_300001`).

## `ac_tipo`: la columna que cambió de significado

En v1 es el **medio de transporte**, un entero que apunta a
`acceso_transporte_tipo`: 1=Caminando, 2=Bicicleta, 3=Moto, 4=Vehiculo. Se
confirma con los datos: las 1.320 filas con `ac_tipo=4` son las únicas que traen
patente.

En v2 es el **tipo de acceso**: peatonal / vehicular / proveedor / empleado /
visitante. Copiar el entero habría dejado `"1"` y `"4"` en una columna de texto, y
`Acceso::esVehicular()` habría dicho que ningún acceso lo es.

Se traduce: 1 y 2 → `peatonal`, 3 y 4 → `vehicular`. Y **la bicicleta no se
pierde**: `ac_tipo=2` pone `ac_bicicleta = true`, que es la columna que v2 tiene
para eso (en v1 `ac_bicicleta` está casi sin usar, 17 filas en 9.769).

## Inventario: la etapa que no puede conservar los ids

Dos cambios de forma, y es el único lugar del ETL donde los ids se reasignan.

**1. Los productos eran globales y ahora son por local.** Los 16 de
`inv_productos` se convierten en **532** filas de `inv_producto_catalogo`, una por
cada par (local, producto) realmente usado. Un id de v1 pasa a ser N de v2, así
que hace falta un mapa.

> El catálogo se arma con los pares de **las listas Y de los movimientos**. Nueve
> detalles apuntan a un producto que ya no está en ninguna lista de su local
> (locales 22, 158 y 162): probablemente la lista se cambió después. Armándolo
> solo desde las listas, esos nueve detalles se descartaban en silencio.

**2. Un movimiento era el ciclo completo y ahora cada fila es un evento.** Los
5.963 movimientos tienen fecha de recepción y 5.916 también de devolución, así que
salen **11.879 eventos**. Ninguno tiene asignación ni entrega, así que esas dos
etapas no generan nada.

> **El detalle solo se crea para la recepción.** `md_cant_devol` está NULL en las
> 23.790 filas de v1: la devolución se registraba solo en la cabecera, nunca
> producto por producto. Crear detalles para el evento de devolución obligaría a
> inventar la cantidad devuelta, y «asumo que devolvió todo» es exactamente la
> clase de dato que después alguien lee como si fuera real. El evento de
> devolución queda con su cabecera, que es todo lo que v1 sabía.

`md_estado` pasó de booleano a `ok`/`falta`/`danado`. Se deduce comparando contado
contra esperado; **`danado` no tiene origen** y no se usa.

## Un detalle que solo aparece en producción

Insertar ids explícitos **no mueve la secuencia de Postgres**. Sin ajustarla, el
primer registro que cree la aplicación arranca en 1 y falla con violación de clave
primaria — y el síntoma aparece recién cuando un guardia marca por primera vez.
Cada etapa la ajusta con `setval` al terminar.

## Pendientes después de cargar

- **25 locales sin marcador activo**: su asistencia entrará como «ubicación no
  verificada» hasta que se les cargue el punto QR.
- **`user_has_pais` vacío**: ningún Líder Operativo ve nada hasta que se pueble.
  Hoy no hay usuarios con ese rol, así que no bloquea.
- **Cuadrantes y turnos desde cero**: no existen en v1.
- **95 fotos de accesos** en disco sin fila que las referencie.

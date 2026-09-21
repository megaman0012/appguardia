# Roadmap Total Secure App

**Fecha:** 2026-09-15 · **Base:** pendientes previos (auditoría 2026-09-15) +
observaciones de uso del cliente + carga de puestos y horarios.

Cada punto trae el **diagnóstico verificado en código**, no la descripción del
síntoma. Donde dice `NO VERIFICADO` es porque hacía falta consultar la base de
producción y no se hizo.

---

## Estado al 2026-09-21 (tercera jornada)

**609 tests en verde.** Commits `ff66a16`, `afcc2e4`. Producción sana.

### ⚠️ Un local con turnos programados no se desactiva

Al aplicar la lista de locales quedaron fuera 4 locales con **268 turnos, 134 de
hoy en adelante** (hasta el 4-oct). `CerrarTurnosDelDia` solo recorre locales
activos, así que **esos turnos dejaron de cerrarse esa noche** — sin error y sin
aviso. Reactivados (170, 120, 203, 151); quedan 88 activos y 0 turnos huérfanos.
El comando ahora los detecta y no los toca.

### Puestos al día

`puestos:sincronizar`: 55 renombrados, 16 actualizados, 66 creados, 6
desactivados. **148 puestos activos** (antes 84, uno por local). HOSPITAL LUIS
VERNAZA pasa a 20 puestos y Cementerio General a 18. **558 turnos intactos.**

⚠️ **Renombrar y no recrear** es lo que salva la programación: los puestos se
generaron con el nombre del local y la lista les da nombre propio. Cuando sobra
uno y falta uno, se renombra y conserva su `pu_id`. 55 de 137 casos.

### 🔴 Abierto: los dos archivos se contradicen

La lista de puestos nombra **103 locales, de los que 14 no existen**: son nombres
**consolidados** (`SWISSPORT` por los cinco `SWISSPORT MATRIZ`/`CARGA`/…,
`NEUROCIENCIAS` por sus dos garitas, `INC` por sus tres). Es la reorganización
pospuesta, y **choca con la lista de locales**, que mantiene los sitios separados.

Se decidió que manda la lista de locales: esos **40 puestos no se cargaron** y
quedan en `docs/puestos-sin-local-2026-09-21.csv` con su motivo.

**Si algún día se consolida**, es una migración de histórico: reasignar accesos,
rondas, biometrías, turnos e inventario de los `ins_code` viejos al nuevo, y
desactivar los viejos después. No es una carga.

## Estado al 2026-09-21 (segunda jornada)

**599 tests en verde.** Commit `37d5136`. Producción sana.

### Locales puestos al día contra la lista final del cliente

`locales:sincronizar` con `docs/locales-2026-09-21-0957.xlsx`:

| | |
|---|---|
| Locales en el archivo | 84, todos existían ya |
| Actualizados | 18 nombres, 42 ciudades, 11 clientes |
| Desactivados | **91** (activos que no estaban en la lista) |
| Cliente creado | CHILENITA |
| Resultado | **84 activos**, 104 inactivos |

✅ **Se cerró de paso el pendiente de los 51 locales sin cliente**: ya no queda
ninguno activo sin asignar, así que el resumen del cliente se puede enseñar.

⚠️ **Se desactivan, no se borran.** Un borrado habría fallado (40 vacantes con FK
`NO ACTION`), destruido en cascada 3.388 movimientos con sus 6.802 detalles, y
dejado **22.857 filas huérfanas** —18.707 vínculos, **2.741 biometrías**, 1.149
rondas— porque esas tablas no tienen clave foránea al local. Eso no falla ni
avisa: los reportes empiezan a mostrar vacíos.

### Dos bugs que este trabajo destapó

- ⚠️ **`allInstitucions` no miraba `ins_estado`**: el endpoint que le da los
  locales a la tablet filtraba solo por el vínculo usuario-local, así que
  desactivar un local **no lo quitaba de la pantalla del guardia**. Sin
  arreglarlo, desactivar los 91 no habría servido de nada.
- ⚠️ **El resumen de inventario contaba locales retirados**: 59 listas con 236
  items colgando de puestos desactivados seguían sumando en el reporte del
  cliente. Nadie lo habría notado — el número sale mayor, no roto.

## Estado al 2026-09-21

**590 tests en verde.** Commits `854bc69` (descarga del resumen) y `81cecb6` (kit
de puesto). Producción sana.

### El kit de puesto

Había **132 listas y 130 idénticas**: una plantilla copiada a mano, con erratas
(`SEGURIDA FISICA` junto a `SEGURIDAD FISICA`). Dar inventario a un local nuevo
eran ~22 interacciones; agregar un producto a todos, 132 ediciones.

⚠️ **`inv_lista` e `inv_lista_item` conservan su forma**: son lo que lee la app
del guardia y contra lo que se registran los 23.799 movimientos. El kit gobierna
cómo se *escriben* las listas, no cómo se leen. Se descartó el diseño que las
calculaba en vivo justo porque tocaba el camino operativo.

⚠️ **Lo que costó acertar:** «modificada» se compara contra el kit **tal como
estaba al aplicarlo** (`li_kit_huella`), no contra el kit actual. Comparar con el
actual se rompe al agregar un producto al kit: las 132 listas pasan a diferir,
todas quedan marcadas y el cambio no se propaga a ninguna. Y la **bandera**
(«es una excepción declarada») es distinta de la **huella** («alguien editó
esto»): mirar solo la huella dejaba pasar la excepción migrada. Los dos casos
los cazaron tests.

Migración aplicada: **132 listas → 2 kits**, 2 marcadas como apartadas (las dos
«Oficina Garzota», sin forros de chaleco), 526 items y 23.799 movimientos
intactos. La excepción ahora se ve: columna «Sigue al kit» y filtro en Listas.

### «Stock por cliente»: eliminado

Se creó el 19-sep y se quitó el 21 tras aclararlo con la operación: **no manejan
stock ni bodega, solo tienen o no tienen**, y el equipo nuevo o dañado va a otros
departamentos. No era bodega, no era la cifra del contrato, y como número
derivable ya era «locales × 1». Nunca tuvo una fila.

### Abierto, del propio análisis del inventario

- **El circuito de equipo dañado o faltante no existe.** 814 detalles en «falta»,
  **0 en «dañado»** y **0 movimientos de tipo «baja»** — los dos estados existen
  en el modelo y nunca se han usado. Cuando algo se rompe no hay registro de a
  qué departamento se entregó ni si llegó el reemplazo. Decidido dejarlo fuera
  por ahora.
- ⚠️ **«Entrada a Consulta Externa del Hospital…» acumula 360 de las 814 faltas:
  el 44% en un solo puesto.** No parece pérdida aleatoria; o ese puesto nunca
  tuvo el equipo que su lista exige, o hay algo sistemático. Sin mirar.

## Estado al 2026-09-19 (segunda jornada)

**577 tests en verde.** Commits `1951624` (alarma), `5e22fc4` (catálogo global),
`04ca33b` (stock por cliente + resumen).

### La alarma: por qué el primer arreglo no bastó

Se comprobó la cadena entera antes de tocar nada: el servidor **sí** despacha
`emergencia-nueva` (test aislado), el HTML autenticado trae todo (volcado y
verificado), la CSP permite `unsafe-inline`, OPcache revalida. Nada de eso era.

⚠️ **El panel no usa SPA**, así que cada clic en el menú es una carga completa de
página, y el permiso de audio del navegador es **por documento**: se perdía
entero en cada navegación. Había que pulsar «Activar alarma» **en cada pantalla
que se abriera**.

Ahora el audio se desbloquea con **cualquier** interacción (`pointerdown`,
`keydown`, `touchstart`). Más: el **título de la pestaña parpadea** —lo único que
se percibe con el panel en segundo plano, y no pide permiso—, el cartel se dibuja
aunque no suene, y el aviso de bloqueo es un banner, no un botón en una esquina.

Las notificaciones de escritorio serían lo ideal con el navegador minimizado,
pero el navegador las bloquea sin HTTPS: quedan disponibles al cerrar SEC-01.

### Inventario reestructurado

| | |
|---|---|
| **Catálogo global** | 532 filas eran **4 productos repetidos en 133 locales**. Fusionado: 519 items y 23.349 detalles reapuntados, cero huérfanos |
| **Stock por cliente** | Tabla nueva: cuánto se le asignó a cada cliente. Detecta si se repartió de más |
| **Resumen de inventario** | Reportería: clientes × productos, con desglose por local al pulsar la fila |

⚠️ **Antes de enseñarle el resumen al cliente hay que resolver los 51 locales sin
`ins_cliente_id`**: salen agrupados en una fila «Sin cliente asignado». No se
esconden a propósito — esconderlos haría que el total cuadrara y estuviera
mintiendo.

### Pendiente decidido y no hecho

- **Tema propio de Filament con Vite + Tailwind.** Aprobado, no empezado.
  ⚠️ Añade un paso de compilación al despliegue en un repositorio donde **el
  árbol de trabajo ES producción**; conviene sopesarlo antes.

## Estado al 2026-09-19

**554 tests en verde** (eran 541). Commits `a57af9e` (documentación) y `05295cc`
(los cinco puntos de abajo). Producción sana, contenedor `healthy`.

### Cerrado hoy

| | |
|---|---|
| **La alarma no sonaba** | Tres fallos encadenados, ver 2.6 |
| **«Personas dentro» decía 9.769** | Era falso: 6.436 ya habían salido. Ver 2.7 |
| **Perfiles › Editar estaba vacío** | Formulario + permisos por perfil. Ver 2.8 |
| **Bitácora → Novedades** | Panel y app, acordado con el cliente |
| **Arranque de la app** | Tras la biometría sigue el inventario, no el menú |
| — | Informe de auditoría coherente y `docs/generar-documentos.sh` |

### ⏳ Esperando el APK (se acumulan a propósito)

Se hace **una sola versión al final**, cuando estén todas las adecuaciones. Lo
que ya está en el código y todavía no llega a las tablets:

- «Bitácora» → «Novedades» en el menú lateral, el Home y el título de la pantalla.
- Tras la biometría de entrada se va al **inventario**, y de ahí al menú.
- Y lo que ya venía pendiente: la **1.0.7 nunca se instaló en tablet**, y por
  debajo de la 1.0.2 no existe el botón de pánico ni la recuperación de clave.

Al compilar hay que subir `versionCode` **a mano y en dos archivos**
(`android/app/build.gradle` y `app.json`), correr `npm install` antes, y **no
correr los tests del backend justo antes** (deja `storage/framework/testing` en
un estado que rompe la compilación).

## Estado al 2026-09-17 (cierre de la jornada)

`main` = `migracion-laravel-filament` = **eea8942**, ambas en GitHub. Árbol
limpio. **541 tests en verde.** Producción: Laravel 13.31, `production`, debug
apagado, contenedor `healthy`.

### Cerrado en estas jornadas

| | |
|---|---|
| SEC-00 | Toma de cuentas, por la API **y** por el portal |
| CONT-01 | Respaldo diario, probado restaurando |
| SEC-05 | Fuga entre clientes (rondas y marcadores) |
| — | Botón de pánico: avisaba a nadie → ahora suena en todo el panel |
| — | El QR que no salía, las novedades sin foto, la hora del pre-registro |
| — | 130 + 51 locales, 182 puestos, **558 turnos** |
| — | 5 fallos de infraestructura: 500 del panel, límite de subida, sesión de 1 h, workers, timeouts |
| APK | **1.0.7** (versionCode 8), firmado y verificado dentro del bundle |

### Abierto, y por qué

**Esperando una decisión externa:**
- **HTTPS (SEC-01).** El único crítico que queda. `docker-compose.prod.yml` con
  certbot está listo; falta el dominio. Hasta entonces la biometría de 880
  personas viaja sin cifrar y el APK mantiene `usesCleartextTraffic`.
- **Copia del respaldo fuera del servidor (CONT-02).** El script ya tiene el paso
  (`DESTINO_EXTERNO`) y avisa en cada corrida; falta a qué máquina copiar.

**Esperando un dato de la operación:**
- **147 de 269 personas** de la malla no cruzan por nombre. Con un listado de
  cédulas, el cruce sería exacto.
- **41 filas de `docs/mapa-proyectos.csv`** sin `ins_code` (~300 celdas de turno).
  Referencia en `docs/locales-disponibles.csv`.
- Revisar en el panel los **51 locales creados**: los que no correspondan se
  desactivan.

**Deuda técnica, nada urgente:** miniaturas de fotos (21,5 MB por página en
Accesos), worker de cola (hoy `sync`), las 57 tablas sin documentar,
`v1_analisis` fuera de todo compose, `openapi.yaml` sin contrastar, y migrar
`InvMovimientoDetalleResource` a `PerfilPanel::localesVisibles()`.

---

## Bloque 0 — Seguridad. Va primero porque está expuesto a internet

### 0.1 ✅ Toma de cualquier cuenta (SEC-00) — CERRADO (2026-09-15)

**Hecho y desplegado**, por las dos puertas: la API y el portal web tenían el
mismo agujero, y cerrar sólo una habría dejado la otra abierta.

- `App\Services\RecuperacionDeClave`: código de 8 dígitos con `random_int`,
  **guardado hasheado**, con vencimiento de 30 minutos, de un solo uso, e
  invalidado al quinto intento fallido. La comparación usa `hash_equals`.
- El código **ya no vuelve en la respuesta**, y la respuesta es idéntica exista o
  no la cédula, para que el endpoint no sirva para averiguar quién está
  registrado.
- `procesar_paswchg` ya no acepta `user_id`: identifica por cédula + código.
- En el portal, `cambiar_password/{codigo}` valida el enlace y deja al usuario
  autorizado **en la sesión**; `procesar_cambiopass` lo lee de ahí e ignora lo
  que venga en el formulario. Antes tomaba el `user_id` del propio formulario.
- Se entrega por WhatsApp o correo en cuanto alguno esté configurado. **Hoy no
  hay ninguno** (`mailhog` no existe y el gateway está vacío), así que el
  autoservicio queda sin efecto y el cambio lo hace el supervisor desde el panel,
  con la acción «Cambiar contraseña» de Usuarios, que ya existía. Decidido así: un
  autoservicio que entrega la cuenta a cualquiera es peor que no tenerlo.
- La app se actualizó al contrato nuevo (pide el código). **El APK 1.0.2 ya
  instalado no puede recuperar clave**: manda `user_id` y recibe error. Hay que
  recompilar.
- 15 tests nuevos (`RecuperacionDeClaveTest`), incluidos los dos que reproducen
  el agujero por cada puerta. Suite: **450 en verde**.

**De paso se corrigieron las dos validaciones muertas** que ya estaban
documentadas: `usu_password == Hash::make($password)` nunca era cierta (bcrypt
sala distinto cada vez), y la que comparaba el hash contra el texto plano
diciendo «no puede ser el usuario» quería comparar contra la cédula.

**Pendiente derivado — CERRADO el 2026-09-19.** Los entregables se regeneraron.
El diagnóstico previo era impreciso: los `.md` ya se habían actualizado el 15-sep
(commit `6d3edfb`), pero **quedaron contradiciéndose a sí mismos** — el resumen
ejecutivo y la lista de prioridades seguían describiendo SEC-00 y la falta de
respaldo en presente, debajo de una tabla que los daba por cerrados. Eso es peor
que estar desactualizado: el lector no sabe a cuál de las dos mitades creerle.

Corregido, y de paso puesto al día con el 16 y 17 de septiembre (APK 1.0.7, no
1.0.2; healthcheck del backend; GES-01 cerrado al publicar en GitHub).

**El conversor quedó guardado en `docs/generar-documentos.sh`**, que antes se
hacía a mano. Tres trampas que vale la pena no volver a descubrir: el PDF sale
del **dompdf del backend** porque en este servidor no hay ningún motor de PDF
instalado; el contenedor **no ve la raíz del monorepo** (solo se monta
`backend/`), así que el HTML pasa por `storage/app/docgen/`; y **a DejaVu le
faltan los emoji de severidad** (🔴🟠🟡🔵🟢 y ✅), que salían como un hueco —18 en
el informe—, así que la rama del PDF los sustituye por un círculo `U+25CF` del
color que toca. El `.html` y el `.docx` conservan el emoji original.

### 0.1-bis (diagnóstico original, para referencia)

`Modules/MobileApp/Routes/api.php:19` publica `procesar_paswchg` fuera de
`api.auth`. En `LoginController::procesar_cambiopass` (líneas 170-211) el método
lee `user_id`, `password`, `password2` y **nunca comprueba `remember_token`**.
El `user_id` es un entero secuencial. Cualquiera en internet cambia la
contraseña de cualquier usuario, incluido un Administrador.

Agrava: `solicitud_cambiopass` (líneas 153-160) devuelve `user_id` y `token` en
la respuesta JSON, y el token es `rand(1000, 10000000)` — 10 millones de
valores, sin expiración y sin un solo intento de aleatoriedad criptográfica.

**Arreglo:** exigir el token en `procesar_cambiopass` y compararlo con
`hash_equals`; generarlo con `random_bytes(32)`; no devolverlo en la respuesta;
darle caducidad (30 min) e invalidarlo al usarlo. Mitigación de hoy mismo, si el
arreglo no entra ya: cerrar las dos rutas en `docker/nginx/default.conf`.

### 0.2 ✅ Respaldo inexistente — HECHO (2026-09-15), con una salvedad

`backend/scripts/respaldo.sh`, diario a las 03:00 por el crontab de `server-dt`,
siguiendo el patrón del respaldo de la ticketera. Guarda en
`~/respaldos/totalsecureapp/` — **fuera del repositorio**, porque acá el árbol de
trabajo es producción y ya se coló un dump en un commit y un `.zip` de 63 MB que
llegó a GitHub.

Cubre las cuatro cosas irrecuperables: la base PostgreSQL (formato custom, para
poder restaurar una sola tabla), la MariaDB de la V1 que no pertenece a ningún
compose, los secretos con `APP_KEY`, y el **keystore de firma**. Paquete diario
de ~7 MB, 14 días de retención. Las fotos van aparte por `rsync` incremental
—1,3 GB que casi no cambian— y sin `--delete`, para que un borrado accidental en
el servidor no se propague al espejo.

**Probado, no sólo escrito:** primera corrida completa sin errores, y el
resultado verificado — 58 tablas con datos en el dump, gzip de la V1 íntegro, y
el keystore del paquete con el mismo SHA-256 que el original. El procedimiento
de restauración está en `backend/scripts/RESPALDO.md`.

⚠️ **La salvedad, que sigue abierta: todo queda en este mismo servidor.** Sirve
contra un borrado accidental o una tabla corrupta; no sirve si se pierde el
servidor, que es justo el caso en el que el keystore no se recupera. El script ya
tiene el paso de copia externa (`DESTINO_EXTERNO`) y avisa en cada corrida
mientras esté vacío: **falta decidir a qué máquina copiar**. Eso es CONT-02 y no
se cierra hasta que exista ese destino.

### 0.2-bis (diagnóstico original, para referencia)

Verificado en el crontab de `server-dt`: sólo hay respaldo de la ticketera, y
traccar tiene su propio timer. Total Secure App **no tiene ninguno**. Cuatro
cosas irrecuperables: la base (880 personas, 12.664 biometrías), los secretos,
la base V1 de `v1_analisis`, y `apk/totalsecureapp-release.jks` — sin ese
keystore no se puede volver a actualizar la app en ninguna tablet.

**Arreglo:** respaldo diario siguiendo el patrón de `respaldo-semanal.sh` de la
ticketera, y copia del keystore fuera del servidor.

### 0.3 🔴 Sin HTTPS

Biometría y credenciales de 880 personas viajan en claro. `docker-compose.prod.yml`
ya tiene nginx 1.27 + certbot escrito y sin usar. Requiere decidir dominio y
tocar el NAT del router. Luego hay que quitar `usesCleartextTraffic` del APK y
recompilar.

### 0.4 ✅ Fuga entre clientes en el panel — CERRADA (2026-09-15)

`RondaDetalleResource` e `InstitucionMarcadoresResource` ya acotan por perfil,
con los dos alcances: por institución (Supervisor) y por país (Líder Operativo).

- En rondas el filtro sube a la cabecera (`rc_ins_code`) y no usa el
  `rd_ins_code` del propio detalle: quien decide de qué local es una ronda es su
  cabecera, y así este filtro dice exactamente lo mismo que el del listado padre.
- En marcadores el filtro es directo sobre `im_ins_code`.
- **Y se cerró la otra vía**, que no estaba en el diagnóstico: el formulario de
  marcadores manda el local en un `Hidden` cuyo valor sale de `?codigo=`. Un
  campo oculto es oculto para la pantalla, no para quien arma la petición, así
  que un Supervisor podía **crear** un marcador en el local de otro cliente.
  `CreateInstitucionMarcadores::mutateFormDataBeforeCreate()` lo rechaza.
- `PerfilPanel::localesVisibles()` resuelve los dos alcances en un solo lugar.
  El patrón estaba copiado en cada recurso que acotaba, y **donde no se copió
  quedó el agujero**. Devuelve `null` (ve todo), `[]` (no ve nada) o la lista;
  esa distinción es la que evita que una configuración incompleta se vuelva
  acceso global.

12 tests nuevos (`AlcanceDeDetallesTest`). Suite: **462 en verde**.

**Nota:** `InvMovimientoDetalleResource` sigue con el patrón copiado a mano. No
se tocó porque funciona y está probado, pero podría migrarse a
`localesVisibles()` para que quede una sola forma de hacerlo.

### 0.4-bis (diagnóstico original, para referencia)

`RondaDetalleResource.php:131` e `InstitucionMarcadoresResource.php:159` filtran
sólo por el parámetro de la URL (`?ronda=`, `?codigo=`), sin consultar el perfil.
Un Supervisor cambia el número a mano y lee rondas de otro local — y en
Marcadores **edita las coordenadas del QR de otro cliente**. El patrón correcto
ya está resuelto al lado, en `InvMovimientoDetalleResource.php:155-173`.

### 0.5 Menores

- Cambiar la clave del admin `0912345678`, reasignada el 2026-09-09 para la
  validación del cliente.
- `Modules/Acceso/Models/users.php:66-69`: un `saving()` comentado que pisaba
  toda contraseña con `123456`. Borrarlo; está a un descomento de ser un
  incidente.
- `storage/logs/schedule-cron.log` sin rotación: 834 KB en seis días.

---

## Bloque 1 — Datos base: puestos y horarios

Sin esto, Turnos, Cuadrantes y Vacantes no tienen nada que mostrar, y varias
observaciones del Bloque 3 no se pueden ni probar.

### 1.1 Cargar los puestos

`puestos-propuesta.csv` (66 locales → 16 sitios) está validado por el cliente.
La tabla `puesto` existe (`pu_ins_code`, `pu_nombre`, `pu_lat`, `pu_lng`, único
por local+nombre). Carga directa con un seeder idempotente.

### 1.2 Cargar la malla de septiembre

`docs/HORARIO-SEPTIEMBRE2026.xlsx`, analizado. Contenido real:

| Hoja | Qué es |
|---|---|
| `ESTRUCTURA` (559 filas) | dotación contratada por cliente: puesto, horas de servicio, CECO, modalidad |
| `DOTACION` (45 filas) | resumen de puestos por tipo de jornada |
| 10 hojas de zona | **la malla**: NORTE, CENTRO, CEMENTERIO, C.C Y LOTERIA, REGIONAL, RANGER, SEMEDIC, CASA TOTAL, SUPERVISORES, DHL MECANO |
| `CONTROL FLOTANTE`, `HORARIO FLOTANTE` | personal de reemplazo |

Cada hoja de zona es una cuadrícula: puesto, guardia (nombres + apellidos) y una
letra por día. Vigencia **desde el 7 de septiembre**, 28 días (35 en RANGER y
DHL MECANO).

Volumen extraído: **~300 guardias** y **6.151 marcas de turno** reales.
Distribución de códigos:

`D` 2.726 · `N` 1.938 · `X` 1.487 · `A` 112 · `T` 56 · `D1` 54 · `C` 48 ·
`D2` 24 · `B` 24 · `Z` 24 · `V2` 5 · `P` 4

El destino es la tabla `turno`, una fila por guardia y día trabajado:
`tu_ins_code`, `tu_usu_id`, `tu_puesto_id`, `tu_fecha`, `tu_hora_inicio_prevista`,
`tu_hora_fin_prevista`, `tu_estado='programado'`.

**Diccionario de marcas: RESUELTO (2026-09-15).** Estaba en el propio Excel, al
pie de cada hoja, en una tabla «NOMENCLATURA / TURNOS LABORAL / PROYECTO».
Confirmado con el cliente: **D = 07:00–19:00, N = 19:00–07:00, X = libre**, que
son el 98% de la malla.

⚠️ **El detalle que decide si la carga sale bien: la nomenclatura es POR
PROYECTO, no global.** `D1` es 10:00–16:00 en Cementerio, 07:00–13:00 en
Neurociencias y 08:00–20:00 en Chilenita. En C.C. y Lotería hay **tres
definiciones distintas de `D`** (07–19, 10–22, 08–19), una por proyecto. Cada
mini-tabla aparece justo encima del bloque al que aplica, así que el importador
tiene que leer el documento **en orden** y asociar cada tabla al bloque que la
sigue. Uno que tome una nomenclatura global se equivoca en cientos de turnos.

**Cifras reales, ya con las filas de encabezado descartadas** (`L M M J V S D` se
colaban como si fueran marcas): **269 guardias, 40 proyectos, 5.008 turnos
trabajados** y 1.487 días libres.

**Lo que sigue bloqueando la carga:**
2. **El cruce de personas.** El Excel trae nombres y apellidos; la base
   identifica por cédula. El cruce por nombre tiene homónimos y hay que
   resolverlo caso por caso. `NO VERIFICADO`: cuántos de los ~300 guardias
   existen ya como usuario.
3. **El cruce de puestos.** Los nombres del Excel («Edificio ADM Sala»,
   «Parqueo Sala de Velación», «Sala Oratoria») hay que mapearlos contra los
   `ins_code` reales.

**Propuesta:** un comando `horarios:importar` con `--dry-run` que emita el
informe de lo que casa y lo que no, antes de escribir una sola fila. Nada entra
a la base de 880 personas sin que ese informe esté revisado.

---

## Bloque 2 — Bugs confirmados de las observaciones

Los cinco tienen causa raíz localizada.

### 2.1 ✅ El botón de pánico no avisa a nadie — RESUELTO (2026-09-15)

**Hecho y desplegado.** La migración `2026_09_15_100001_avisos_de_alerta` corrió
en producción (crea `notifications`, agrega `aviso_envio.ae_al_code`), se
publicaron los activos de Filament y se limpiaron las cachés de configuración y
vistas. Verificado después: `/acceso/login` 200, `/admin` 302 al login, el JS de
Livewire 200 por su ruta con hash, y las dos IP públicas en pie.

Lo que se hizo:

- `App\Services\NotificadorAlerta`, con el mismo diseño que `NotificadorVacante`:
  canales desde `config/avisos.php` y constancia de cada intento en `aviso_envio`.
- `App\Listeners\AvisarAlertaCreada`, registrado en `EventServiceProvider`. El
  evento se emitía desde el principio y **no lo escuchaba nadie**.
- `App\Services\Avisos\CanalPanel`, la campanita del panel, más
  `->databaseNotifications()` en `AdminPanelProvider` con sondeo de 10 s. Es el
  único canal que no depende de Firebase ni del gateway de WhatsApp.
- El aviso va a supervisores del local, Consola y Administradores; nunca al
  propio guardia que pidió auxilio. Lleva enlace a mapa sólo si el GPS respondió
  de verdad: la app manda `0/0` a propósito cuando no hay lectura.
- 11 tests nuevos (`AvisoDeAlertaTest`). Suite completa: **435 en verde**.

**Dos bugs que aparecieron al implementarlo:**

- `AlertaService::buscarSupervisorPorNivel()` usaba `whereHas('instituciones')`,
  una relación **que no existe en ninguno de los dos modelos `users`** — el mismo
  error que ya se había corregido en `asignarASupervisor` y que quedó sin
  corregir acá. **Escalar una alerta reventaba siempre.** Ya usa
  `user_has_institucion`.
- El evento se emitía **dentro** de la transacción: se avisaba de una alerta que
  aún podía no existir, y la transacción quedaba abierta durante los 8 s de
  timeout del gateway de WhatsApp. Ahora sale después del commit.

### 2.1-bis (diagnóstico original, para referencia)

**Es el más grave de este bloque: es un botón de emergencia que no emite la
emergencia.**

La app envía bien (`AlertasScreen.tsx:100`, a `ALERT_CREAR`) y el backend guarda
bien — por eso quedó el registro. Lo que falla es el aviso:
`AlertaService.php:45` emite `event(new AlertaCreada($alerta))`, la clase
`App\Events\AlertaCreada` es un `ShouldBroadcast`, y **el `.env` tiene
`BROADCAST_DRIVER=log`**. El evento se escribe en un archivo de log y ahí muere.
No hay ningún listener registrado, y `AlertasActivasWidget` es un contador del
tablero que sólo cambia si alguien abre el panel y refresca.

**Arreglo:** un listener que notifique de verdad — WhatsApp al supervisor
asignado (el gateway ya existe y funciona) y notificación de Filament en el
panel. Encender un driver de broadcasting real es opcional; el aviso al
supervisor no lo es. Ojo con `QUEUE_CONNECTION=sync`: mientras la cola sea
síncrona, el envío del WhatsApp retrasa la respuesta al guardia.

### 2.2 ✅ El QR no sale en el PDF — RESUELTO (2026-09-15)

Se declaró `data://` en `allowed_protocols` de `config/dompdf.php`. **No se tocó
`enable_remote`**, que sigue apagado: `data://` no hace ninguna petición —los
bytes viajan en la propia URL— y por eso dompdf no le aplica ninguna regla. Los
que salen a la red son `http://` y `https://`, y ésos siguen cerrados.

Medido antes y después sobre el PDF real: **2 imágenes antes, 3 después**. El
test compara contra la configuración anterior en vez de contra un número fijo,
así que sigue valiendo si alguien cambia la plantilla.

### 2.2-bis (diagnóstico original)

Sale la plantilla y el recuadro, sin el código. Causa: `generalTrait.php:209`
genera el QR como data URI (`data:image/png;base64,...`) y la plantilla lo
inserta en un `<img>` (`pointcontrol.blade.php:92`). Pero **dompdf es la v3.1.6,
y desde la 2.0 el esquema `data:` quedó bajo el control de `enable_remote`**,
que en `config/dompdf.php:287` está en `false` — apagado a propósito, y bien, por
SSRF. dompdf entonces descarta la imagen en silencio y dibuja el resto.

**Arreglo:** no encender `enable_remote`. Escribir el PNG del QR a un archivo
temporal y referenciarlo por ruta local, o habilitar únicamente el protocolo
`data://` en `allowed_protocols`.

### 2.3 ✅ Las novedades desde la web no guardan foto — RESUELTO (2026-09-15)

El formulario estaba vacío, así que no fallaba la foto: **se guardaba una novedad
en blanco**. Ahora pide local, quién reporta, fecha y hora, observación, foto y
coordenadas. El desplegable de locales está acotado por perfil, para que un
Supervisor no registre una novedad en el local de otro cliente.

La foto se guarda por un disco nuevo, `imagenes`, que apunta a `public/images`
—donde las viene dejando `generalTrait::storeFiles()` desde la V1— en vez de
abrir un segundo árbol de archivos que nadie sabría servir.

**Hallazgo que apareció al hacerlo, y que sigue abierto:** `storeFiles()` arma la
carpeta con `Carbon::now()` mientras el accesor `imagen_url` la reconstruye con
`nv_fecha_hora`. Para una novedad creada y sincronizada el mismo día coinciden,
pero **una que la tablet sincroniza al día siguiente queda con la foto en una
carpeta y el registro apuntando a otra, y la foto no aparece**. El accesor ya
acepta rutas relativas completas —que es lo que guarda el panel— y eso cierra el
hueco para lo nuevo; migrar `storeFiles()` toca también biometría y accesos, así
que va aparte. **Vale revisar cuántas fotos de novedades ya están perdidas por
esto.**

### 2.3-bis (diagnóstico original)

`NovedadResource.php:48`: `public static function form(Schema $schema): Schema
{ return $schema->schema([ ]); }` — **el formulario está vacío**, y sin embargo
existen las páginas `CreateNovedad` y `EditNovedad`. Se guarda el registro sin
foto y sin ningún otro campo porque no hay dónde ponerlos.

**Arreglo:** escribir el formulario, con `FileUpload` que respete la convención
de `public/images/novedad/<fecha>/` que ya usa el modelo (`Novedad.php:49`).

### 2.4 ✅ Preregistro: no se puede escribir la hora — RESUELTO (2026-09-15)

Los campos llevan máscara: se teclean sólo los dígitos y la app pone el `-` y el
`:`. Más validación de que la hora existe (00:00–23:59), que antes no se
comprobaba.

No se usó un selector nativo de fecha a propósito: sería un módulo nativo nuevo,
y en este proyecto `android/` está versionado, así que `expo prebuild` rehace la
carpeta entera. La máscara resuelve lo mismo sin tocar la compilación.

### 2.4-bis (diagnóstico original)

`PreregistroFormScreen.tsx:103-116`: fecha y hora son `TextInput` con
`keyboardType="numeric"`. El teclado numérico de Android **no trae `:` ni `-`**,
así que efectivamente «sólo salen números» y es imposible separar horas de
minutos. El campo ya está separado en la base (`apr_fecha_estimada` y
`apr_hora_estimada` son dos columnas).

**Arreglo:** selectores nativos de fecha y hora en lugar de texto libre.

### 2.5 ✅ «Quiero cubrir turnos extra» — RESUELTO (2026-09-15)

**Corrección del diagnóstico:** la app *sí* recarga el valor desde la API cada
vez que se abre Vacantes o Perfil, así que no había un problema de
sincronización. Lo que faltaba era el control: **no existía en ninguna parte del
panel**, sólo en el Perfil dentro de la app — y la app vive en las tablets de los
puestos, no en el teléfono del guardia, así que ofrecerse para un turno extra
obligaba a ir hasta un puesto.

Ahora está en dos sitios: el toggle en la ficha de Usuarios, junto al de
WhatsApp, y **una pantalla nueva en el grupo Operación**, «Disponibilidad para
extras», que es donde se pidió — junto a Turnos y Cobertura, porque cuando hay
que cubrir un puesto lo primero es saber a quién ofrecérselo. Lista cédula,
nombre, número de WhatsApp y los dos interruptores, con filtros. Acotada por
perfil y sin alta de personas: eso sigue en Usuarios.

Muestra el número de WhatsApp a propósito: sin número cargado el aviso no llega,
y eso explica por qué un guardia disponible nunca contesta una convocatoria.

### 2.5-bis (diagnóstico original)

El campo `usu_acepta_extras` existe, está en el `fillable` y la API lo lee y
escribe (`VacanteController.php:320`). Pero **no hay ningún control en el panel
web**: `UsersResource` sólo tiene el toggle de WhatsApp. Y la app recibe el valor
únicamente en la respuesta del login (`LoginController.php:118`), así que un
cambio hecho fuera no llega hasta que el guardia vuelve a entrar.

**Arreglo:** el toggle en el panel — y el cliente pide que viva en el **módulo de
Turnos**, no en Perfil — más refresco del valor al abrir la pantalla de Vacantes,
para que no dependa de volver a iniciar sesión.

---

### 2.6 ✅ La alarma de emergencia llegaba y no sonaba — RESUELTO (2026-09-19)

Tres fallos encadenados, y **cada uno bastaba por sí solo**. Por eso el problema
volvió cuatro veces: se arreglaba uno y quedaban los otros.

1. **`wire:poll.15s` sin `keep-alive`.** Livewire descarta el **95% de los
   sondeos** con la pestaña en segundo plano
   (`throttleWhile(theTabIsInTheBackground() && theDirectiveIsMissingKeepAlive)`,
   y luego `Math.random() < 0.95` en `start()`). A 15 s eso es una comprobación
   cada cinco minutos de media, y el panel de un operador está de fondo casi
   todo el tiempo.
2. **El botón de activar desaparecía sin que el audio funcionara.** Se abría el
   `AudioContext` en la carga de la página —sin gesto del usuario, o sea
   **suspendido**— pero se ponía `listo = true` igual, escondiendo el único
   botón que podía desbloquearlo. Sonaba la primera vez y **quedaba mudo para
   siempre desde la segunda carga**, sin síntoma.
3. **No se esperaba el `resume()`.** Con el contexto suspendido `currentTime` no
   avanza: los osciladores se programaban contra un reloj parado y esos
   instantes ya habían pasado al arrancar.

Y uno de diseño: **sin audio no se dibujaba ni el cartel**. Ahora el aviso visual
va siempre y el sonido es lo único condicional.

⚠️ **La lógica estaba copiada en dos sitios** (widget del tablero y componente
global), que es la razón de fondo de que reapareciera. Vive en
`resources/views/partials/alarma-de-emergencia-js.blade.php`, inyectada en
`HEAD_END`. Tres tests la fijan en `PanelConSesionTest`.

### 2.7 ✅ «Personas dentro» decía 9.769 de 9.776 accesos — RESUELTO (2026-09-19)

Solo 7 accesos cerrados en toda la historia. **Dos problemas distintos
mezclados:**

- **6.436 ya habían salido.** La migración `2026_08_21_100002` creó
  `ac_estado_acceso` con `default('en_curso')` y rellenó bien lo que había. Pero
  el **ETL importó los accesos tres semanas después** y la v1 no tiene esa
  columna: cada fila tomó el valor por defecto. El ETL nunca aplicó la regla que
  la migración ya tenía escrita. Corregido en `EtlV1::accesos()`.
- **3.333 abiertos de verdad**, desde abril de 2025; solo 5 de los últimos siete
  días.

`accesos:cerrar-abiertos` (simula por defecto, idempotente) los trata distinto: al
primer grupo solo le pone el estado —su salida fue real—; al segundo lo cierra
**sin fabricar la hora**, igualando la salida al ingreso y dejando la razón en
`ac_observaciones` y en el historial.

⚠️ **Dos trampas que encontró el test antes que producción**, las dos por el
accessor `getAcCreatedAtAttribute` de `Acceso`: devuelve **cadena vacía** en vez
de null (131 accesos sin fecha habrían cortado la corrida con «invalid input
syntax for type timestamp») y **convierte a `America/Guayaquil`** (habría corrido
la hora de las 3.202 filas restantes). Va con `getRawOriginal()`.

Resultado: **0 personas dentro**, 3.333 cierres trazados.

### 2.8 ✅ Configuración › Perfiles › Editar abría una página vacía — RESUELTO (2026-09-19)

`RolesResource::form()` era literalmente `return $schema->schema([ ]);`. Los 111
vínculos de `role_has_permissions` solo se podían tocar por SQL.

Debajo había un segundo fallo que el primero tapaba: **`roles` no declaraba
`$fillable`**, y con el `$guarded = ['*']` por defecto de Eloquent
`$record->update()` no habría escrito nada, sin error.

⚠️ **El nombre queda bloqueado en los cinco perfiles que `PerfilPanel` compara
por cadena literal.** Renombrar «Administrador» deja fuera del panel a todos sus
usuarios y el único síntoma sería «no puedo entrar». Va con `dehydrated(false)`,
no solo `disabled()`: un campo deshabilitado **sigue siendo escribible desde
fuera**, el mismo error que el `Hidden` de marcadores.

Los permisos de esta pantalla **no** gobiernan el panel: son los granulares de la
app móvil (`PermisosApiService`, `permission.api`).

### 2.9 Inventario: Productos y Listas no son redundantes (aclaración, 2026-09-19)

Surgió como duda y conviene dejarlo escrito. **Productos**
(`inv_producto_catalogo`) es el catálogo: qué cosas existen en un puesto, por
local. **Listas** (`inv_lista` + `inv_lista_item`) es la plantilla de conteo: qué
productos y **cuántos** debe haber al recibir el turno. Esa cantidad esperada es
lo que no cabe en el catálogo y obliga a la tabla intermedia. El movimiento
compara `md_cantidad_default` (de la lista) contra `md_cantidad_real` (contada).

Si vuelve a confundir, lo que ayuda es renombrar las etiquetas a «Catálogo de
productos» y «Listas de conteo»; no se hizo por no tocar sin pedido.

### 2.10 Qué es el módulo Gestiones (aclaración, 2026-09-19)

Es el **período de vinculación de una persona con la empresa**: `ug_ingreso`,
`ug_egreso`, `ug_finish`. No es decorativo: `LoginController:62` exige una
gestión abierta (`ug_finish = 0`) para entrar, y `getSanctumSession()` la
resuelve en cada petición de la app. **Dar de baja a un guardia = cerrar su
gestión**, no borrar el usuario: así se conserva su histórico.

---

## Bloque 3 — Mejoras funcionales pedidas

### ✅ Bloque 3 — HECHO (2026-09-15), salvo el APK

Todo lo de la app está en el código y **falta compilar el APK**, que se deja para
el final a pedido del cliente. Los cambios del panel ya están en producción.

- **3.1 Flujo tras el login.** Orden confirmado con el cliente el 2026-09-15:
  **inicio de sesión → elegir local → biometría → menú con Inventario primero**.
  La selección de perfil ya se saltaba sola cuando el guardia tiene uno solo.

  El local va **antes** del marcaje porque el servidor compara la ubicación
  contra el punto de marcación *del local*: sin local elegido no hay contra qué
  validar. Esa pantalla de biometría lleva un «Omitir», para que alguien que ya
  marcó —o que no puede marcar en ese momento— no quede encerrado sin llegar a
  su trabajo.
- **3.2 Botón de GPS en biometría.** Antes la ubicación se leía *dentro* del
  envío: si fallaba, se abortaba la marcación **con la foto ya tomada** y había
  que repetir todo. Ahora se pide al abrir la pantalla, se muestra con su
  precisión, y hay botón para reintentar sin perder nada. La precisión importa:
  una lectura con 500 m de error es la que hace que un marcaje salga «fuera del
  punto» sin que nadie se haya movido.
- **3.3 Menú lateral con iconos.** Hecho con `Modal` y `Animated` del propio
  React Native: `@react-navigation/drawer` arrastra dos módulos nativos, y acá
  `android/` está versionado. Los módulos salen de `utils/modulos`, compartida
  con el Home para que no puedan quedar desfasados.
- **3.4 Novedades con filtro de días.** Más un selector **Mías / Del puesto**: el
  endpoint devolvía sólo lo propio y de un día, así que al recibir el puesto no
  había forma de leer el turno anterior. Los dos parámetros son opcionales, para
  que el APK ya instalado siga funcionando. El listado ahora dice quién escribió
  cada novedad.
- **3.5 Código de acceso oculto**, en Perfil y también en el Home, donde también
  se mostraba. Visible a demanda para dictarlo a soporte.
- **3.6 y 3.7 «Personas dentro» (panel).** Pantalla nueva en Operación con quién
  sigue adentro, búsqueda por documento, nombre, apellido y placa, y **acción de
  registrar la salida** — que no existía en la web: `registrarSalida()` sólo la
  llamaba la API de la tablet, así que un visitante que se iba por otra puerta
  quedaba con el acceso abierto para siempre. Muestra cuánto lleva dentro cada
  uno, en rojo a partir de doce horas, porque eso casi siempre es una salida que
  nadie registró.
- **3.8** ya se había hecho en el Bloque 2.

20 tests nuevos entre los dos bloques del día. Suite: **493 en verde**.

### 3-bis (diagnóstico original)

- **3.1 Flujo posterior al login.** Hoy: login → selección de perfil →
  selección de local. Se pide llegar antes al local, y que de ahí se entre a
  inventario. `A CONFIRMAR`: si la selección de perfil debe saltarse cuando el
  guardia tiene un solo perfil, y si inventario reemplaza al Home o es sólo el
  atajo tras elegir local.
- **3.2 Botón de cargar coordenadas GPS en biometría.** Hoy
  (`BiometriaScreen.tsx:85-96`) si el GPS falla se aborta el marcaje y **se
  pierde la foto ya tomada**. Un botón de reintento explícito, con la lectura y
  su precisión a la vista antes de enviar.
- **3.3 Menú lateral desplegable** con todos los módulos y un icono por módulo.
- **3.4 Novedades con filtro de días.** El vigilante ya las ve, pero
  `NovedadListScreen.tsx:44` pide siempre `date:` de un solo día — el de hoy.
  Falta el rango.
- **3.5 Ocultar el código de acceso en Perfil** (`PerfilScreen.tsx:72`).

### Panel web

- **3.6 Apartado de «personas dentro»** en Accesos: los que tienen entrada
  registrada y todavía no marcaron salida.
- **3.7 Búsqueda por nombre, cédula o placa en el panel de salida**, para cerrar
  el acceso sin buscar a mano en el listado.
- **3.8 Mover «cubrir turnos extra» al módulo de Turnos** (ver 2.5).

---

## Bloque 4 — Deuda anterior, sigue abierta

- **4.1 Miniaturas de fotos.** La primera página de Accesos baja **21,5 MB**
  para dibujar cinco círculos de 35 px: `ImageColumn->width()` es sólo CSS y el
  navegador se trae el original. 32 fotos pasan de 3 MB (165 MB, el 20% del
  almacenamiento).
- **4.2 Instalar y probar el APK 1.0.2 en tablet** (`adb install -r`, sin
  desinstalar, misma firma). El archivo está verificado; nadie lo ha ejecutado.
  Hasta 1.0.2 **el botón de EMERGENCIA no existía**: hay que censar qué versión
  tiene cada tablet.
- **4.3 Worker de cola.** `QUEUE_CONNECTION=sync`; bloquea 2.1.
- **4.4 Healthchecks** de backend y nginx (hoy sólo los tiene la base).
- **4.5 Sembrar una fila por cada uno de los 27 recursos** en los tests del
  panel: con las tablas vacías, una acción de fila rota se dibuja perfecta.
- **4.6 `openapi.yaml`** sin contrastar contra `route:list`.
- **4.7 `v1_analisis`**, MariaDB fuera de todo compose y de todo respaldo.
- **4.8 Las 57 tablas** sin documentar una por una.
- **4.9 Puerto 3031** abierto a toda la LAN.
- **4.10 Decisión de negocio:** si bloquear el marcaje en locales sin
  marcadores. Hoy se acepta y se marca como no verificado.
- **4.11 18 nombres** que la migración omitió por tener el desglose corrupto.

---

## Orden sugerido

1. **0.1** (toma de cuentas) y **2.1** (el botón de pánico que no avisa). Uno
   es la puerta abierta; el otro es seguridad física de los guardias.
2. **0.2** respaldos — es lo único irrecuperable si algo se cae hoy.
3. **1.1** y **1.2**, en cuanto lleguen el diccionario de marcas y el criterio
   de cruce de personas. Desbloquea Turnos, Cuadrantes y Vacantes.
4. **0.4**, **2.2**, **2.3**, **2.4**, **2.5** — bugs acotados, cada uno con su
   causa ya localizada.
5. **0.3** HTTPS, coordinando la ventana con el cliente por el NAT.
6. Bloque 3, agrupado en una sola versión del APK para no repetir la
   instalación en tablet.
7. Bloque 4.

## Lo que hace falta del cliente

1. **Horas exactas** de `D` y `N`, y qué significan `A`, `T`, `C`, `B`, `Z`,
   `D1`, `D2`, `V2`, `P` (351 turnos dependen de esto).
2. **Cómo cruzar los guardias del Excel con la base**: ¿hay un listado con
   cédulas, o se resuelve por nombre con revisión manual?
3. **3.1**: si la selección de perfil se salta con un solo perfil, y qué papel
   cumple inventario en el arranque.
4. Dominio para el certificado (0.3).

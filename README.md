# Total Secure App

Sistema de gestión de guardias de seguridad: control de asistencia, rondas,
accesos, novedades, alertas, inventario, programación de turnos y cobertura de
puestos vacíos. Opera en Ecuador y Colombia, con clientes en aeropuertos y
bodegas.

Dos piezas en un mismo repositorio:

| Carpeta | Qué es |
|---|---|
| `totalsecureapp/backend` | API + panel de administración (Laravel 8.75, PHP 8.3, PostgreSQL 16) |
| `totalsecureapp/src`, `totalsecureapp/android` | App para las tablets de los puestos (Expo SDK 57 / React Native) |

---

## Puesta en marcha desde cero

### Requisitos

- PHP **8.3** con las extensiones habituales de Laravel (`pdo_pgsql`, `mbstring`, `gd`, `zip`)
- PostgreSQL **16**
- Composer 2
- Docker y Docker Compose (opcional, es el camino recomendado)

### 1. Clonar

```bash
git clone git@github.com:megaman0012/appguardia.git
cd appguardia/totalsecureapp/backend
```

> Si el servidor tiene **varias identidades de GitHub**, `git@github.com:` usa la
> clave por defecto (`~/.ssh/id_ed25519`), que puede pertenecer a otra cuenta u
> organizacion: el clon falla o queda con la identidad equivocada. En ese caso
> clonar por el alias de ssh_config de esta cuenta, p. ej.
> `git@github-megaman0012:megaman0012/appguardia.git`, y ponerle identidad
> **local al repo** (`git config user.name/user.email`) para no heredar la global.

### 2. Dependencias

```bash
composer install --no-dev --optimize-autoloader
```

> `vendor/` no se versiona. Este paso es obligatorio y necesita salida a internet;
> si el servidor no la tiene, subir la carpeta `vendor/` por separado.

### 3. Configuración

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con los datos reales. Lo mínimo que hay que revisar:

| Variable | Nota |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production` y `false` en el servidor real |
| `APP_URL` | El dominio, con `https://` |
| `DB_*` | Credenciales de PostgreSQL |
| `FILAMENT_LIVEWIRE` | **Dejar vacío.** Con un valor puesto el panel se renderiza pero no responde a nada |
| `MAIL_*` | Para el flujo de recuperación de contraseña |
| `WHATSAPP_*` | Solo si se usa el canal de WhatsApp (ver `WHATSAPP-EVOLUTION.md`) |

### 4. Base de datos

```bash
php artisan migrate --force
php artisan db:seed --force      # roles, permisos y catálogos base
php artisan storage:link
```

Para ver el sistema con datos de ejemplo (**no correr en producción real**):

```bash
php artisan db:seed --class=CuadranteEjemploSeeder
```

### 5. Tareas programadas

Con Docker, usar el script incluido (resuelve el `docker compose exec`, el
usuario y las rutas absolutas que cron necesita):

```cron
* * * * * /ruta/al/backend/scripts/schedule-run.sh >> /ruta/al/backend/storage/logs/schedule-cron.log 2>&1
```

Sin Docker:

```cron
* * * * * cd /ruta/al/backend && php artisan schedule:run >> /dev/null 2>&1
```

> **Sin esto no hay detección de faltas.** `turnos:revisar-cobertura` corre cada
> 5 minutos y es lo único que descubre un puesto vacío a tiempo para cubrirlo.
> El otro comando, `turnos:cerrar-dia`, corre a las 23:55: enterarse a esa hora
> de que el puesto de las 06:00 quedó vacío no sirve de nada.
>
> Va en el crontab del **usuario dueño del repo**, no de root: root no está en el
> grupo `docker` ni es dueño de `storage/`. Y **no redirigir a `/dev/null`** la
> primera vez: el log es la única forma de saber si el cron corre.
>
> `php artisan schedule:list` **no sirve para verificarlo** — revienta por un bug
> de Laravel 8.75. Lo programado está en `app/Console/Kernel.php`.

### 6. Permisos de escritura

```bash
chmod -R ug+w storage bootstrap/cache
```

### Con Docker

```bash
cd totalsecureapp/backend
docker compose up -d
docker compose exec backend php artisan migrate --force
```

Levanta nginx + PHP-FPM + PostgreSQL. El backend queda en el puerto 3031 y la
base en `127.0.0.1:5434`.

---

## Primer acceso

El panel se entra por **`/acceso/login`** (cédula y contraseña), se elige el
perfil, y de ahí redirige a `/admin`. **La raíz (`/`) y `/admin/login` redirigen
a esa misma pantalla**: el sistema no tiene portada pública. Antes la raíz
respondía un 404 de Laravel, que parecía un despliegue roto cuando solo faltaba
la ruta.

El usuario que crea `db:seed` es **de demostración** y su contraseña es
**`123456`** (la escribe `DatabaseSeeder`). Sirve para comprobar que el clon
quedó bien y **no debe sobrevivir a la salida a producción**: ver la sección
final.

Para crear un usuario administrador propio:

```bash
php artisan usuario:crear --cedula=... --nombres=... --apellidos=... \
    --email=... --rol=Administrador
```

> Sin `--password` la pide por teclado sin mostrarla, así que **necesita una
> terminal real**: desde un script o una sesión de agente hay que pasarla en el
> comando.

## Los seis perfiles

| Perfil | Panel | Qué hace | Qué ve |
|---|---|---|---|
| `Administrador` | ✅ | Sistemas: todo, incluida la configuración | Todo |
| `Consola` | ✅ | Central 24/7: consigue el reemplazo cuando falta un guardia | Todo |
| `Lider Operativo` | ✅ | Da de alta guardias, arma cuadrantes, asigna coberturas | Los locales de su país |
| `Supervisor` | ✅ | Observa guardias y turnos, atiende alertas | Sus locales |
| `Vigilante` | ❌ | App de la tablet del puesto | Sus locales |
| `Cliente` | ❌ | Portal de solo lectura (API) | Sus locales |

---

## Documentación

| Archivo | Para qué |
|---|---|
| `totalsecureapp/AGENTS.md` | **Documentación técnica principal.** Decisiones de diseño, trampas conocidas y por qué las cosas están como están |
| `CHECKLIST-DESPLIEGUE-V2.md` | Checklist de despliegue: backup obligatorio, orden de migraciones, rollback por fase |
| `DESPLIEGUE-DOMINIO.md` | Dominio, DNS y certificado |
| `DESPLIEGUE-IP-Y-PUERTO-80.md` | **Publicar por IP en el puerto 80, sin dominio.** Reparto del 80 entre proyectos, qué reenviar en el router, el APK con IP, y por qué el 3031 sigue abierto |
| `WHATSAPP-EVOLUTION.md` | Canal de WhatsApp con el gateway Evolution (se instala aparte) |
| `API-OFFLINE-SYNC.md` | Contrato de los endpoints que funcionan sin señal |
| `openapi.yaml` | API del portal de clientes |

## Pruebas

```bash
docker compose exec -u 1000 backend php artisan test
```

285 pruebas, ~34 s. Si fallan **todas** después de clonar, no es el código:

- **Base de pruebas.** `phpunit.xml` corre contra `coredt360_testing`, una base
  **aparte** de la de trabajo. La crea `docker/postgres/init/01-base-de-pruebas.sql`
  al inicializar el volumen, así que un clon nuevo ya la trae. En un volumen que
  **ya existía** hay que crearla a mano, una sola vez:

  ```bash
  docker compose exec db psql -U totalsecure -d coredt360 \
      -c 'CREATE DATABASE coredt360_testing;'
  ```

- **`memory_limit`.** Con los 128M por defecto de PHP la suite muere a mitad de
  camino con `Allowed memory size exhausted`, y las fallas que deja parecen
  errores de lógica. El `Dockerfile` lo sube a 512M
  (`conf.d/zz-memory.ini`); fuera de Docker hay que pasarlo a mano:
  `php -d memory_limit=512M artisan test`.

- **Conexión.** Si el mensaje es de conexión, revisar `DB_*` en el `.env`.

---

## Antes de abrir a producción

- [ ] **Sacar de circulación el usuario de demostración.** Son **dos** cosas y
      hacer una sola no alcanza:
      1. `DatabaseSeeder` escribe la contraseña **`123456`** en claro. Cambiarla
         en la base no basta: el próximo `db:seed` la vuelve a poner. Hay que
         tocar el seeder, o no correrlo en el servidor real.
      2. La credencial que se usó en el piloto quedó en texto plano en
         `HISTORIAL_DE_CHAT.md`, que **está versionado y ya viajó a GitHub**:
         tratarla como comprometida donde sea que se haya reutilizado.
      Lo sano en producción es crear el administrador con `usuario:crear` y
      desactivar al usuario demo.
- [ ] `APP_DEBUG=false` y `APP_ENV=production`
- [ ] Cargar los **clientes** y asociarlos a cada local
- [ ] Cargar el **WhatsApp** de los guardias y su autorización, si se usa ese canal
- [ ] Cron activo (paso 5)
- [ ] Backup automático de PostgreSQL

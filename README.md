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

```cron
* * * * * cd /ruta/al/backend && php artisan schedule:run >> /dev/null 2>&1
```

> **Sin esto no hay detección de faltas.** Nadie se entera de que un puesto quedó
> vacío hasta el cierre del día, cuando ya no se puede cubrir.

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
perfil, y de ahí redirige a `/admin`. El login propio de Filament está
deliberadamente desviado a esa misma pantalla.

Para crear el primer usuario administrador:

```bash
php artisan usuario:crear
```

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
| `WHATSAPP-EVOLUTION.md` | Canal de WhatsApp con el gateway Evolution (se instala aparte) |
| `API-OFFLINE-SYNC.md` | Contrato de los endpoints que funcionan sin señal |
| `openapi.yaml` | API del portal de clientes |

## Pruebas

```bash
php artisan test
```

285 pruebas. Si alguna falla después de clonar, la causa más probable es la
conexión a la base de datos del `.env`.

---

## Antes de abrir a producción

- [ ] **Rotar la contraseña de demostración.** `HISTORIAL_DE_CHAT.md` contuvo una
      credencial en texto plano y sigue en el historial de git: hay que cambiarla
      en la base y tratarla como comprometida.
- [ ] `APP_DEBUG=false` y `APP_ENV=production`
- [ ] Cargar los **clientes** y asociarlos a cada local
- [ ] Cargar el **WhatsApp** de los guardias y su autorización, si se usa ese canal
- [ ] Cron activo (paso 5)
- [ ] Backup automático de PostgreSQL

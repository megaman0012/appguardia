# Avisos por WhatsApp con Evolution API

Cómo conectar Total Secure App a un gateway de WhatsApp **open source**, instalado
aparte del proyecto.

> **Esto no es WhatsApp oficial.** Evolution se enlaza a una cuenta común como
> "dispositivo enlazado", igual que WhatsApp Web. Va contra los términos de uso de
> WhatsApp y **el número puede ser bloqueado sin aviso, a veces de forma
> definitiva**. Está documentado acá porque es una decisión tomada a conciencia,
> con las mitigaciones del final aplicadas.

## Qué hace y qué no

El gateway corre en su propio contenedor y mantiene la sesión de WhatsApp. Total
Secure App solo le habla por HTTP. Si el gateway se apaga, se rompe o el número
queda bloqueado, **no se rompe nada del sistema**: los guardias siguen viendo los
turnos por cubrir en la pantalla «Turnos disponibles» de la app. El aviso acelera,
no habilita.

## 1. Levantar Evolution

Punto de partida para `docker-compose.yml` (verificá los nombres de variables
contra la documentación de **tu** versión: cambian entre v1 y v2):

```yaml
services:
  evolution:
    image: atendai/evolution-api:v2.1.1
    container_name: evolution
    restart: unless-stopped
    ports:
      - "127.0.0.1:8080:8080"     # solo local: no exponer a internet
    environment:
      AUTHENTICATION_API_KEY: "una-clave-larga-y-aleatoria"
      DEL_INSTANCE: "false"
      QRCODE_LIMIT: "10"
    volumes:
      - evolution_instances:/evolution/instances

volumes:
  evolution_instances:
```

**Publicá el puerto solo en `127.0.0.1`.** Con la API key, cualquiera que llegue a
ese puerto puede mandar mensajes desde el número de la empresa.

## 2. Crear la instancia y enlazar el teléfono

```bash
# Crear la instancia (el nombre es el que después va en el .env)
curl -X POST http://localhost:8080/instance/create \
  -H "apikey: una-clave-larga-y-aleatoria" \
  -H "Content-Type: application/json" \
  -d '{"instanceName":"totalsecure","qrcode":true,"integration":"WHATSAPP-BAILEYS"}'

# Obtener el QR para escanear desde el teléfono
curl http://localhost:8080/instance/connect/totalsecure \
  -H "apikey: una-clave-larga-y-aleatoria"
```

En el teléfono: **WhatsApp → Dispositivos vinculados → Vincular dispositivo**, y
escaneás el QR que devuelve ese endpoint.

## 3. Configurar Total Secure App

En el `.env` del backend:

```
WHATSAPP_URL=http://localhost:8080
WHATSAPP_INSTANCIA=totalsecure
WHATSAPP_API_KEY=una-clave-larga-y-aleatoria
WHATSAPP_CODIGO_PAIS=593
```

Después, `php artisan config:clear`.

**Mientras esas variables estén vacías el canal no intenta nada** y cada aviso
queda registrado como «no se intentó». No hace falta comentar ni sacar nada del
código para operar sin WhatsApp.

## 4. Configurar el webhook (respuestas de los guardias)

Sin esto los avisos salen pero **nadie puede contestarlos**, que es la mitad que
importa: el guardia que puede cubrir está franco y no tiene la app.

En el `.env` del backend:

```
WHATSAPP_WEBHOOK_TOKEN=una-cadena-larga-y-aleatoria-distinta-de-la-api-key
```

Y en Evolution, apuntá el webhook a esa URL:

```bash
curl -X POST http://localhost:8080/webhook/set/totalsecure \
  -H "apikey: una-clave-larga-y-aleatoria" \
  -H "Content-Type: application/json" \
  -d '{
        "webhook": {
          "enabled": true,
          "url": "http://TU-BACKEND/api/whatsapp/webhook/una-cadena-larga-y-aleatoria-distinta-de-la-api-key",
          "events": ["MESSAGES_UPSERT"]
        }
      }'
```

> El token va **en la URL**, así que esa URL es una credencial: quien la tenga
> puede simular que un guardia aceptó un turno. Si el backend y Evolution están
> en el mismo servidor, que la URL sea interna.

Con el token vacío, la ruta responde 404 y el sistema funciona igual: los avisos
salen, pero las respuestas hay que cargarlas a mano desde el panel.

### Qué entiende el sistema

La convocatoria que recibe el guardia dice:

```
Hay un turno por cubrir:
Terminal de carga
Garita principal, 25/08/2026 de 06:00 a 14:00

Para tomarlo responda: SI 4821
Si no puede, responda: NO 4821
```

- Con **una sola** convocatoria abierta, un «si» alcanza.
- Con **dos o más**, sin el número pide aclaración en vez de adivinar: mandar a
  alguien al puesto equivocado es peor que preguntar.
- Un «no» no lo postula, pero queda registrado para que la central deje de
  esperar esa respuesta.
- Si ya no puede cubrirlo (le programaron otro turno mientras tanto), se le
  responde el motivo concreto.

Cuando un guardia acepta, la **Consola y el Líder Operativo** reciben el aviso en
el momento.

**Las dos respuestas quedan registradas**, el «sí» y el «no». En *Avisos
enviados* se distinguen de los mensajes salientes con la etiqueta **Respuesta**, y
hay un filtro «Solo respuestas de guardias». El «no» importa tanto como el «sí»:
la central deja de esperar esa respuesta y sabe a quién no volver a llamar por ese
turno.

## 5. Verificar

En el panel, **Operación → Avisos enviados**. Arriba hay una tarjeta con el estado
del canal:

| Lo que dice | Qué significa |
|---|---|
| **Conectado** + número | Todo bien; ese es el número desde el que salen los avisos |
| **Esperando el código QR** | La instancia existe pero nadie escaneó |
| **Desconectado** | La sesión se cayó: hay que volver a escanear el QR |
| **El gateway no responde** | El contenedor está apagado o la URL es incorrecta |
| **Sin configurar** | Faltan las variables del `.env` |

Con perfil Administrador aparece además **Probar WhatsApp**, que manda un mensaje
a un número que escribas. Conviene usarlo después de cada reconexión.

## 6. Cargar los números

En **Usuarios**, cada guardia tiene dos campos nuevos:

- **WhatsApp** — con código de país (`593987654321`). También acepta el formato
  local (`0987654321`) y lo completa. Un número mal formado **no da error**:
  WhatsApp lo acepta y el mensaje se pierde, así que el sistema valida antes de
  intentar y lo registra como «no se intentó» si no sirve.
- **Autoriza avisos por WhatsApp** — es aparte de «quiero turnos extra».
  Aceptar trabajar de más no es aceptar que le escriban al teléfono personal.
  Sin esta casilla, no se le escribe.

## 7. Operación

- **La sesión se cae sola cada tanto.** Es normal en clientes no oficiales. La
  tarjeta de estado lo muestra, pero conviene mirarla o montar un chequeo del
  endpoint `/instance/connectionState/{instancia}`.
- **Si un guardia no se enteró**, la pantalla de avisos lo dice: filtro «Solo los
  que no llegaron», y la columna Motivo distingue entre «no tiene número cargado»
  (se arregla cargando un dato) y «el gateway no respondió» (se arregla levantando
  un servicio).

## 8. Bajar el riesgo de bloqueo

- **Número dedicado, nunca el operativo de la empresa.** Si el número baneado es
  por el que escriben los clientes, el daño supera largamente el beneficio.
- **Que los guardias guarden el número en su agenda.** Escribirle a alguien que
  no te tiene agendado y nunca te escribió es el patrón que más denuncias genera.
- **Volumen bajo y mensajes distintos.** El caso de uso ayuda: son pocos avisos y
  cada uno dice un puesto, un horario y un local diferentes. No usar este canal
  para difusión masiva idéntica.
- **Calentar el número**: usarlo unos días con tráfico normal antes de automatizar.
- **Plan B listo**: si el número cae, se vacían las tres variables del `.env` y el
  sistema sigue funcionando sin WhatsApp mientras se consigue otro.

## Si tu versión de Evolution es v1

El envío de texto cambió de forma entre versiones. El código manda el cuerpo de
v2:

```json
{ "number": "593987654321", "text": "..." }
```

En v1 era `{"number": "...", "textMessage": {"text": "..."}}`. Si usás v1, se
ajusta en un solo lugar: `enviarTexto()` en
`backend/app/Services/Avisos/EvolutionApi.php`.

// Constantes de la aplicacion
import Constants from 'expo-constants';

// Puerto del backend en desarrollo (nginx del docker-compose local).
// En produccion con HTTPS no se usa: el 443 es implicito.
const API_PORT_DEFECTO = 3031;

interface ExtraConfig {
  /** Override completo, p. ej. "https://api.totalsecureapp.com". Gana sobre todo lo demas. */
  apiUrl?: string;
  /** Host o dominio del backend. */
  apiHost?: string;
  /** 'http' (defecto) o 'https'. */
  apiScheme?: string;
  /** Puerto explicito. Con https se omite salvo que se indique. */
  apiPort?: number | string | null;
}

function getExtra(): ExtraConfig {
  try {
    return (Constants.expoConfig?.extra ?? {}) as ExtraConfig;
  } catch (e) {
    return {};
  }
}

function getHost(): string {
  try {
    const extraHost = getExtra().apiHost;
    if (extraHost) {
      return extraHost;
    }
    const hostUri = Constants.expoConfig?.hostUri || Constants.expoGoConfig?.debuggerHost;
    if (hostUri) {
      return hostUri.split(':')[0];
    }
  } catch (e) {
    // ignorar y usar localhost
  }
  return 'localhost';
}

/**
 * URL base de la API.
 *
 * Sin configuracion extra se comporta igual que siempre:
 * http://<apiHost o hostUri>:3031/api
 *
 * Para produccion con dominio basta editar `expo.extra` en app.json, sin tocar
 * este archivo:
 *   { "apiUrl": "https://api.totalsecureapp.com" }
 * o bien:
 *   { "apiHost": "api.totalsecureapp.com", "apiScheme": "https" }
 *
 * Con apiScheme 'https' el puerto se omite (443 implicito) salvo que se pase
 * apiPort explicitamente.
 */
function construirApiUrl(): string {
  const extra = getExtra();

  if (extra.apiUrl) {
    // Se normaliza para tolerar que venga con o sin '/api' y con o sin barra final.
    const base = String(extra.apiUrl).replace(/\/+$/, '');
    return base.endsWith('/api') ? base : `${base}/api`;
  }

  const scheme = extra.apiScheme === 'https' ? 'https' : 'http';

  let puerto = '';
  if (extra.apiPort !== undefined && extra.apiPort !== null && extra.apiPort !== '') {
    puerto = `:${extra.apiPort}`;
  } else if (scheme === 'http') {
    puerto = `:${API_PORT_DEFECTO}`;
  }

  return `${scheme}://${getHost()}${puerto}/api`;
}

export const API_URL = construirApiUrl();

export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/login',
    SOLICITUD_PASS: '/solicitud_paswchg',
    PROCESAR_PASS: '/procesar_paswchg',
  },
  PERFIL: {
    SELECCIONAR: '/seleccionar_perfil',
    PROCESAR: '/procesar_perfil',
  },
  INSTITUCIONES: '/instituciones',
  BIOMETRIA: '/biometria',
  TURNOS: {
    DEL_DIA: '/turnos-del-dia',
    VINCULAR_MARCAJE: '/turnos-vincular-marcaje',
    CUMPLIMIENTO: '/turnos-cumplimiento',
  },
  VACANTES: {
    DISPONIBLES: '/vacantes-disponibles',
    POSTULAR: '/vacantes-postular',
    RETIRAR: '/vacantes-retirar',
    MIS_POSTULACIONES: '/vacantes-mis-postulaciones',
    ACEPTAR_EXTRAS: '/perfil-extras',
  },
  RONDAS: {
    LIST: '/rondas',
    GESTION: '/rondas_gestion',
    DETALLE: '/rondas_detalle',
    DETALLE_GESTION: '/rondas_detalle_gestion',
    DETALLE_QRCODE: '/rondas_detalle_qrcode',
  },
  ACCESO: {
    REGISTRAR: '/acceso',
    LIST_BY_INST: '/accesosbyinst',
    SALIDA: '/accesout',
    PREREGISTRO_CREATE: '/acceso/preregistro',
    PREREGISTRO_LIST: '/acceso/preregistros',
    PREREGISTRO_CANCEL: '/acceso/cancelar-preregistro',
  },
  NOVEDAD: {
    CREATE: '/novedad_create',
    LIST_BY_DATE: '/novedad_listbydate',
  },
  INVENTARIO: {
    LIST_BY_INST: '/inventario/listbyinst',
    LIST_SAVE: '/inventario/listsave',
    FINISH_SAVE: '/inventario/finishsave',
  },
  NOTIFICACION: {
    TOKEN_SAVE: '/token/save',
    TOKEN_REMOVE: '/token/remove',
    ALERT_TODAY: '/alert/today',
  },
};

// Ambiente de notificaciones push. Debe coincidir con PUSH_ENV del backend (por defecto 'prod').
export const PUSH_ENV = 'prod';

export const APP_NAME = 'Total Secure App';
export const STORAGE_KEYS = {
  TOKEN: 'token',
  USER: 'user',
};

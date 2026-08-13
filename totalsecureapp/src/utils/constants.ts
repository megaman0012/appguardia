// Constantes de la aplicacion
import Constants from 'expo-constants';

const API_PORT = 3031;

function getHost(): string {
  try {
    const extraHost = Constants.expoConfig?.extra?.apiHost as string | undefined;
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

export const API_URL = `http://${getHost()}:${API_PORT}/api`;

export const API_ENDPOINTS = {
  AUTH: {
    LOGIN: '/login',
    SOLICITUD_PASS: '/solicitud_paswchg',
    PROCESAR_PASS: '/procesar_paswchg',
  },
  INSTITUCIONES: '/instituciones',
  BIOMETRIA: '/biometria',
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

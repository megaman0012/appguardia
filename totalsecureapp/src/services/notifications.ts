import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from './api';
import { API_ENDPOINTS, PUSH_ENV } from '../utils/constants';

const PUSH_TOKEN_KEY = 'pushToken';
const PUSH_TOKEN_INS_KEY = 'pushTokenIns';

export const NOTIFICATION_CHANNEL = 'default';

export async function setupNotificationChannel(): Promise<void> {
  if (Platform.OS !== 'android') return;
  await Notifications.setNotificationChannelAsync(NOTIFICATION_CHANNEL, {
    name: 'Notificaciones',
    importance: Notifications.AndroidImportance.HIGH,
    vibrationPattern: [0, 250, 250, 250],
    lightColor: '#FF231F7C',
  });
}

async function ensurePermission(): Promise<boolean> {
  const current = await Notifications.getPermissionsAsync();
  if (current.granted) return true;
  if (current.ios?.status === Notifications.IosAuthorizationStatus.PROVISIONAL) return true;
  const requested = await Notifications.requestPermissionsAsync();
  return requested.granted;
}

export async function getExpoPushToken(): Promise<string | null> {
  if (!Device.isDevice) return null;
  const granted = await ensurePermission();
  if (!granted) return null;
  try {
    const tokenData = await Notifications.getExpoPushTokenAsync({
      projectId: undefined,
    });
    return tokenData.data;
  } catch (error) {
    console.error('Error obteniendo push token:', error);
    return null;
  }
}

export async function registerPushToken(insCode: number | string): Promise<string | null> {
  const token = await getExpoPushToken();
  if (!token) return null;

  try {
    const stored = await AsyncStorage.getItem(PUSH_TOKEN_KEY);
    const storedIns = await AsyncStorage.getItem(PUSH_TOKEN_INS_KEY);
    if (stored === token && storedIns === String(insCode)) {
      return token;
    }

    await api.post(API_ENDPOINTS.NOTIFICACION.TOKEN_SAVE, {
      tkn: token,
      ins: insCode,
      env: PUSH_ENV,
      ptf: Platform.OS,
      dvn: Device.modelName || Device.osName || Platform.OS,
    });

    await AsyncStorage.multiSet([
      [PUSH_TOKEN_KEY, token],
      [PUSH_TOKEN_INS_KEY, String(insCode)],
    ]);
    return token;
  } catch (error) {
    console.error('Error registrando push token en el backend:', error);
    return token;
  }
}

export async function removeRegisteredPushToken(): Promise<void> {
  try {
    const stored = await AsyncStorage.getItem(PUSH_TOKEN_KEY);
    if (!stored) return;
    await api.post(API_ENDPOINTS.NOTIFICACION.TOKEN_REMOVE, { token: stored });
    await AsyncStorage.multiRemove([PUSH_TOKEN_KEY, PUSH_TOKEN_INS_KEY]);
  } catch (error) {
    console.error('Error eliminando push token del backend:', error);
  }
}

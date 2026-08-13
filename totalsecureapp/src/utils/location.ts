import * as Location from 'expo-location';

export interface Coords {
  lat: string;
  lng: string;
}

export const getCurrentLocation = async (): Promise<Coords> => {
  const { status } = await Location.requestForegroundPermissionsAsync();
  if (status !== 'granted') {
    throw new Error('Permiso de ubicación denegado');
  }
  const loc = await Location.getCurrentPositionAsync({
    accuracy: Location.Accuracy.Balanced,
  });
  return {
    lat: String(loc.coords.latitude),
    lng: String(loc.coords.longitude),
  };
};

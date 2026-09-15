import * as Location from 'expo-location';

export interface Coords {
  lat: string;
  lng: string;
  /** Radio de error en metros que informa el dispositivo, si lo informa. */
  precision?: number;
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
    // Se expone para poder mostrarla antes de marcar: una lectura con 500 m de
    // error es la que hace que el marcaje salga «fuera del punto» sin que el
    // guardia se haya movido de su puesto.
    precision: loc.coords.accuracy ?? undefined,
  };
};

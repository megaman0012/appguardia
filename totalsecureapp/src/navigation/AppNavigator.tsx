import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useAuth } from '../context/AuthContext';

export type RootStackParamList = {
  Login: undefined;
  PasswordResetRequest: undefined;
  PasswordReset: { user_id: number | string };
  SeleccionInstitucion: undefined;
  Home: undefined;
  Perfil: undefined;
  RondaList: undefined;
  RondaDetalle: { rc_id: number | string; estado: string };
  ScannerQR: { rc_id: number | string };
  AccesoList: undefined;
  AccesoForm: undefined;
  NovedadList: undefined;
  NovedadCreate: undefined;
  Alertas: undefined;
  Inventario: undefined;
  InventarioDetalle: { lp_id: number | string; lp_nombre: string };
  Biometria: undefined;
};

export type RootStackScreenProps<T extends keyof RootStackParamList> = NativeStackScreenProps<
  RootStackParamList,
  T
>;

import { LoginScreen } from '../screens/LoginScreen';
import { PasswordResetRequestScreen } from '../screens/PasswordResetRequestScreen';
import { PasswordResetScreen } from '../screens/PasswordResetScreen';
import { SeleccionInstitucionScreen } from '../screens/SeleccionInstitucionScreen';
import { HomeScreen } from '../screens/HomeScreen';
import { PerfilScreen } from '../screens/PerfilScreen';
import { RondaListScreen } from '../screens/RondaListScreen';
import { RondaDetalleScreen } from '../screens/RondaDetalleScreen';
import { ScannerQRScreen } from '../screens/ScannerQRScreen';
import { AccesoListScreen } from '../screens/AccesoListScreen';
import { AccesoFormScreen } from '../screens/AccesoFormScreen';
import { NovedadListScreen } from '../screens/NovedadListScreen';
import { NovedadCreateScreen } from '../screens/NovedadCreateScreen';
import { AlertasScreen } from '../screens/AlertasScreen';
import { InventarioScreen } from '../screens/InventarioScreen';
import { InventarioDetalleScreen } from '../screens/InventarioDetalleScreen';
import { BiometriaScreen } from '../screens/BiometriaScreen';

const Stack = createNativeStackNavigator<RootStackParamList>();

export const AppNavigator = () => {
  const { token, institucion, isLoading } = useAuth();

  if (isLoading) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
        <ActivityIndicator size="large" color="#007AFF" />
      </View>
    );
  }

  const initialRoute: keyof RootStackParamList = !token
    ? 'Login'
    : institucion
      ? 'Home'
      : 'SeleccionInstitucion';

  return (
    <Stack.Navigator
      initialRouteName={initialRoute}
      screenOptions={{
        headerShown: false,
      }}
    >
      <Stack.Screen name="Login" component={LoginScreen} />
      <Stack.Screen name="PasswordResetRequest" component={PasswordResetRequestScreen} />
      <Stack.Screen name="PasswordReset" component={PasswordResetScreen} />
      <Stack.Screen name="SeleccionInstitucion" component={SeleccionInstitucionScreen} />
      <Stack.Screen name="Home" component={HomeScreen} />
      <Stack.Screen name="Perfil" component={PerfilScreen} />
      <Stack.Screen name="RondaList" component={RondaListScreen} />
      <Stack.Screen name="RondaDetalle" component={RondaDetalleScreen} />
      <Stack.Screen name="ScannerQR" component={ScannerQRScreen} />
      <Stack.Screen name="AccesoList" component={AccesoListScreen} />
      <Stack.Screen name="AccesoForm" component={AccesoFormScreen} />
      <Stack.Screen name="NovedadList" component={NovedadListScreen} />
      <Stack.Screen name="NovedadCreate" component={NovedadCreateScreen} />
      <Stack.Screen name="Alertas" component={AlertasScreen} />
      <Stack.Screen name="Inventario" component={InventarioScreen} />
      <Stack.Screen name="InventarioDetalle" component={InventarioDetalleScreen} />
      <Stack.Screen name="Biometria" component={BiometriaScreen} />
    </Stack.Navigator>
  );
};

import React, { useState } from 'react';
import { View, Text, TouchableOpacity, Alert, StyleSheet, ActivityIndicator } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';

export const ScannerQRScreen = ({ navigation, route }: any) => {
  const { rc_id } = route.params;
  const { institucion } = useAuth();
  const [permission, requestPermission] = useCameraPermissions();
  const [procesando, setProcesando] = useState(false);
  const [scanned, setScanned] = useState(false);

  const insCode = institucion?.ins_code;

  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#fff" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.centerText}>Se requiere permiso de cámara para escanear</Text>
        <TouchableOpacity style={styles.button} onPress={requestPermission}>
          <Text style={styles.buttonText}>Permitir cámara</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.cancel} onPress={() => navigation.goBack()}>
          <Text style={styles.cancelText}>Cancelar</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const registrarQR = async (data: string) => {
    if (procesando || scanned || insCode === undefined) return;
    setScanned(true);
    setProcesando(true);
    try {
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e: any) {
        Alert.alert('Error', e.message || 'No se pudo obtener la ubicación');
        setScanned(false);
        return;
      }

      const response = await api.post(API_ENDPOINTS.RONDAS.DETALLE_QRCODE, {
        ins_code: insCode,
        rc_id,
        rc_qr: data,
        rd_lat: coords.lat,
        rd_lng: coords.lng,
      });
      const resp = response.data;
      if (resp && resp.result === 'success') {
        Alert.alert('Ronda', resp.message || 'Marcador registrado', [
          {
            text: 'OK',
            onPress: () => navigation.goBack(),
          },
        ]);
      } else if (resp && resp.errors) {
        Alert.alert('Error', String(Object.values(resp.errors)[0]), [
          { text: 'OK', onPress: () => setScanned(false) },
        ]);
      } else {
        Alert.alert('Error', resp?.message || 'No se pudo registrar el marcador', [
          { text: 'OK', onPress: () => setScanned(false) },
        ]);
      }
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Error al registrar el marcador';
      Alert.alert('Error', msg, [{ text: 'OK', onPress: () => setScanned(false) }]);
    } finally {
      setProcesando(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Escanear QR</Text>
      </View>

      <View style={styles.cameraWrap}>
        <CameraView
          style={styles.camera}
          facing="back"
          onBarcodeScanned={scanned ? undefined : ({ data }) => registrarQR(data)}
          barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        >
          <View style={styles.overlay}>
            <View style={styles.target} />
            {procesando && (
              <ActivityIndicator size="large" color="#fff" style={styles.spinner} />
            )}
          </View>
        </CameraView>
      </View>

      <Text style={styles.hint}>
        Apunte la cámara hacia el código QR del marcador de la ronda
      </Text>
      {scanned && !procesando && (
        <TouchableOpacity style={styles.rescan} onPress={() => setScanned(false)}>
          <Text style={styles.rescanText}>Volver a escanear</Text>
        </TouchableOpacity>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    backgroundColor: '#000',
  },
  centerText: {
    color: '#fff',
    fontSize: 16,
    marginBottom: 20,
    textAlign: 'center',
  },
  button: {
    backgroundColor: '#007AFF',
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  cancel: {
    marginTop: 12,
  },
  cancelText: {
    color: '#ccc',
    fontSize: 15,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    paddingTop: 50,
  },
  backBtn: {
    marginRight: 12,
  },
  backText: {
    fontSize: 16,
    color: '#007AFF',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#fff',
  },
  cameraWrap: {
    flex: 1,
    margin: 20,
    borderRadius: 12,
    overflow: 'hidden',
  },
  camera: {
    flex: 1,
  },
  overlay: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  target: {
    width: 220,
    height: 220,
    borderWidth: 3,
    borderColor: '#fff',
    borderRadius: 12,
  },
  spinner: {
    marginTop: 20,
  },
  hint: {
    color: '#aaa',
    textAlign: 'center',
    paddingHorizontal: 30,
    fontSize: 14,
    marginBottom: 30,
  },
  rescan: {
    alignItems: 'center',
    marginBottom: 30,
  },
  rescanText: {
    color: '#007AFF',
    fontSize: 16,
  },
});

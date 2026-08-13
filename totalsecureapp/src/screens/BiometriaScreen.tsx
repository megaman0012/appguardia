import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { CameraCapture } from '../components/CameraCapture';

export const BiometriaScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [isEntrada, setIsEntrada] = useState(true);
  const [showCamera, setShowCamera] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string } | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (institucion?.ins_code === undefined) return;
    if (!photo) {
      Alert.alert('Aviso', 'Tome la foto de la marcación');
      return;
    }
    setEnviando(true);
    try {
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e: any) {
        Alert.alert('Error', e.message || 'No se pudo obtener la ubicación');
        setEnviando(false);
        return;
      }

      const formData = new FormData();
      formData.append('institucion', String(institucion.ins_code));
      formData.append('is_entrada', isEntrada ? '1' : '0');
      formData.append('latitud', coords.lat);
      formData.append('longitud', coords.lng);
      formData.append('file', {
        uri: photo.uri,
        name: 'foto.jpg',
        type: 'image/jpeg',
      } as any);

      const response = await api.post(API_ENDPOINTS.BIOMETRIA, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = response.data;
      if (data && data.message) {
        Alert.alert('Éxito', data.message, [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      } else {
        Alert.alert('Error', data?.message || 'No se pudo guardar la marcación');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al guardar la marcación');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Marcación biométrica</Text>
      </View>

      <View style={styles.toggleRow}>
        <TouchableOpacity
          style={[styles.toggle, isEntrada ? styles.toggleOn : null]}
          onPress={() => setIsEntrada(true)}
        >
          <Text style={isEntrada ? styles.toggleTextOn : styles.toggleText}>Entrada</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.toggle, !isEntrada ? styles.toggleOn : null]}
          onPress={() => setIsEntrada(false)}
        >
          <Text style={!isEntrada ? styles.toggleTextOn : styles.toggleText}>Salida</Text>
        </TouchableOpacity>
      </View>

      {!showCamera ? (
        <TouchableOpacity
          style={styles.cameraButton}
          onPress={() => setShowCamera(true)}
        >
          <Text style={styles.cameraButtonText}>
            {photo ? 'Cambiar foto' : 'Tomar foto'}
          </Text>
        </TouchableOpacity>
      ) : (
        <View style={styles.cameraWrap}>
          <CameraCapture
            title="Tomar marcación"
            onCapture={(pic) => {
              setPhoto({ uri: pic.uri });
              setShowCamera(false);
            }}
            onCancel={() => setShowCamera(false)}
          />
        </View>
      )}

      <TouchableOpacity
        style={styles.saveButton}
        onPress={enviar}
        disabled={enviando}
      >
        {enviando ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.saveButtonText}>
            Registrar {isEntrada ? 'entrada' : 'salida'}
          </Text>
        )}
      </TouchableOpacity>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  backBtn: { marginRight: 12 },
  backText: { fontSize: 16, color: '#007AFF' },
  title: { fontSize: 20, fontWeight: 'bold', color: '#333' },
  toggleRow: {
    flexDirection: 'row',
    marginHorizontal: 20,
    marginTop: 20,
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#007AFF',
  },
  toggle: { flex: 1, paddingVertical: 12, alignItems: 'center', backgroundColor: '#fff' },
  toggleOn: { backgroundColor: '#007AFF' },
  toggleText: { color: '#007AFF', fontWeight: '600' },
  toggleTextOn: { color: '#fff', fontWeight: '600' },
  cameraButton: {
    backgroundColor: '#6c757d',
    marginHorizontal: 20,
    marginTop: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  cameraButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  cameraWrap: {
    height: 400,
    margin: 20,
    borderRadius: 8,
    overflow: 'hidden',
  },
  saveButton: {
    backgroundColor: '#28a745',
    marginHorizontal: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});

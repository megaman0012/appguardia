import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  ScrollView,
  Image,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { CameraCapture } from '../components/CameraCapture';

export const NovedadCreateScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [observacion, setObservacion] = useState('');
  const [showCamera, setShowCamera] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string } | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (institucion?.ins_code === undefined) return;
    if (!observacion.trim()) {
      Alert.alert('Aviso', 'Ingrese la observación');
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
      formData.append('ins_code', String(institucion.ins_code));
      formData.append('nv_observacion', observacion.trim());
      formData.append('nv_lat', coords.lat);
      formData.append('nv_lng', coords.lng);
      if (photo) {
        formData.append('file', {
          uri: photo.uri,
          name: 'foto.jpg',
          type: 'image/jpeg',
        } as any);
      }

      const response = await api.post(API_ENDPOINTS.NOVEDAD.CREATE, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = response.data;
      if (data && data.result === 'success') {
        Alert.alert('Éxito', data.message || 'Novedad cargada', [
          { text: 'OK', onPress: () => navigation.goBack() },
        ]);
      } else if (data && data.errors) {
        Alert.alert('Error', String(Object.values(data.errors)[0]));
      } else {
        Alert.alert('Error', data?.message || 'No se pudo guardar la novedad');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al guardar la novedad');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Nueva novedad</Text>
      </View>

      <TextInput
        placeholder="Observación"
        value={observacion}
        onChangeText={setObservacion}
        style={styles.input}
        multiline
      />

      {photo && (
        <Image source={{ uri: photo.uri }} style={styles.photoPreview} resizeMode="cover" />
      )}

      {!showCamera ? (
        <TouchableOpacity style={styles.photoButton} onPress={() => setShowCamera(true)}>
          <Text style={styles.photoButtonText}>
            {photo ? 'Cambiar foto' : 'Agregar foto'}
          </Text>
        </TouchableOpacity>
      ) : (
        <View style={styles.cameraWrap}>
          <CameraCapture
            onCapture={(pic) => {
              setPhoto({ uri: pic.uri });
              setShowCamera(false);
            }}
            onCancel={() => setShowCamera(false)}
          />
        </View>
      )}

      <TouchableOpacity style={styles.saveButton} onPress={enviar} disabled={enviando}>
        {enviando ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.saveButtonText}>Guardar novedad</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  content: { paddingBottom: 40 },
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
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    margin: 20,
    fontSize: 15,
    minHeight: 100,
    textAlignVertical: 'top',
  },
  photoPreview: {
    width: '90%',
    height: 170,
    borderRadius: 8,
    marginHorizontal: 20,
    marginTop: 0,
  },
  photoButton: {
    backgroundColor: '#6c757d',
    marginHorizontal: 20,
    marginTop: 16,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
  },
  photoButtonText: { color: '#fff', fontWeight: '600' },
  cameraWrap: { height: 340, marginHorizontal: 20, marginTop: 16, borderRadius: 8, overflow: 'hidden' },
  saveButton: {
    backgroundColor: '#28a745',
    marginHorizontal: 20,
    marginTop: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});

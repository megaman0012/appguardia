import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  FlatList,
  TextInput,
  Image,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { formatDateTime } from '../utils/format';
import { CameraCapture } from '../components/CameraCapture';

interface Detalle {
  rd_id: number;
  rd_im_code: string | null;
  rd_fecha_hora: string;
  rd_observacion: string;
  rd_foto: string | null;
  rd_lat: string;
  rd_lng: string;
}

export const RondaDetalleScreen = ({ navigation, route }: any) => {
  const { rc_id } = route.params;
  const { institucion } = useAuth();
  const [detalle, setDetalle] = useState<Detalle[]>([]);
  const [loading, setLoading] = useState(true);
  const [enviando, setEnviando] = useState(false);
  const [observacion, setObservacion] = useState('');
  const [showCamera, setShowCamera] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string } | null>(null);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      const response = await api.post(API_ENDPOINTS.RONDAS.DETALLE, {
        ins_code: insCode,
        rc_id,
      });
      const data = response.data;
      if (data && Array.isArray(data.rdNovedades)) {
        setDetalle(data.rdNovedades);
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al cargar detalle');
    } finally {
      setLoading(false);
    }
  }, [insCode, rc_id]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const enviarObservacion = async () => {
    if (insCode === undefined) return;
    if (!observacion.trim()) {
      Alert.alert('Aviso', 'Ingrese una observación');
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
      formData.append('ins_code', String(insCode));
      formData.append('rc_id', String(rc_id));
      formData.append('rd_observacion', observacion);
      formData.append('rd_lat', coords.lat);
      formData.append('rd_lng', coords.lng);
      if (photo) {
        formData.append('file', {
          uri: photo.uri,
          name: 'foto.jpg',
          type: 'image/jpeg',
        } as any);
      }

      const response = await api.post(API_ENDPOINTS.RONDAS.DETALLE_GESTION, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = response.data;
      if (data && data.result === 'success') {
        Alert.alert('Ronda', data.message || 'Observación cargada');
        setObservacion('');
        setPhoto(null);
        cargar();
      } else {
        Alert.alert('Error', data?.message || 'No se pudo guardar');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al guardar observación');
    } finally {
      setEnviando(false);
    }
  };

  const escanearQR = () => {
    navigation.navigate('ScannerQR', { rc_id });
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Ronda #{rc_id}</Text>
      </View>

      <TouchableOpacity style={styles.qrButton} onPress={escanearQR}>
        <Text style={styles.qrButtonText}>Escanear QR de marcador</Text>
      </TouchableOpacity>

      <View style={styles.formCard}>
        <Text style={styles.formTitle}>Nueva observación</Text>
        <TextInput
          placeholder="Observación de la ronda"
          value={observacion}
          onChangeText={setObservacion}
          style={styles.input}
          multiline
        />

        {photo && (
          <View style={styles.photoPreviewWrap}>
            <Image source={{ uri: photo.uri }} style={styles.photoPreview} />
            <TouchableOpacity onPress={() => setPhoto(null)}>
              <Text style={styles.photoRemove}>Quitar foto</Text>
            </TouchableOpacity>
          </View>
        )}

        {!showCamera ? (
          <View style={styles.formActions}>
            <TouchableOpacity
              style={styles.photoButton}
              onPress={() => setShowCamera(true)}
            >
              <Text style={styles.photoButtonText}>
                {photo ? 'Cambiar foto' : 'Agregar foto'}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.saveButton}
              onPress={enviarObservacion}
              disabled={enviando}
            >
              {enviando ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.saveButtonText}>Guardar</Text>
              )}
            </TouchableOpacity>
          </View>
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
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList
          data={detalle}
          keyExtractor={(item) => String(item.rd_id)}
          contentContainerStyle={styles.list}
          ListHeaderComponent={
            <Text style={styles.listTitle}>Registros de la ronda ({detalle.length})</Text>
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>Aún no hay registros en esta ronda</Text>
          }
          renderItem={({ item }) => (
            <View style={styles.item}>
              <Text style={styles.itemDate}>{formatDateTime(item.rd_fecha_hora)}</Text>
              <Text style={styles.itemObs}>{item.rd_observacion}</Text>
              {item.rd_im_code ? (
                <Text style={styles.itemMarker}>Marcador: {item.rd_im_code}</Text>
              ) : null}
              {item.rd_foto ? (
                <Image
                  source={{ uri: item.rd_foto }}
                  style={styles.itemPhoto}
                  resizeMode="cover"
                />
              ) : null}
            </View>
          )}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
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
    color: '#333',
  },
  qrButton: {
    backgroundColor: '#6f42c1',
    marginHorizontal: 20,
    marginTop: 16,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  qrButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  formCard: {
    backgroundColor: '#f8f9fa',
    margin: 20,
    borderRadius: 10,
    padding: 16,
  },
  formTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 10,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    backgroundColor: '#fff',
    fontSize: 15,
    minHeight: 80,
    textAlignVertical: 'top',
  },
  photoPreviewWrap: {
    marginTop: 10,
  },
  photoPreview: {
    width: '100%',
    height: 160,
    borderRadius: 8,
  },
  photoRemove: {
    color: '#dc3545',
    marginTop: 6,
    textAlign: 'center',
  },
  formActions: {
    flexDirection: 'row',
    marginTop: 12,
  },
  photoButton: {
    backgroundColor: '#6c757d',
    borderRadius: 6,
    paddingVertical: 10,
    paddingHorizontal: 16,
    marginRight: 10,
  },
  photoButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
  saveButton: {
    backgroundColor: '#007AFF',
    borderRadius: 6,
    paddingVertical: 10,
    paddingHorizontal: 24,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveButtonText: {
    color: '#fff',
    fontWeight: '600',
  },
  cameraWrap: {
    marginTop: 12,
    height: 320,
    borderRadius: 8,
    overflow: 'hidden',
  },
  list: {
    paddingHorizontal: 20,
    paddingBottom: 30,
  },
  listTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: '#333',
    marginBottom: 10,
  },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
  },
  itemDate: {
    fontSize: 13,
    color: '#007AFF',
    fontWeight: '600',
  },
  itemObs: {
    fontSize: 15,
    color: '#333',
    marginTop: 4,
  },
  itemMarker: {
    fontSize: 13,
    color: '#666',
    marginTop: 4,
  },
  itemPhoto: {
    width: '100%',
    height: 150,
    borderRadius: 8,
    marginTop: 10,
  },
  emptyText: {
    textAlign: 'center',
    color: '#999',
    marginTop: 20,
    fontSize: 15,
  },
});

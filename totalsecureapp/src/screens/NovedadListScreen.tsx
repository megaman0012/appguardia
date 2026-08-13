import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  FlatList,
  RefreshControl,
  Image,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { formatDateTime } from '../utils/format';

interface Novedad {
  nv_id: number;
  nv_fecha_hora: string;
  nv_observacion: string;
  nv_foto: string | null;
  nv_lat: string;
  nv_lng: string;
}

export const NovedadListScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [novedades, setNovedades] = useState<Novedad[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    const d = new Date();
    const fecha = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
      d.getDate()
    ).padStart(2, '0')}`;
    try {
      const response = await api.post(API_ENDPOINTS.NOVEDAD.LIST_BY_DATE, {
        date: fecha,
        ins_code: insCode,
      });
      const data = response.data;
      if (data && Array.isArray(data.nvNovedad)) {
        setNovedades(data.nvNovedad);
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al cargar novedades');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [insCode]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Novedades del día</Text>
      </View>

      <TouchableOpacity
        style={styles.newButton}
        onPress={() => navigation.navigate('NovedadCreate')}
      >
        <Text style={styles.newButtonText}>Nueva novedad</Text>
      </TouchableOpacity>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList
          data={novedades}
          keyExtractor={(item) => String(item.nv_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>No hay novedades registradas hoy</Text>
          }
          renderItem={({ item }) => (
            <View style={styles.item}>
              <Text style={styles.itemDate}>{formatDateTime(item.nv_fecha_hora)}</Text>
              <Text style={styles.itemObs}>{item.nv_observacion}</Text>
              {item.nv_foto ? (
                <Image source={{ uri: item.nv_foto }} style={styles.itemPhoto} resizeMode="cover" />
              ) : null}
            </View>
          )}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
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
  newButton: {
    backgroundColor: '#007AFF',
    marginHorizontal: 20,
    marginTop: 16,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
  },
  newButtonText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  list: { paddingHorizontal: 20, paddingBottom: 30, paddingTop: 16 },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 14,
    marginBottom: 12,
  },
  itemDate: { fontSize: 13, color: '#007AFF', fontWeight: '600' },
  itemObs: { fontSize: 15, color: '#333', marginTop: 4 },
  itemPhoto: { width: '100%', height: 150, borderRadius: 8, marginTop: 10 },
  emptyText: { textAlign: 'center', color: '#999', marginTop: 40, fontSize: 16 },
});

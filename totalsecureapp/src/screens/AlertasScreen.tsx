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
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { formatDateTime } from '../utils/format';

interface Alerta {
  al_code: number;
  al_observacion: string;
  al_estado_alerta: string;
  al_created_at?: string;
  al_fecha?: string;
  usuarios?: { usu_nmbcom?: string } | null;
}

export const AlertasScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [alertas, setAlertas] = useState<Alerta[]>([]);
  const [consoleMode, setConsoleMode] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      const response = await api.post(API_ENDPOINTS.NOTIFICACION.ALERT_TODAY, {
        ins: insCode,
      });
      const data = response.data;
      if (data && Array.isArray(data.alerts)) {
        setAlertas(data.alerts);
        setConsoleMode(data.console || 0);
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al cargar alertas');
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
        <Text style={styles.title}>Alertas de hoy</Text>
        {consoleMode === 1 && (
          <View style={styles.consoleBadge}>
            <Text style={styles.consoleBadgeText}>CONSOLA</Text>
          </View>
        )}
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList
          data={alertas}
          keyExtractor={(item) => String(item.al_code)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>No hay alertas registradas hoy</Text>
          }
          renderItem={({ item }) => (
            <View style={styles.item}>
              <View style={styles.itemHeader}>
                <Text style={styles.itemCode}>Alerta #{item.al_code}</Text>
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{item.al_estado_alerta || 'Pendiente'}</Text>
                </View>
              </View>
              {item.al_observacion ? (
                <Text style={styles.itemObs}>{item.al_observacion}</Text>
              ) : null}
              <Text style={styles.itemDate}>
                {formatDateTime(item.al_created_at || item.al_fecha)}
              </Text>
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
  title: { fontSize: 20, fontWeight: 'bold', color: '#333', flex: 1 },
  consoleBadge: {
    backgroundColor: '#6f42c1',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  consoleBadgeText: { color: '#fff', fontSize: 11, fontWeight: '700' },
  list: { padding: 20, paddingBottom: 30 },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 14,
    marginBottom: 12,
  },
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemCode: { fontSize: 15, fontWeight: '600', color: '#333' },
  badge: { backgroundColor: '#cfe8ff', borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3 },
  badgeText: { fontSize: 11, fontWeight: '700', color: '#333' },
  itemObs: { fontSize: 15, color: '#333', marginTop: 4 },
  itemDate: { fontSize: 13, color: '#007AFF', marginTop: 6, fontWeight: '600' },
  emptyText: { textAlign: 'center', color: '#999', marginTop: 40, fontSize: 16 },
});

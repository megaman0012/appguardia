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
import { getCurrentLocation } from '../utils/location';
import { formatDateTime } from '../utils/format';

interface Ronda {
  rc_id: number;
  rc_fecha_inicio: string;
  rc_fecha_fin: string | null;
  rc_estado_ronda: string;
}

export const RondaListScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [rondas, setRondas] = useState<Ronda[]>([]);
  const [inicio, setInicio] = useState<number>(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [accion, setAccion] = useState(false);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      const response = await api.post(API_ENDPOINTS.RONDAS.LIST, { ins_code: insCode });
      const data = response.data;
      if (data && Array.isArray(data.rondas)) {
        setRondas(data.rondas);
        setInicio(data.inicio || 0);
      }
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Error al cargar rondas';
      Alert.alert('Error', msg);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [insCode]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const gestionRonda = async (estado: 'Iniciada' | 'Finalizada' | 'Cancelada', rcId?: number) => {
    if (insCode === undefined) return;
    setAccion(true);
    try {
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e: any) {
        Alert.alert('Error', e.message || 'No se pudo obtener la ubicación');
        setAccion(false);
        return;
      }

      const payload: any = {
        ins_code: insCode,
        rc_code: estado === 'Iniciada' ? 0 : rcId,
        rc_estado_ronda: estado,
      };
      if (estado === 'Iniciada') {
        payload.rc_lat_inicio = coords.lat;
        payload.rc_lng_inicio = coords.lng;
      } else {
        payload.rc_lat_fin = coords.lat;
        payload.rc_lng_fin = coords.lng;
      }

      const response = await api.post(API_ENDPOINTS.RONDAS.GESTION, payload);
      const data = response.data;
      if (data && data.result === 'success') {
        Alert.alert('Ronda', data.message || 'Operación exitosa');
        cargar();
      } else if (data && data.errors) {
        Alert.alert('Error', Object.values(data.errors)[0] as string);
      } else {
        Alert.alert('Error', data?.message || 'No se pudo completar la operación');
      }
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Error al gestionar la ronda';
      Alert.alert('Error', msg);
    } finally {
      setAccion(false);
    }
  };

  const confirmar = (estado: 'Finalizada' | 'Cancelada', ronda: Ronda) => {
    Alert.alert(
      `¿${estado === 'Finalizada' ? 'Finalizar' : 'Cancelar'} ronda?`,
      'Se registrará la ubicación y hora actual.',
      [
        { text: 'No', style: 'cancel' },
        { text: 'Sí', onPress: () => gestionRonda(estado, ronda.rc_id) },
      ]
    );
  };

  const verDetalle = (ronda: Ronda) => {
    navigation.navigate('RondaDetalle', {
      rc_id: ronda.rc_id,
      estado: ronda.rc_estado_ronda,
    });
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Rondas</Text>
      </View>

      <Text style={styles.subtitle}>
        Institución: {institucion?.ins_descripcion || 'No seleccionada'}
      </Text>

      <View style={styles.acciones}>
        <TouchableOpacity
          style={[styles.startButton, inicio ? styles.startButtonDisabled : null]}
          disabled={!!inicio || accion}
          onPress={() => gestionRonda('Iniciada')}
        >
          {accion ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.startButtonText}>Iniciar ronda</Text>
          )}
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList
          data={rondas}
          keyExtractor={(item) => String(item.rc_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>No hay rondas registradas</Text>
          }
          renderItem={({ item }) => (
            <TouchableOpacity
              style={styles.item}
              onPress={() => verDetalle(item)}
            >
              <View style={styles.itemHeader}>
                <Text style={styles.itemId}>Ronda #{item.rc_id}</Text>
                <View
                  style={[
                    styles.badge,
                    item.rc_estado_ronda === 'Iniciada'
                      ? styles.badgeActiva
                      : item.rc_estado_ronda === 'Finalizada'
                        ? styles.badgeFinalizada
                        : styles.badgeCancelada,
                  ]}
                >
                  <Text style={styles.badgeText}>{item.rc_estado_ronda}</Text>
                </View>
              </View>
              <Text style={styles.itemDate}>
                Inicio: {formatDateTime(item.rc_fecha_inicio)}
              </Text>
              {item.rc_fecha_fin ? (
                <Text style={styles.itemDate}>
                  Fin: {formatDateTime(item.rc_fecha_fin)}
                </Text>
              ) : null}

              {item.rc_estado_ronda === 'Iniciada' && (
                <View style={styles.itemActions}>
                  <TouchableOpacity
                    style={styles.btnFinalizar}
                    onPress={() => confirmar('Finalizada', item)}
                  >
                    <Text style={styles.btnFinalizarText}>Finalizar</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.btnCancelar}
                    onPress={() => confirmar('Cancelada', item)}
                  >
                    <Text style={styles.btnCancelarText}>Cancelar</Text>
                  </TouchableOpacity>
                </View>
              )}
            </TouchableOpacity>
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
  subtitle: {
    paddingHorizontal: 20,
    paddingTop: 12,
    color: '#666',
    fontSize: 14,
  },
  acciones: {
    padding: 20,
  },
  startButton: {
    backgroundColor: '#28a745',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  startButtonDisabled: {
    backgroundColor: '#6c757d',
  },
  startButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  list: {
    paddingHorizontal: 20,
    paddingBottom: 30,
  },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 16,
    marginBottom: 12,
  },
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemId: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  badge: {
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  badgeActiva: {
    backgroundColor: '#cfe8ff',
  },
  badgeFinalizada: {
    backgroundColor: '#d4edda',
  },
  badgeCancelada: {
    backgroundColor: '#f8d7da',
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#333',
  },
  itemDate: {
    fontSize: 14,
    color: '#666',
    marginTop: 4,
  },
  itemActions: {
    flexDirection: 'row',
    marginTop: 12,
  },
  btnFinalizar: {
    backgroundColor: '#007AFF',
    borderRadius: 6,
    paddingVertical: 8,
    paddingHorizontal: 16,
    marginRight: 10,
  },
  btnFinalizarText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  btnCancelar: {
    backgroundColor: '#dc3545',
    borderRadius: 6,
    paddingVertical: 8,
    paddingHorizontal: 16,
  },
  btnCancelarText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  emptyText: {
    textAlign: 'center',
    color: '#999',
    marginTop: 40,
    fontSize: 16,
  },
});

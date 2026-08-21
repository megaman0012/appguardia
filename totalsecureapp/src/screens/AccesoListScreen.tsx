import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  ScrollView,
  FlatList,
  RefreshControl,
  Image,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { formatDateTime } from '../utils/format';

interface AccesoItem {
  ac_code: number;
  ac_tipo: string;
  ac_is_entrada: number;
  ac_estado_acceso?: string;
  tiempo_permanencia?: string | null;
  ac_foto: string | null;
  ac_temperatura?: string | null;
  ac_nomb_acomp?: string | null;
  ac_observaciones?: string | null;
  ac_created_at?: string;
  acceso_persona?: {
    ap_documento?: string;
    ap_nombres?: string;
    ap_apellidos?: string;
  } | null;
  persona?: {
    ap_documento?: string;
    ap_nombres?: string;
    ap_apellidos?: string;
  } | null;
  vehiculo?: {
    av_patente?: string;
    av_empresa?: string;
    av_color?: string;
    av_marca?: string;
    av_modelo?: string;
  } | null;
  visitante?: {
    avi_motivo?: string;
    avi_area_visita?: string;
    avi_persona_visita?: string;
  } | null;
}

interface PreregistroItem {
  apr_code: number;
  apr_fecha_estimada: string;
  apr_hora_estimada?: string | null;
  apr_motivo?: string | null;
  apr_area_visita?: string | null;
  apr_estado: string;
  persona?: {
    ap_documento?: string;
    ap_nombres?: string;
    ap_apellidos?: string;
  } | null;
}

const TIPOS: Record<string, string> = {
  peatonal: 'Peatón',
  empleado: 'Empleado',
  visitante: 'Visitante',
  proveedor: 'Proveedor',
  vehicular: 'Vehículo',
};

const ESTADOS_ACCESO: Record<string, { label: string; style: object }> = {
  en_curso: { label: 'EN CURSO', style: {} },
  completada: { label: 'COMPLETADA', style: {} },
};

const ESTADOS_PREREGISTRO: Record<string, { label: string; style: object }> = {
  pendiente: { label: 'PENDIENTE', style: {} },
  llego: { label: 'LLEGÓ', style: {} },
  cancelado: { label: 'CANCELADO', style: {} },
};

const getEstadoAcceso = (estado: string) => {
  const base = ESTADOS_ACCESO[estado];
  if (!base) return null;
  return {
    ...base,
    style: estado === 'en_curso' ? styles.badgeCurso : styles.badgeCompletada,
  };
};

const getEstadoPreregistro = (estado: string) => {
  const base = ESTADOS_PREREGISTRO[estado];
  if (!base) return null;
  const style =
    estado === 'pendiente'
      ? styles.badgePendiente
      : estado === 'llego'
        ? styles.badgeLlego
        : styles.badgeCancelado;
  return { ...base, style };
};

type Tab = 'accesos' | 'preregistros';

export const AccesoListScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [fecha, setFecha] = useState<string>(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
      d.getDate()
    ).padStart(2, '0')}`;
  });
  const [tab, setTab] = useState<Tab>('accesos');
  const [filtroTipo, setFiltroTipo] = useState<string>('todos');
  const [accesos, setAccesos] = useState<AccesoItem[]>([]);
  const [preregistros, setPreregistros] = useState<PreregistroItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    try {
      if (tab === 'accesos') {
        const response = await api.post(API_ENDPOINTS.ACCESO.LIST_BY_INST, {
          date: fecha,
          ins_code: insCode,
        });
        const data = response.data;
        if (data && Array.isArray(data.acAccByIns)) {
          setAccesos(data.acAccByIns);
        }
      } else {
        const response = await api.post(API_ENDPOINTS.ACCESO.PREREGISTRO_LIST, {
          ins_code: insCode,
          date: fecha,
        });
        const data = response.data;
        if (data && Array.isArray(data.preregistros)) {
          setPreregistros(data.preregistros);
        }
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'Error al cargar datos');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [fecha, insCode, tab]);

  useEffect(() => {
    setLoading(true);
    cargar();
  }, [cargar]);

  const cambiarDia = (delta: number) => {
    const d = new Date(fecha + 'T12:00:00');
    d.setDate(d.getDate() + delta);
    setFecha(
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
        d.getDate()
      ).padStart(2, '0')}`
    );
  };

  const registrarSalida = async (item: AccesoItem) => {
    if (insCode === undefined) return;
    Alert.alert(
      'Registrar salida',
      `¿Registrar salida del acceso #${item.ac_code}?`,
      [
        { text: 'No', style: 'cancel' },
        {
          text: 'Sí',
          onPress: async () => {
            try {
              let coords = { lat: '0', lng: '0' };
              try {
                coords = await getCurrentLocation();
              } catch (e: any) {
                Alert.alert('Error', e.message || 'No se pudo obtener la ubicación');
                return;
              }
              const response = await api.post(API_ENDPOINTS.ACCESO.SALIDA, {
                code: item.ac_code,
                ins: insCode,
                lat: coords.lat,
                lng: coords.lng,
              });
              const data = response.data;
              if (data && data.message) {
                Alert.alert('Éxito', data.message);
                cargar();
              } else {
                Alert.alert('Error', 'No se pudo registrar la salida');
              }
            } catch (error: any) {
              Alert.alert('Error', error.response?.data?.message || 'Error al registrar salida');
            }
          },
        },
      ]
    );
  };

  const cancelarPreregistro = (item: PreregistroItem) => {
    Alert.alert(
      'Cancelar pre-registro',
      `¿Cancelar el pre-registro #${item.apr_code}?`,
      [
        { text: 'No', style: 'cancel' },
        {
          text: 'Sí, cancelar',
          style: 'destructive',
          onPress: async () => {
            try {
              const response = await api.post(API_ENDPOINTS.ACCESO.PREREGISTRO_CANCEL, {
                apr_code: item.apr_code,
              });
              if (response.data?.message) {
                Alert.alert('Éxito', response.data.message);
                cargar();
              }
            } catch (error: any) {
              Alert.alert('Error', error.response?.data?.message || 'Error al cancelar');
            }
          },
        },
      ]
    );
  };

  const accesosFiltrados =
    filtroTipo === 'todos' ? accesos : accesos.filter((a) => a.ac_tipo === filtroTipo);

  const renderAcceso = ({ item }: { item: AccesoItem }) => {
    const persona = item.persona || item.acceso_persona;
    const nombre = persona
      ? `${persona.ap_nombres || ''} ${persona.ap_apellidos || ''}`.trim()
      : '';
    const estado = getEstadoAcceso(item.ac_estado_acceso || '');
    const enCurso = item.ac_estado_acceso === 'en_curso';
    return (
      <View style={styles.item}>
        <View style={styles.itemHeader}>
          <Text style={styles.itemName}>
            {nombre || `Acceso #${item.ac_code}`}
          </Text>
          <View style={styles.badgesRow}>
            {estado && (
              <View style={[styles.badge, estado.style]}>
                <Text style={styles.badgeText}>{estado.label}</Text>
              </View>
            )}
            <View
              style={[
                styles.badge,
                item.ac_is_entrada === 1 ? styles.badgeEntrada : styles.badgeSalida,
              ]}
            >
              <Text style={styles.badgeText}>
                {item.ac_is_entrada === 1 ? 'ENTRADA' : 'SALIDA'}
              </Text>
            </View>
          </View>
        </View>

        {persona?.ap_documento ? (
          <Text style={styles.itemDetail}>Documento: {persona.ap_documento}</Text>
        ) : null}
        <Text style={styles.itemDetail}>
          Tipo: {TIPOS[item.ac_tipo] || item.ac_tipo}
        </Text>
        {item.vehiculo?.av_patente ? (
          <Text style={styles.itemDetail}>
            Vehículo: {item.vehiculo.av_patente}
            {item.vehiculo.av_marca ? ` · ${item.vehiculo.av_marca}` : ''}
            {item.vehiculo.av_color ? ` ${item.vehiculo.av_color}` : ''}
          </Text>
        ) : null}
        {item.visitante?.avi_motivo ? (
          <Text style={styles.itemDetail}>Motivo: {item.visitante.avi_motivo}</Text>
        ) : null}
        {item.visitante?.avi_area_visita ? (
          <Text style={styles.itemDetail}>Área: {item.visitante.avi_area_visita}</Text>
        ) : null}
        {item.ac_nomb_acomp ? (
          <Text style={styles.itemDetail}>Acompañante: {item.ac_nomb_acomp}</Text>
        ) : null}
        {item.tiempo_permanencia ? (
          <Text style={styles.itemPermanencia}>
            Permanencia: {item.tiempo_permanencia}
          </Text>
        ) : null}
        <Text style={styles.itemDate}>
          {formatDateTime(item.ac_created_at)}
        </Text>

        {item.ac_foto ? (
          <Image source={{ uri: item.ac_foto }} style={styles.itemPhoto} resizeMode="cover" />
        ) : null}

        {enCurso && (
          <TouchableOpacity
            style={styles.salidaButton}
            onPress={() => registrarSalida(item)}
          >
            <Text style={styles.salidaButtonText}>Registrar salida</Text>
          </TouchableOpacity>
        )}
      </View>
    );
  };

  const renderPreregistro = ({ item }: { item: PreregistroItem }) => {
    const persona = item.persona;
    const nombre = persona
      ? `${persona.ap_nombres || ''} ${persona.ap_apellidos || ''}`.trim()
      : '';
    const estado = getEstadoPreregistro(item.apr_estado);
    return (
      <View style={styles.item}>
        <View style={styles.itemHeader}>
          <Text style={styles.itemName}>
            {nombre || `Pre-registro #${item.apr_code}`}
          </Text>
          {estado && (
            <View style={[styles.badge, estado.style]}>
              <Text style={styles.badgeText}>{estado.label}</Text>
            </View>
          )}
        </View>

        {persona?.ap_documento ? (
          <Text style={styles.itemDetail}>Documento: {persona.ap_documento}</Text>
        ) : null}
        <Text style={styles.itemDetail}>
          Fecha estimada: {formatDateTime(item.apr_fecha_estimada)}
          {item.apr_hora_estimada ? ` ${item.apr_hora_estimada}` : ''}
        </Text>
        {item.apr_motivo ? (
          <Text style={styles.itemDetail}>Motivo: {item.apr_motivo}</Text>
        ) : null}
        {item.apr_area_visita ? (
          <Text style={styles.itemDetail}>Área: {item.apr_area_visita}</Text>
        ) : null}

        {item.apr_estado === 'pendiente' && (
          <TouchableOpacity
            style={[styles.salidaButton, styles.cancelButton]}
            onPress={() => cancelarPreregistro(item)}
          >
            <Text style={styles.salidaButtonText}>Cancelar pre-registro</Text>
          </TouchableOpacity>
        )}
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Accesos</Text>
      </View>

      <View style={styles.tabRow}>
        <TouchableOpacity
          style={[styles.tabBtn, tab === 'accesos' ? styles.tabOn : null]}
          onPress={() => setTab('accesos')}
        >
          <Text style={tab === 'accesos' ? styles.tabTextOn : styles.tabText}>Accesos</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tabBtn, tab === 'preregistros' ? styles.tabOn : null]}
          onPress={() => setTab('preregistros')}
        >
          <Text style={tab === 'preregistros' ? styles.tabTextOn : styles.tabText}>
            Pre-registros
          </Text>
        </TouchableOpacity>
      </View>

      <View style={styles.dateRow}>
        <TouchableOpacity style={styles.dateBtn} onPress={() => cambiarDia(-1)}>
          <Text style={styles.dateBtnText}>‹</Text>
        </TouchableOpacity>
        <Text style={styles.dateLabel}>{formatDateTime(fecha)}</Text>
        <TouchableOpacity style={styles.dateBtn} onPress={() => cambiarDia(1)}>
          <Text style={styles.dateBtnText}>›</Text>
        </TouchableOpacity>
      </View>

      {tab === 'accesos' ? (
        <View style={styles.filtrosWrap}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filtrosRow}>
            <TouchableOpacity
              style={[styles.tipoChip, filtroTipo === 'todos' ? styles.tipoChipOn : null]}
              onPress={() => setFiltroTipo('todos')}
            >
              <Text style={filtroTipo === 'todos' ? styles.tipoChipTextOn : styles.tipoChipText}>
                Todos
              </Text>
            </TouchableOpacity>
            {Object.entries(TIPOS).map(([value, label]) => (
              <TouchableOpacity
                key={value}
                style={[styles.tipoChip, filtroTipo === value ? styles.tipoChipOn : null]}
                onPress={() => setFiltroTipo(value)}
              >
                <Text style={filtroTipo === value ? styles.tipoChipTextOn : styles.tipoChipText}>
                  {label}
                </Text>
              </TouchableOpacity>
            ))}
          </ScrollView>
        </View>
      ) : (
        <TouchableOpacity
          style={styles.newButton}
          onPress={() => navigation.navigate('PreregistroForm')}
        >
          <Text style={styles.newButtonText}>Nuevo pre-registro</Text>
        </TouchableOpacity>
      )}

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : (
        <FlatList<any>
          data={tab === 'accesos' ? accesosFiltrados : preregistros}
          keyExtractor={(item: any) =>
            String(tab === 'accesos' ? item.ac_code : item.apr_code)
          }
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>
              {tab === 'accesos'
                ? filtroTipo === 'todos'
                  ? 'No hay accesos en esta fecha'
                  : 'No hay accesos de este tipo en esta fecha'
                : 'No hay pre-registros para esta fecha'}
            </Text>
          }
          renderItem={tab === 'accesos' ? renderAcceso : renderPreregistro}
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
  backBtn: { marginRight: 12 },
  backText: { fontSize: 16, color: '#007AFF' },
  title: { fontSize: 20, fontWeight: 'bold', color: '#333' },
  tabRow: {
    flexDirection: 'row',
    marginHorizontal: 20,
    marginTop: 14,
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#007AFF',
  },
  tabBtn: { flex: 1, paddingVertical: 10, alignItems: 'center', backgroundColor: '#fff' },
  tabOn: { backgroundColor: '#007AFF' },
  tabText: { color: '#007AFF', fontWeight: '600' },
  tabTextOn: { color: '#fff', fontWeight: '600' },
  dateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingTop: 14,
  },
  dateBtn: {
    backgroundColor: '#f0f0f0',
    borderRadius: 6,
    paddingHorizontal: 16,
    paddingVertical: 6,
  },
  dateBtnText: { fontSize: 18, color: '#333' },
  dateLabel: { fontSize: 15, color: '#333', fontWeight: '600' },
  filtrosWrap: { marginTop: 12 },
  filtrosRow: { paddingHorizontal: 20 },
  tipoChip: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 16,
    paddingVertical: 6,
    paddingHorizontal: 13,
    marginRight: 8,
  },
  tipoChipOn: { backgroundColor: '#007AFF', borderColor: '#007AFF' },
  tipoChipText: { color: '#333', fontSize: 13 },
  tipoChipTextOn: { color: '#fff', fontWeight: '600', fontSize: 13 },
  newButton: {
    backgroundColor: '#007AFF',
    marginHorizontal: 20,
    marginTop: 14,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
  },
  newButtonText: { color: '#fff', fontSize: 15, fontWeight: '600' },
  list: { paddingHorizontal: 20, paddingBottom: 30, paddingTop: 14 },
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
  itemName: { fontSize: 15, fontWeight: '600', color: '#333', flex: 1, marginRight: 8 },
  badgesRow: { flexDirection: 'row', alignItems: 'center' },
  badge: { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3, marginLeft: 6 },
  badgeEntrada: { backgroundColor: '#d4edda' },
  badgeSalida: { backgroundColor: '#f8d7da' },
  badgeCurso: { backgroundColor: '#cce5ff' },
  badgeCompletada: { backgroundColor: '#e2e3e5' },
  badgePendiente: { backgroundColor: '#fff3cd' },
  badgeLlego: { backgroundColor: '#d4edda' },
  badgeCancelado: { backgroundColor: '#f8d7da' },
  badgeText: { fontSize: 11, fontWeight: '700', color: '#333' },
  itemDetail: { fontSize: 14, color: '#555', marginTop: 4 },
  itemPermanencia: { fontSize: 14, color: '#856404', marginTop: 4, fontWeight: '600' },
  itemDate: { fontSize: 13, color: '#007AFF', marginTop: 6, fontWeight: '600' },
  itemPhoto: { width: '100%', height: 140, borderRadius: 8, marginTop: 10 },
  salidaButton: {
    backgroundColor: '#6c757d',
    borderRadius: 6,
    paddingVertical: 9,
    alignItems: 'center',
    marginTop: 10,
  },
  cancelButton: { backgroundColor: '#dc3545' },
  salidaButtonText: { color: '#fff', fontWeight: '600' },
  emptyText: { textAlign: 'center', color: '#999', marginTop: 40, fontSize: 16 },
});

import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  FlatList,
  Modal,
  RefreshControl,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { formatDateTime } from '../utils/format';
import { getCurrentLocation } from '../utils/location';
import { ahoraDelDispositivo, useIdempotencia } from '../utils/idempotencia';
import { Encabezado } from '../components/Encabezado';
import { COLORES, colorDeEstadoAlerta, colorDePrioridad } from '../utils/tema';

interface Alerta {
  al_code: number;
  al_observacion: string;
  al_estado_alerta: string;
  al_prioridad?: string;
  al_created_at?: string;
  al_fecha?: string;
  usuarios?: { usu_nmbcom?: string } | null;
}

/** Prioridades que ofrece el formulario, en el orden en que se deciden. */
const PRIORIDADES = [
  { valor: 'critica', etiqueta: 'Crítica' },
  { valor: 'alta', etiqueta: 'Alta' },
  { valor: 'media', etiqueta: 'Media' },
  { valor: 'baja', etiqueta: 'Baja' },
] as const;

export const AlertasScreen = ({ navigation }: { navigation: any }) => {
  const { institucion, can } = useAuth();
  const [alertas, setAlertas] = useState<Alerta[]>([]);
  const [consoleMode, setConsoleMode] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const [formVisible, setFormVisible] = useState(false);
  const [observacion, setObservacion] = useState('');
  const [prioridad, setPrioridad] = useState<string>('critica');
  const [enviando, setEnviando] = useState(false);

  const { uuidPara, confirmar } = useIdempotencia();
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

  const enviarAlerta = async () => {
    if (insCode === undefined) return;

    const texto = observacion.trim();
    if (texto === '') {
      Alert.alert('Falta el motivo', 'Escriba brevemente qué está ocurriendo.');
      return;
    }

    setEnviando(true);
    try {
      // ⚠️ La ubicación NO bloquea el envío. El endpoint exige lat/lng, pero
      // una emergencia no se puede perder porque el GPS tarde o el guardia esté
      // bajo techo: si no hay lectura se manda 0/0, que el servidor ya
      // interpreta como «no se sabe» y no como una coordenada real.
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e) {
        // Se sigue igual, a propósito.
      }

      const { data } = await api.post(API_ENDPOINTS.NOTIFICACION.ALERT_CREAR, {
        ins: insCode,
        lat: coords.lat,
        lng: coords.lng,
        observacion: texto,
        prioridad,
        client_uuid: uuidPara('alerta'),
        ocurrido_en: ahoraDelDispositivo(),
      });

      if (data?.success) {
        confirmar('alerta');
        setObservacion('');
        setPrioridad('critica');
        setFormVisible(false);
        Alert.alert('Alerta enviada', 'La central fue notificada.');
        cargar();
      } else {
        const detalle = data?.errors ? String(Object.values(data.errors)[0]) : data?.message;
        Alert.alert('No se pudo enviar', detalle || 'Intente de nuevo.');
      }
    } catch (error: any) {
      Alert.alert(
        'No se pudo enviar',
        error.response?.data?.message || 'Sin conexión. Vuelva a intentar.'
      );
    } finally {
      setEnviando(false);
    }
  };

  const puedeCrear = can('alertas.crear');

  return (
    <View style={styles.container}>
      <Encabezado
        titulo="Alertas de hoy"
        onVolver={() => navigation.goBack()}
        derecha={
          consoleMode === 1 ? (
            <View style={styles.consoleBadge}>
              <Text style={styles.consoleBadgeText}>CONSOLA</Text>
            </View>
          ) : undefined
        }
      />

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={COLORES.marca} />
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
          renderItem={({ item }) => {
            const color = colorDeEstadoAlerta(item.al_estado_alerta);

            return (
              // La franja de color a la izquierda dice el estado sin leer:
              // antes todas las alertas se veían idénticas y había que leer la
              // palabra del badge para distinguir una pendiente de una cerrada.
              <View style={[styles.item, { borderLeftColor: color }]}>
                <View style={styles.itemHeader}>
                  <Text style={styles.itemCode}>Alerta #{item.al_code}</Text>
                  <View style={[styles.badge, { backgroundColor: color }]}>
                    <Text style={styles.badgeText}>
                      {(item.al_estado_alerta || 'Pendiente').toUpperCase()}
                    </Text>
                  </View>
                </View>

                {item.al_prioridad ? (
                  <Text style={[styles.prioridad, { color: colorDePrioridad(item.al_prioridad) }]}>
                    Prioridad {item.al_prioridad}
                  </Text>
                ) : null}

                {item.al_observacion ? (
                  <Text style={styles.itemObs}>{item.al_observacion}</Text>
                ) : null}

                <Text style={styles.itemDate}>
                  {formatDateTime(item.al_created_at || item.al_fecha)}
                </Text>
              </View>
            );
          }}
        />
      )}

      {/*
        El botón de emergencia. La pantalla era solo lectura: listaba las
        alertas del día y no había forma de generar una, aunque el endpoint
        `/alert/crear` existía en el backend desde el principio. Al rol
        Vigilante además le faltaba el permiso `alertas.crear`, así que incluso
        con el botón habría recibido 403 (corregido en 2026_09_08_230001).
      */}
      {puedeCrear && (
        <TouchableOpacity
          style={styles.botonEmergencia}
          onPress={() => setFormVisible(true)}
          activeOpacity={0.85}
        >
          <Text style={styles.botonEmergenciaTexto}>EMERGENCIA</Text>
        </TouchableOpacity>
      )}

      <Modal visible={formVisible} animationType="slide" transparent onRequestClose={() => setFormVisible(false)}>
        <View style={styles.modalFondo}>
          <View style={styles.modalCaja}>
            <Text style={styles.modalTitulo}>Nueva alerta</Text>

            <Text style={styles.etiqueta}>¿Qué está ocurriendo?</Text>
            <TextInput
              style={styles.entrada}
              value={observacion}
              onChangeText={setObservacion}
              placeholder="Ej. Intento de ingreso forzado en la puerta 3"
              placeholderTextColor={COLORES.textoSuave}
              multiline
              numberOfLines={3}
              maxLength={1000}
              autoFocus
            />

            <Text style={styles.etiqueta}>Prioridad</Text>
            <View style={styles.prioridades}>
              {PRIORIDADES.map((p) => {
                const activa = prioridad === p.valor;
                return (
                  <TouchableOpacity
                    key={p.valor}
                    onPress={() => setPrioridad(p.valor)}
                    style={[
                      styles.chip,
                      activa && { backgroundColor: colorDePrioridad(p.valor), borderColor: colorDePrioridad(p.valor) },
                    ]}
                  >
                    <Text style={[styles.chipTexto, activa && styles.chipTextoActivo]}>{p.etiqueta}</Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            <View style={styles.modalBotones}>
              <TouchableOpacity
                style={[styles.modalBoton, styles.modalCancelar]}
                onPress={() => setFormVisible(false)}
                disabled={enviando}
              >
                <Text style={styles.modalCancelarTexto}>Cancelar</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modalBoton, styles.modalEnviar, enviando && styles.modalDeshabilitado]}
                onPress={enviarAlerta}
                disabled={enviando}
              >
                {enviando ? (
                  <ActivityIndicator color={COLORES.textoSobreMarca} />
                ) : (
                  <Text style={styles.modalEnviarTexto}>Enviar alerta</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORES.fondo },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  consoleBadge: {
    backgroundColor: COLORES.textoSobreMarca,
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  consoleBadgeText: { color: COLORES.marca, fontSize: 11, fontWeight: '700' },

  // El espacio de abajo deja libre el botón de emergencia: sin esto la última
  // alerta de la lista queda tapada por él.
  list: { padding: 16, paddingBottom: 110 },

  item: {
    backgroundColor: COLORES.fondoSuave,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderLeftWidth: 5,
    padding: 14,
    marginBottom: 12,
  },
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  itemCode: { fontSize: 15, fontWeight: '700', color: COLORES.texto },
  badge: { borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3 },
  badgeText: { fontSize: 10, fontWeight: '800', color: COLORES.textoSobreMarca, letterSpacing: 0.4 },
  prioridad: { fontSize: 12, fontWeight: '700', marginTop: 4, textTransform: 'capitalize' },
  itemObs: { fontSize: 15, color: COLORES.texto, marginTop: 6 },
  itemDate: { fontSize: 13, color: COLORES.textoSuave, marginTop: 8, fontWeight: '600' },
  emptyText: { textAlign: 'center', color: COLORES.textoSuave, marginTop: 40, fontSize: 16 },

  botonEmergencia: {
    position: 'absolute',
    left: 16,
    right: 16,
    bottom: 20,
    backgroundColor: COLORES.critico,
    borderRadius: 14,
    paddingVertical: 18,
    alignItems: 'center',
    // Sombra para que se lea como algo que flota sobre la lista.
    elevation: 6,
    shadowColor: '#000',
    shadowOpacity: 0.25,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
  },
  botonEmergenciaTexto: {
    color: COLORES.textoSobreMarca,
    fontSize: 18,
    fontWeight: '800',
    letterSpacing: 1,
  },

  modalFondo: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.45)',
    justifyContent: 'flex-end',
  },
  modalCaja: {
    backgroundColor: COLORES.fondo,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    padding: 20,
    paddingBottom: 30,
  },
  modalTitulo: { fontSize: 19, fontWeight: '700', color: COLORES.texto, marginBottom: 14 },
  etiqueta: { fontSize: 13, fontWeight: '600', color: COLORES.textoSuave, marginBottom: 6, marginTop: 10 },
  entrada: {
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderRadius: 10,
    padding: 12,
    fontSize: 15,
    color: COLORES.texto,
    minHeight: 84,
    textAlignVertical: 'top',
  },
  prioridades: { flexDirection: 'row', flexWrap: 'wrap' },
  chip: {
    borderWidth: 1,
    borderColor: COLORES.borde,
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 7,
    marginRight: 8,
    marginBottom: 8,
  },
  chipTexto: { fontSize: 14, color: COLORES.texto, fontWeight: '600' },
  chipTextoActivo: { color: COLORES.textoSobreMarca },
  modalBotones: { flexDirection: 'row', marginTop: 18 },
  modalBoton: { flex: 1, borderRadius: 12, paddingVertical: 15, alignItems: 'center' },
  modalCancelar: { backgroundColor: COLORES.fondoSuave, borderWidth: 1, borderColor: COLORES.borde, marginRight: 10 },
  modalCancelarTexto: { fontSize: 16, fontWeight: '600', color: COLORES.texto },
  modalEnviar: { backgroundColor: COLORES.critico },
  modalEnviarTexto: { fontSize: 16, fontWeight: '700', color: COLORES.textoSobreMarca },
  modalDeshabilitado: { opacity: 0.6 },
});

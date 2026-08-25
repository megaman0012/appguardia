import React, { useCallback, useEffect, useState } from 'react';
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

interface TurnoDelDia {
  tu_id: number;
  puesto: string | null;
  tu_hora_inicio_prevista: string;
  tu_hora_fin_prevista: string;
  tu_marcada_entrada: string | null;
  tu_marcada_salida: string | null;
  tu_estado: string;
  minutos_tardanza_display: string | null;
}

export const BiometriaScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [isEntrada, setIsEntrada] = useState(true);
  const [showCamera, setShowCamera] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string } | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [turno, setTurno] = useState<TurnoDelDia | null>(null);
  const [cargandoTurno, setCargandoTurno] = useState(true);

  // El guardia necesita saber en qué puesto le toca y a qué hora antes de
  // marcar. Si la institución no usa turnos, la pantalla funciona igual.
  const cargarTurno = useCallback(async () => {
    if (institucion?.ins_code === undefined) {
      setCargandoTurno(false);
      return;
    }
    try {
      const { data } = await api.post(API_ENDPOINTS.TURNOS.DEL_DIA, {
        ins_code: institucion.ins_code,
      });
      const primero: TurnoDelDia | undefined = (data?.turnos ?? [])[0];
      setTurno(primero ?? null);

      // Si ya abrió el turno, lo que sigue es marcar la salida.
      if (primero?.tu_marcada_entrada && !primero?.tu_marcada_salida) {
        setIsEntrada(false);
      }
    } catch (e) {
      // Un fallo aquí no debe impedir marcar: el turno es informativo.
      setTurno(null);
    } finally {
      setCargandoTurno(false);
    }
  }, [institucion]);

  useEffect(() => {
    cargarTurno();
  }, [cargarTurno]);

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
        // El backend vincula el marcaje con el turno y devuelve el resultado,
        // así el guardia ve su tardanza en el momento y no en un reporte.
        let detalle = data.message;
        if (data.turno) {
          const t = data.turno;
          detalle += t.puesto ? `\n\nPuesto: ${t.puesto}` : '';
          detalle += `\nTurno: ${t.estado}`;
          if (t.minutos_tardanza > 0) {
            detalle += `\nTardanza: ${t.minutos_tardanza} min`;
          }
          if (t.minutos_extras > 0) {
            detalle += `\nHoras extra: ${t.minutos_extras} min`;
          }
        }

        Alert.alert('Éxito', detalle, [
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

      {cargandoTurno ? (
        <View style={styles.turnoCard}>
          <ActivityIndicator size="small" color="#007AFF" />
        </View>
      ) : turno ? (
        <View style={styles.turnoCard}>
          <Text style={styles.turnoTitulo}>Su turno de hoy</Text>
          {turno.puesto ? (
            <Text style={styles.turnoPuesto}>{turno.puesto}</Text>
          ) : null}
          <Text style={styles.turnoHorario}>
            {turno.tu_hora_inicio_prevista} — {turno.tu_hora_fin_prevista}
          </Text>
          <Text style={styles.turnoEstado}>
            {turno.tu_marcada_entrada
              ? turno.tu_marcada_salida
                ? 'Turno completado'
                : 'Entrada marcada · falta la salida'
              : 'Sin marcar la entrada'}
          </Text>
          {turno.minutos_tardanza_display ? (
            <Text style={styles.turnoTardanza}>
              Tardanza: {turno.minutos_tardanza_display}
            </Text>
          ) : null}
        </View>
      ) : (
        <View style={styles.turnoCard}>
          <Text style={styles.turnoEstado}>
            No tiene un turno programado para hoy. Puede marcar igualmente.
          </Text>
        </View>
      )}

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
  turnoCard: {
    backgroundColor: '#f1f5f9',
    borderRadius: 10,
    padding: 14,
    marginHorizontal: 20,
    marginBottom: 16,
  },
  turnoTitulo: {
    fontSize: 13,
    color: '#64748b',
    fontWeight: '600',
    marginBottom: 4,
  },
  turnoPuesto: {
    fontSize: 17,
    fontWeight: 'bold',
    color: '#0f172a',
  },
  turnoHorario: {
    fontSize: 15,
    color: '#334155',
    marginTop: 2,
  },
  turnoEstado: {
    fontSize: 13,
    color: '#475569',
    marginTop: 6,
  },
  turnoTardanza: {
    fontSize: 13,
    color: '#b45309',
    marginTop: 2,
    fontWeight: '600',
  },
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

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

interface TurnoProximo {
  tu_id: number;
  local?: string | null;
  puesto?: string | null;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  avisado?: boolean;
}

interface Vacante {
  tv_id: number;
  ins_code: number;
  local?: string | null;
  puesto?: string | null;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  motivo?: string;
  es_de_mi_local?: boolean;
  postulado?: boolean;
  estado_postulacion?: string | null;
}

/**
 * Turnos que quedaron sin cubrir y este guardia puede tomar.
 *
 * La lista ya viene filtrada por el servidor: solo aparecen los turnos que
 * realmente puede aceptar (local habilitado, sin choque con otro turno suyo y
 * con descanso suficiente). Mostrarle uno que no puede tomar sería hacerle
 * perder el viaje.
 *
 * Postularse NO le asigna el turno: lo propone. Confirma el supervisor, y por
 * eso el texto del botón dice "Me postulo" y no "Tomar turno".
 */
export const VacantesScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [vacantes, setVacantes] = useState<Vacante[]>([]);
  const [misTurnos, setMisTurnos] = useState<TurnoProximo[]>([]);
  const [vista, setVista] = useState<'disponibles' | 'mis-turnos'>('disponibles');
  const [aceptaExtras, setAceptaExtras] = useState(true);
  const [mensaje, setMensaje] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [enviando, setEnviando] = useState<number | null>(null);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    try {
      const { data } = await api.post(API_ENDPOINTS.VACANTES.DISPONIBLES, {
        ins_code: insCode,
      });
      setVacantes(Array.isArray(data?.vacantes) ? data.vacantes : []);
      setAceptaExtras(data?.acepta_extras !== false);
      setMensaje(data?.mensaje || null);

      // Los turnos propios se cargan siempre: avisar que no se puede cubrir un
      // turno no depende de querer turnos extra.
      const propios = await api.post(API_ENDPOINTS.VACANTES.MIS_PROXIMOS_TURNOS, { dias: 14 });
      setMisTurnos(Array.isArray(propios.data?.turnos) ? propios.data.turnos : []);
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'No se pudieron cargar los turnos disponibles');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [insCode]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const postular = async (vacante: Vacante) => {
    setEnviando(vacante.tv_id);
    try {
      const { data } = await api.post(API_ENDPOINTS.VACANTES.POSTULAR, {
        tv_id: vacante.tv_id,
        // La hora real de la postulación la manda el dispositivo, igual que en
        // los marcajes: si se envía sin señal y sincroniza después, vale la hora
        // en que el guardia se ofreció, no la que llegó al servidor.
        ocurrido_en: new Date().toISOString().slice(0, 19).replace('T', ' '),
        client_uuid: `${vacante.tv_id}-${Date.now()}`,
      });

      if (data?.success) {
        Alert.alert('Listo', data.message || 'Su postulación fue registrada.');
      } else {
        Alert.alert('No se pudo', data?.message || 'Ese turno ya no está disponible.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'No se pudo enviar la postulación');
    } finally {
      setEnviando(null);
      cargar();
    }
  };

  const retirar = async (vacante: Vacante) => {
    setEnviando(vacante.tv_id);
    try {
      await api.post(API_ENDPOINTS.VACANTES.RETIRAR, { tv_id: vacante.tv_id });
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'No se pudo retirar la postulación');
    } finally {
      setEnviando(null);
      cargar();
    }
  };

  const avisarAusencia = async (turno: TurnoProximo, motivo: string) => {
    setEnviando(turno.tu_id);
    try {
      const { data } = await api.post(API_ENDPOINTS.VACANTES.AVISAR_AUSENCIA, {
        tu_id: turno.tu_id,
        motivo,
        ocurrido_en: new Date().toISOString().slice(0, 19).replace('T', ' '),
        client_uuid: `aviso-${turno.tu_id}-${Date.now()}`,
      });

      Alert.alert(
        data?.success ? 'Aviso enviado' : 'No se pudo',
        data?.message || 'Intente de nuevo.'
      );
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || 'No se pudo enviar el aviso');
    } finally {
      setEnviando(null);
      cargar();
    }
  };

  const preguntarMotivo = (turno: TurnoProximo) => {
    Alert.alert(
      'No podré cubrir este turno',
      `${turno.puesto || 'Puesto'} · ${turno.fecha}\n${turno.hora_inicio} a ${turno.hora_fin}\n\nSu supervisor buscará quién lo cubra.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Enfermedad', onPress: () => avisarAusencia(turno, 'enfermedad') },
        { text: 'Permiso', onPress: () => avisarAusencia(turno, 'permiso') },
        { text: 'Otro motivo', onPress: () => avisarAusencia(turno, 'aviso') },
      ]
    );
  };

  const confirmarPostulacion = (vacante: Vacante) => {
    if (vacante.postulado) {
      Alert.alert('Retirar postulación', '¿Ya no puede cubrir este turno?', [
        { text: 'No', style: 'cancel' },
        { text: 'Retirar', style: 'destructive', onPress: () => retirar(vacante) },
      ]);
      return;
    }

    Alert.alert(
      'Postularse al turno',
      `${vacante.puesto || 'Puesto'} · ${vacante.fecha}\n${vacante.hora_inicio} a ${vacante.hora_fin}\n\nEl supervisor confirmará quién lo cubre.`,
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Me postulo', onPress: () => postular(vacante) },
      ]
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Turnos disponibles</Text>
      </View>

      <View style={styles.tabs}>
        <TouchableOpacity
          style={[styles.tab, vista === 'disponibles' && styles.tabActiva]}
          onPress={() => setVista('disponibles')}
        >
          <Text style={[styles.tabText, vista === 'disponibles' && styles.tabTextActiva]}>
            Por cubrir
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, vista === 'mis-turnos' && styles.tabActiva]}
          onPress={() => setVista('mis-turnos')}
        >
          <Text style={[styles.tabText, vista === 'mis-turnos' && styles.tabTextActiva]}>
            Mis turnos
          </Text>
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
        </View>
      ) : vista === 'mis-turnos' ? (
        <FlatList
          data={misTurnos}
          keyExtractor={(item) => String(item.tu_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>No tiene turnos programados próximamente.</Text>
          }
          renderItem={({ item }) => (
            <View style={styles.item}>
              <Text style={styles.itemPuesto}>{item.puesto || 'Sin puesto asignado'}</Text>
              <Text style={styles.itemLocal}>{item.local || 'Local'}</Text>
              <Text style={styles.itemHorario}>
                {item.fecha} · {item.hora_inicio} a {item.hora_fin}
              </Text>

              {item.avisado ? (
                <Text style={styles.itemAvisado}>
                  Ya avisó que no podrá cubrirlo. Su supervisor está buscando reemplazo.
                </Text>
              ) : (
                <TouchableOpacity
                  style={[styles.btn, styles.btnAvisar]}
                  onPress={() => preguntarMotivo(item)}
                  disabled={enviando === item.tu_id}
                >
                  {enviando === item.tu_id ? (
                    <ActivityIndicator color="#fff" />
                  ) : (
                    <Text style={styles.btnText}>No podré cubrirlo</Text>
                  )}
                </TouchableOpacity>
              )}
            </View>
          )}
        />
      ) : !aceptaExtras ? (
        <View style={styles.center}>
          <Text style={styles.emptyText}>
            {mensaje || 'Active "quiero cubrir turnos extra" en su perfil.'}
          </Text>
          <TouchableOpacity style={styles.linkBtn} onPress={() => navigation.navigate('Perfil')}>
            <Text style={styles.linkText}>Ir a mi perfil</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={vacantes}
          keyExtractor={(item) => String(item.tv_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>
              No hay turnos por cubrir en este momento.
            </Text>
          }
          renderItem={({ item }) => (
            <View style={[styles.item, item.postulado && styles.itemPostulado]}>
              <View style={styles.itemHeader}>
                <Text style={styles.itemPuesto}>{item.puesto || 'Sin puesto asignado'}</Text>
                {item.postulado ? (
                  <View style={styles.badgeOk}>
                    <Text style={styles.badgeOkText}>POSTULADO</Text>
                  </View>
                ) : null}
              </View>

              <Text style={styles.itemLocal}>{item.local || 'Local'}</Text>
              {!item.es_de_mi_local ? (
                <Text style={styles.itemAviso}>Es otro local de su ciudad</Text>
              ) : null}

              <Text style={styles.itemHorario}>
                {item.fecha} · {item.hora_inicio} a {item.hora_fin}
              </Text>
              {item.motivo ? <Text style={styles.itemMotivo}>{item.motivo}</Text> : null}

              <TouchableOpacity
                style={[styles.btn, item.postulado ? styles.btnRetirar : styles.btnPostular]}
                onPress={() => confirmarPostulacion(item)}
                disabled={enviando === item.tv_id}
              >
                {enviando === item.tv_id ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.btnText}>
                    {item.postulado ? 'Retirar postulación' : 'Me postulo'}
                  </Text>
                )}
              </TouchableOpacity>
            </View>
          )}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 30 },
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
  tabs: { flexDirection: 'row', borderBottomWidth: 1, borderBottomColor: '#eee' },
  tab: { flex: 1, paddingVertical: 12, alignItems: 'center', borderBottomWidth: 3, borderBottomColor: 'transparent' },
  tabActiva: { borderBottomColor: '#007AFF' },
  tabText: { fontSize: 15, color: '#777', fontWeight: '600' },
  tabTextActiva: { color: '#007AFF' },
  itemAvisado: { fontSize: 13, color: '#b26a00', marginTop: 10, fontWeight: '600' },
  btnAvisar: { backgroundColor: '#d9534f' },
  list: { padding: 20, paddingBottom: 30 },
  item: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 16,
    marginBottom: 12,
  },
  itemPostulado: { backgroundColor: '#eaf7ee' },
  itemHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  itemPuesto: { fontSize: 16, fontWeight: '700', color: '#333', flex: 1 },
  itemLocal: { fontSize: 14, color: '#555', marginTop: 2 },
  itemAviso: { fontSize: 12, color: '#b26a00', marginTop: 2, fontWeight: '600' },
  itemHorario: { fontSize: 15, color: '#007AFF', marginTop: 8, fontWeight: '700' },
  itemMotivo: { fontSize: 13, color: '#777', marginTop: 2 },
  badgeOk: { backgroundColor: '#28a745', borderRadius: 10, paddingHorizontal: 8, paddingVertical: 3 },
  badgeOkText: { fontSize: 11, fontWeight: '700', color: '#fff' },
  btn: { borderRadius: 8, paddingVertical: 12, alignItems: 'center', marginTop: 14 },
  btnPostular: { backgroundColor: '#007AFF' },
  btnRetirar: { backgroundColor: '#8e8e93' },
  btnText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  emptyText: { textAlign: 'center', color: '#999', marginTop: 40, fontSize: 16 },
  linkBtn: { marginTop: 16 },
  linkText: { color: '#007AFF', fontSize: 16, fontWeight: '600' },
});

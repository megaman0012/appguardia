import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  Image,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { CameraCapture } from '../components/CameraCapture';
import { Encabezado } from '../components/Encabezado';
import { ahoraDelDispositivo, useIdempotencia } from '../utils/idempotencia';
import { COLORES } from '../utils/tema';

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
  const { uuidPara, confirmar } = useIdempotencia();

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
      // Acá el GPS SÍ es obligatorio, al contrario que en una alerta: el
      // servidor compara la ubicación con el punto de marcación del local para
      // decidir si el guardia está en su puesto, y sin coordenada no hay nada
      // que comparar. Lo que se mejora es el aviso: antes decía «No se pudo
      // obtener la ubicación» y no sugería qué hacer.
      let coords = { lat: '0', lng: '0' };
      try {
        coords = await getCurrentLocation();
      } catch (e: any) {
        Alert.alert(
          'Sin ubicación',
          'Active el GPS y salga al aire libre unos segundos antes de marcar.\n\n' +
            (e?.message || '')
        );
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
      // El endpoint acepta los dos desde la Fase 7 y la app no los mandaba: un
      // reintento subía la foto otra vez y creaba un segundo marcaje. El uuid
      // se suelta sólo cuando el servidor confirma.
      formData.append('client_uuid', uuidPara(isEntrada ? 'entrada' : 'salida'));
      formData.append('ocurrido_en', ahoraDelDispositivo());

      const response = await api.post(API_ENDPOINTS.BIOMETRIA, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = response.data;
      if (data && data.message) {
        confirmar(isEntrada ? 'entrada' : 'salida');
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
      <Encabezado titulo="Marcación biométrica" onVolver={() => navigation.goBack()} />

      {/* Con la tarjeta del turno, el selector, la cámara y el botón, en una
          pantalla chica el botón de registrar quedaba fuera de vista. */}
      <ScrollView contentContainerStyle={styles.scroll}>

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

      {/*
        La foto que se va a subir, visible.

        Antes la cámara guardaba la imagen en el estado y **no se mostraba en
        ningún lado**: el único cambio era que el botón pasaba a decir «Cambiar
        foto». El guardia enviaba su marcación a ciegas, sin saber si había
        salido movida, oscura o con el dedo encima del lente -- y esa foto es la
        prueba de que estuvo en su puesto.
      */}
      {photo && !showCamera ? (
        <View style={styles.previewWrap}>
          <Text style={styles.previewTitulo}>Foto de la marcación</Text>
          <Image source={{ uri: photo.uri }} style={styles.preview} resizeMode="cover" />
          <View style={styles.previewBotones}>
            <TouchableOpacity style={styles.previewBoton} onPress={() => setShowCamera(true)}>
              <Text style={styles.previewBotonTexto}>Tomar otra</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.previewBoton, styles.previewDescartar]}
              onPress={() => setPhoto(null)}
            >
              <Text style={styles.previewDescartarTexto}>Descartar</Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : null}

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
        style={[styles.saveButton, (enviando || !photo) && styles.saveButtonOff]}
        onPress={enviar}
        disabled={enviando || !photo}
      >
        {enviando ? (
          <ActivityIndicator color={COLORES.textoSobreMarca} />
        ) : (
          <Text style={styles.saveButtonText}>
            {photo
              ? `Registrar ${isEntrada ? 'entrada' : 'salida'}`
              : 'Tome la foto para continuar'}
          </Text>
        )}
      </TouchableOpacity>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  scroll: {
    paddingBottom: 32,
  },
  previewWrap: {
    marginHorizontal: 20,
    marginBottom: 16,
  },
  previewTitulo: {
    fontSize: 13,
    fontWeight: '600',
    color: COLORES.textoSuave,
    marginBottom: 8,
  },
  preview: {
    width: '100%',
    // Alto fijo y `cover`: la cámara devuelve la foto en la orientación del
    // dispositivo y sin esto el recuadro cambia de tamaño según cómo se sostuvo
    // la tablet.
    height: 260,
    borderRadius: 12,
    backgroundColor: COLORES.fondoSuave,
    borderWidth: 1,
    borderColor: COLORES.borde,
  },
  previewBotones: {
    flexDirection: 'row',
    marginTop: 10,
  },
  previewBoton: {
    flex: 1,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: COLORES.borde,
    backgroundColor: COLORES.fondoSuave,
    paddingVertical: 12,
    alignItems: 'center',
    marginRight: 10,
  },
  previewBotonTexto: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORES.texto,
  },
  previewDescartar: {
    marginRight: 0,
    borderColor: COLORES.critico,
  },
  previewDescartarTexto: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORES.critico,
  },
  saveButtonOff: {
    opacity: 0.5,
  },
  turnoCard: {
    backgroundColor: COLORES.fondoSuave,
    borderRadius: 10,
    padding: 14,
    marginHorizontal: 20,
    marginBottom: 16,
  },
  turnoTitulo: {
    fontSize: 13,
    color: COLORES.textoSuave,
    fontWeight: '600',
    marginBottom: 4,
  },
  turnoPuesto: {
    fontSize: 17,
    fontWeight: 'bold',
    color: COLORES.texto,
  },
  turnoHorario: {
    fontSize: 15,
    color: COLORES.texto,
    marginTop: 2,
  },
  turnoEstado: {
    fontSize: 13,
    color: COLORES.textoSuave,
    marginTop: 6,
  },
  turnoTardanza: {
    fontSize: 13,
    color: COLORES.advertencia,
    marginTop: 2,
    fontWeight: '600',
  },
  container: { flex: 1, backgroundColor: COLORES.fondo },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: COLORES.borde,
  },
  backBtn: { marginRight: 12 },
  backText: { fontSize: 16, color: COLORES.marca },
  title: { fontSize: 20, fontWeight: 'bold', color: COLORES.texto },
  toggleRow: {
    flexDirection: 'row',
    marginHorizontal: 20,
    marginTop: 20,
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: COLORES.marca,
  },
  toggle: { flex: 1, paddingVertical: 12, alignItems: 'center', backgroundColor: COLORES.fondo },
  toggleOn: { backgroundColor: COLORES.marca },
  toggleText: { color: COLORES.marca, fontWeight: '600' },
  toggleTextOn: { color: COLORES.textoSobreMarca, fontWeight: '600' },
  cameraButton: {
    backgroundColor: COLORES.marcaGris,
    marginHorizontal: 20,
    marginTop: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  cameraButtonText: { color: COLORES.textoSobreMarca, fontSize: 16, fontWeight: '600' },
  cameraWrap: {
    height: 400,
    margin: 20,
    borderRadius: 8,
    overflow: 'hidden',
  },
  saveButton: {
    backgroundColor: COLORES.exito,
    marginHorizontal: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonText: { color: COLORES.textoSobreMarca, fontSize: 16, fontWeight: '600' },
});

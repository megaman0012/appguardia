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
  TextInput,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { mensajeDeError, mensajeDeExcepcion } from '../utils/errores';
import { formatDateTime } from '../utils/format';
import { COLORES } from '../utils/tema';

interface Novedad {
  nv_id: number;
  nv_fecha_hora: string;
  nv_observacion: string;
  nv_foto: string | null;
  nv_lat: string;
  nv_lng: string;
  nv_autor?: string | null;
  nv_usu_id?: number;
}

/** Rangos que se ofrecen en la pantalla. */
const RANGOS = [
  { dias: 1, etiqueta: 'Hoy' },
  { dias: 7, etiqueta: '7 días' },
  { dias: 30, etiqueta: '30 días' },
];

export const NovedadListScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();
  const [novedades, setNovedades] = useState<Novedad[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [dias, setDias] = useState(1);
  const [soloMias, setSoloMias] = useState(true);
  const [busqueda, setBusqueda] = useState('');
  /*
   * Cuál está abierta. La lista mostraba la observación recortada y la foto en
   * miniatura, sin forma de ver el registro completo: el guardia no podía leer
   * lo que él mismo había escrito si pasaba de dos renglones.
   */
  const [abierta, setAbierta] = useState<number | null>(null);

  const insCode = institucion?.ins_code;

  const cargar = useCallback(async () => {
    if (insCode === undefined) return;
    const d = new Date();
    const fecha = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
      d.getDate()
    ).padStart(2, '0')}`;
    try {
      setAbierta(null);
      const response = await api.post(API_ENDPOINTS.NOVEDAD.LIST_BY_DATE, {
        date: fecha,
        ins_code: insCode,
        // `dias` cuenta hacia atrás desde hoy. El guardia sólo veía las de hoy,
        // así que al recibir el puesto no había forma de leer lo del turno
        // anterior.
        dias,
        alcance: soloMias ? 'propias' : 'local',
      });
      const data = response.data;
      if (data && Array.isArray(data.nvNovedad)) {
        setNovedades(data.nvNovedad);
      }
    } catch (error: any) {
      Alert.alert('Error', mensajeDeExcepcion(error, 'Error al cargar novedades'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [insCode, dias, soloMias]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  /*
   * El buscador filtra lo que ya está en pantalla, sin volver al servidor:
   * responde mientras se escribe y funciona igual sin señal.
   */
  const normalizar = (s: string) =>
    s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  const texto = normalizar(busqueda.trim());
  const listaFiltrada =
    texto === ''
      ? novedades
      : novedades.filter(
          (n) =>
            normalizar(n.nv_observacion || '').includes(texto) ||
            normalizar(n.nv_autor || '').includes(texto)
        );

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Bitácora</Text>
      </View>

      <View style={styles.filtros}>
        <TextInput
          style={styles.buscador}
          placeholder="Buscar en los registros"
          value={busqueda}
          onChangeText={setBusqueda}
          autoCapitalize="none"
          autoCorrect={false}
          clearButtonMode="while-editing"
        />

        <View style={styles.filtroFila}>
          {RANGOS.map((r) => (
            <TouchableOpacity
              key={r.dias}
              style={[styles.chip, dias === r.dias && styles.chipActivo]}
              onPress={() => setDias(r.dias)}
            >
              <Text style={[styles.chipTexto, dias === r.dias && styles.chipTextoActivo]}>
                {r.etiqueta}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <View style={styles.filtroFila}>
          <TouchableOpacity
            style={[styles.chip, soloMias && styles.chipActivo]}
            onPress={() => setSoloMias(true)}
          >
            <Text style={[styles.chipTexto, soloMias && styles.chipTextoActivo]}>Mías</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.chip, !soloMias && styles.chipActivo]}
            onPress={() => setSoloMias(false)}
          >
            <Text style={[styles.chipTexto, !soloMias && styles.chipTextoActivo]}>
              Del puesto
            </Text>
          </TouchableOpacity>
        </View>
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
          data={listaFiltrada}
          keyExtractor={(item) => String(item.nv_id)}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); cargar(); }} />
          }
          ListEmptyComponent={
            <Text style={styles.emptyText}>
              {busqueda.trim() !== ''
                ? 'Nada coincide con la búsqueda'
                : dias === 1
                  ? 'No hay registros de hoy'
                  : 'No hay registros en el período'}
            </Text>
          }
          renderItem={({ item }) => {
            const desplegada = abierta === item.nv_id;

            return (
              <TouchableOpacity
                style={styles.item}
                activeOpacity={0.75}
                onPress={() => setAbierta(desplegada ? null : item.nv_id)}
              >
                <Text style={styles.itemDate}>
                  {formatDateTime(item.nv_fecha_hora)}
                  {!soloMias && item.nv_autor ? `  ·  ${item.nv_autor}` : ''}
                </Text>

                <Text style={styles.itemObs} numberOfLines={desplegada ? undefined : 2}>
                  {item.nv_observacion}
                </Text>

                {item.nv_foto ? (
                  <Image
                    source={{ uri: item.nv_foto }}
                    style={desplegada ? styles.itemPhotoGrande : styles.itemPhoto}
                    resizeMode={desplegada ? 'contain' : 'cover'}
                  />
                ) : null}

                {desplegada ? (
                  <View style={styles.detalle}>
                    {item.nv_autor ? (
                      <Text style={styles.detalleLinea}>Registró: {item.nv_autor}</Text>
                    ) : null}
                    {item.nv_lat && item.nv_lng
                      && (Math.abs(Number(item.nv_lat)) > 0.0001
                        || Math.abs(Number(item.nv_lng)) > 0.0001) ? (
                      <Text style={styles.detalleLinea}>
                        Ubicación: {Number(item.nv_lat).toFixed(5)}, {Number(item.nv_lng).toFixed(5)}
                      </Text>
                    ) : (
                      <Text style={styles.detalleLinea}>Sin ubicación registrada</Text>
                    )}
                  </View>
                ) : (
                  <Text style={styles.verMas}>Toque para ver el detalle</Text>
                )}
              </TouchableOpacity>
            );
          }}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORES.fondo },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    paddingTop: 50,
    borderBottomWidth: 1,
    borderBottomColor: COLORES.borde,
  },
  backBtn: { marginRight: 12 },
  filtros: {
    paddingHorizontal: 16,
    paddingTop: 12,
  },
  filtroFila: {
    flexDirection: 'row',
    marginBottom: 8,
  },
  buscador: {
    backgroundColor: '#FFF',
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: COLORES.borde,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 9,
    fontSize: 15,
    color: COLORES.texto,
    marginBottom: 10,
  },
  itemPhotoGrande: {
    width: '100%',
    height: 260,
    borderRadius: 8,
    marginTop: 8,
    backgroundColor: '#EEE',
  },
  detalle: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: COLORES.borde,
  },
  detalleLinea: {
    fontSize: 12.5,
    color: COLORES.textoSuave,
    marginBottom: 2,
  },
  verMas: {
    marginTop: 6,
    fontSize: 12,
    color: COLORES.textoSuave,
    fontStyle: 'italic',
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 16,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: COLORES.borde,
    marginRight: 8,
    backgroundColor: '#FFF',
  },
  chipActivo: {
    backgroundColor: COLORES.marca,
    borderColor: COLORES.marca,
  },
  chipTexto: {
    fontSize: 14,
    color: COLORES.texto,
  },
  chipTextoActivo: {
    color: COLORES.textoSobreMarca,
    fontWeight: '600',
  },
  backText: { fontSize: 16, color: COLORES.marca },
  title: { fontSize: 20, fontWeight: 'bold', color: COLORES.texto },
  newButton: {
    backgroundColor: COLORES.marca,
    marginHorizontal: 20,
    marginTop: 16,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
  },
  newButtonText: { color: COLORES.textoSobreMarca, fontSize: 15, fontWeight: '600' },
  list: { paddingHorizontal: 20, paddingBottom: 30, paddingTop: 16 },
  item: {
    backgroundColor: COLORES.fondoSuave,
    borderRadius: 10,
    padding: 14,
    marginBottom: 12,
  },
  itemDate: { fontSize: 13, color: COLORES.marca, fontWeight: '600' },
  itemObs: { fontSize: 15, color: COLORES.texto, marginTop: 4 },
  itemPhoto: { width: '100%', height: 150, borderRadius: 8, marginTop: 10 },
  emptyText: { textAlign: 'center', color: COLORES.textoTenue, marginTop: 40, fontSize: 16 },
});

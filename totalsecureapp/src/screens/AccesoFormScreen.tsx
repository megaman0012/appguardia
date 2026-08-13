import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  StyleSheet,
  ScrollView,
  Image,
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';
import { getCurrentLocation } from '../utils/location';
import { CameraCapture } from '../components/CameraCapture';

const TIPOS = [
  { value: '1', label: 'Peatón' },
  { value: '2', label: 'Empleado' },
  { value: '3', label: 'Visitante' },
  { value: '4', label: 'Vehículo' },
];

export const AccesoFormScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();

  const [isEntrada, setIsEntrada] = useState(true);
  const [tipoAc, setTipoAc] = useState('1');
  const [identificacion, setIdentificacion] = useState('');
  const [nombres, setNombres] = useState('');
  const [apellidos, setApellidos] = useState('');
  const [isAcomp, setIsAcomp] = useState(false);
  const [nombAcomp, setNombAcomp] = useState('');
  const [patente, setPatente] = useState('');
  const [empresa, setEmpresa] = useState('');
  const [temperatura, setTemperatura] = useState('');
  const [nombreContacto, setNombreContacto] = useState('');
  const [observacion, setObservacion] = useState('');
  const [showCamera, setShowCamera] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string } | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (institucion?.ins_code === undefined) return;

    if (!identificacion.trim() || !nombres.trim() || !apellidos.trim()) {
      Alert.alert('Aviso', 'Complete identificación, nombres y apellidos');
      return;
    }
    if (tipoAc === '4' && !patente.trim()) {
      Alert.alert('Aviso', 'Ingrese la patente del vehículo');
      return;
    }
    if (isAcomp && !nombAcomp.trim()) {
      Alert.alert('Aviso', 'Ingrese el nombre del acompañante');
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
      formData.append('tipoAc', tipoAc);
      formData.append('isEntrada', isEntrada ? 'true' : 'false');
      formData.append('identificacion', identificacion.trim());
      formData.append('nombres', nombres.trim());
      formData.append('apellidos', apellidos.trim());
      formData.append('latitud', coords.lat);
      formData.append('longitud', coords.lng);
      formData.append('isAcomp', isAcomp ? 'true' : 'false');
      if (isAcomp) formData.append('nombAcomp', nombAcomp.trim());
      formData.append('isBici', 'false');
      formData.append('isSello', 'false');
      formData.append('isNeumaticos', 'false');
      formData.append('isCarro', 'false');
      formData.append('isPtaConLlave', 'false');
      if (tipoAc === '4') formData.append('patente', patente.trim());
      if (empresa) formData.append('empresa', empresa.trim());
      if (temperatura) formData.append('temperatura', temperatura.trim());
      if (nombreContacto) formData.append('nombreContacto', nombreContacto.trim());
      if (observacion) formData.append('observacion', observacion.trim());
      if (photo) {
        formData.append('file', {
          uri: photo.uri,
          name: 'foto.jpg',
          type: 'image/jpeg',
        } as any);
      }

      const response = await api.post(API_ENDPOINTS.ACCESO.REGISTRAR, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = response.data;
      if (data && data.message) {
        Alert.alert('Éxito', data.message, [{ text: 'OK', onPress: () => navigation.goBack() }]);
      } else if (data && data.errors) {
        Alert.alert('Error', String(Object.values(data.errors)[0]));
      } else {
        Alert.alert('Error', data?.message || 'No se pudo registrar el acceso');
      }
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Error al registrar el acceso';
      Alert.alert('Error', msg);
    } finally {
      setEnviando(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backText}>‹ Volver</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Registrar acceso</Text>
      </View>

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

      <Text style={styles.label}>Tipo de acceso</Text>
      <View style={styles.tiposRow}>
        {TIPOS.map((t) => (
          <TouchableOpacity
            key={t.value}
            style={[styles.tipoChip, tipoAc === t.value ? styles.tipoChipOn : null]}
            onPress={() => setTipoAc(t.value)}
          >
            <Text style={tipoAc === t.value ? styles.tipoChipTextOn : styles.tipoChipText}>
              {t.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      <TextInput
        placeholder="Identificación"
        value={identificacion}
        onChangeText={setIdentificacion}
        style={styles.input}
        keyboardType="numeric"
      />
      <TextInput
        placeholder="Nombres"
        value={nombres}
        onChangeText={setNombres}
        style={styles.input}
      />
      <TextInput
        placeholder="Apellidos"
        value={apellidos}
        onChangeText={setApellidos}
        style={styles.input}
      />

      {tipoAc === '4' && (
        <TextInput
          placeholder="Patente"
          value={patente}
          onChangeText={setPatente}
          style={styles.input}
          autoCapitalize="characters"
        />
      )}

      <TouchableOpacity
        style={styles.checkRow}
        onPress={() => setIsAcomp(!isAcomp)}
      >
        <View style={[styles.checkbox, isAcomp ? styles.checkboxOn : null]}>
          {isAcomp ? <Text style={styles.checkMark}>✓</Text> : null}
        </View>
        <Text style={styles.checkLabel}>Con acompañante</Text>
      </TouchableOpacity>
      {isAcomp && (
        <TextInput
          placeholder="Nombre del acompañante"
          value={nombAcomp}
          onChangeText={setNombAcomp}
          style={styles.input}
        />
      )}

      <TextInput
        placeholder="Empresa"
        value={empresa}
        onChangeText={setEmpresa}
        style={styles.input}
      />
      <TextInput
        placeholder="Temperatura (°C)"
        value={temperatura}
        onChangeText={setTemperatura}
        style={styles.input}
        keyboardType="decimal-pad"
      />
      <TextInput
        placeholder="Nombre de contacto"
        value={nombreContacto}
        onChangeText={setNombreContacto}
        style={styles.input}
      />
      <TextInput
        placeholder="Observaciones"
        value={observacion}
        onChangeText={setObservacion}
        style={[styles.input, styles.textArea]}
        multiline
      />

      {photo && (
        <Image source={{ uri: photo.uri }} style={styles.photoPreview} resizeMode="cover" />
      )}

      {!showCamera ? (
        <TouchableOpacity
          style={styles.photoButton}
          onPress={() => setShowCamera(true)}
        >
          <Text style={styles.photoButtonText}>
            {photo ? 'Cambiar foto' : 'Agregar foto'}
          </Text>
        </TouchableOpacity>
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

      <TouchableOpacity
        style={styles.saveButton}
        onPress={enviar}
        disabled={enviando}
      >
        {enviando ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.saveButtonText}>
            Guardar {isEntrada ? 'entrada' : 'salida'}
          </Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  content: { paddingBottom: 40 },
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
    marginTop: 16,
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#007AFF',
  },
  toggle: { flex: 1, paddingVertical: 12, alignItems: 'center', backgroundColor: '#fff' },
  toggleOn: { backgroundColor: '#007AFF' },
  toggleText: { color: '#007AFF', fontWeight: '600' },
  toggleTextOn: { color: '#fff', fontWeight: '600' },
  label: {
    marginHorizontal: 20,
    marginTop: 16,
    marginBottom: 6,
    fontSize: 14,
    color: '#666',
    fontWeight: '500',
  },
  tiposRow: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 20 },
  tipoChip: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 16,
    paddingVertical: 7,
    paddingHorizontal: 14,
    marginRight: 8,
    marginBottom: 8,
  },
  tipoChipOn: { backgroundColor: '#007AFF', borderColor: '#007AFF' },
  tipoChipText: { color: '#333' },
  tipoChipTextOn: { color: '#fff', fontWeight: '600' },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    marginHorizontal: 20,
    marginTop: 12,
    fontSize: 15,
  },
  textArea: { minHeight: 80, textAlignVertical: 'top' },
  checkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 20,
    marginTop: 16,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: '#bbb',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  checkboxOn: { backgroundColor: '#007AFF', borderColor: '#007AFF' },
  checkMark: { color: '#fff', fontWeight: '700' },
  checkLabel: { fontSize: 15, color: '#333' },
  photoPreview: {
    width: '90%',
    height: 170,
    borderRadius: 8,
    marginHorizontal: 20,
    marginTop: 16,
  },
  photoButton: {
    backgroundColor: '#6c757d',
    marginHorizontal: 20,
    marginTop: 16,
    borderRadius: 8,
    paddingVertical: 13,
    alignItems: 'center',
  },
  photoButtonText: { color: '#fff', fontWeight: '600' },
  cameraWrap: { height: 340, marginHorizontal: 20, marginTop: 16, borderRadius: 8, overflow: 'hidden' },
  saveButton: {
    backgroundColor: '#28a745',
    marginHorizontal: 20,
    marginTop: 20,
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});

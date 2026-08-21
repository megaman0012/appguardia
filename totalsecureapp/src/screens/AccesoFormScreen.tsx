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
  { value: 'peatonal', label: 'Peatón' },
  { value: 'empleado', label: 'Empleado' },
  { value: 'visitante', label: 'Visitante' },
  { value: 'proveedor', label: 'Proveedor' },
  { value: 'vehicular', label: 'Vehículo' },
];

export const AccesoFormScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();

  const [isEntrada, setIsEntrada] = useState(true);
  const [tipoAc, setTipoAc] = useState('peatonal');
  const [identificacion, setIdentificacion] = useState('');
  const [nombres, setNombres] = useState('');
  const [apellidos, setApellidos] = useState('');
  const [isAcomp, setIsAcomp] = useState(false);
  const [nombAcomp, setNombAcomp] = useState('');
  const [temperatura, setTemperatura] = useState('');
  const [observacion, setObservacion] = useState('');

  // Seccion vehicular (obligatoria para vehicular, opcional para proveedor)
  const [patente, setPatente] = useState('');
  const [empresa, setEmpresa] = useState('');
  const [color, setColor] = useState('');
  const [marca, setMarca] = useState('');
  const [modelo, setModelo] = useState('');
  const [anio, setAnio] = useState('');
  const [kms, setKms] = useState('');
  const [isSello, setIsSello] = useState(false);
  const [isNeumaticos, setIsNeumaticos] = useState(false);
  const [isCarro, setIsCarro] = useState(false);
  const [isPtaConLlave, setIsPtaConLlave] = useState(false);

  // Seccion visita (visitante y proveedor)
  const [motivo, setMotivo] = useState('');
  const [areaVisita, setAreaVisita] = useState('');
  const [personaVisita, setPersonaVisita] = useState('');
  const [personasGrupo, setPersonasGrupo] = useState('1');
  const [duracionEstimada, setDuracionEstimada] = useState('');

  const [showCamera, setShowCamera] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string } | null>(null);
  const [enviando, setEnviando] = useState(false);

  const esVehicular = tipoAc === 'vehicular';
  const conVehiculo = esVehicular || tipoAc === 'proveedor';
  const conVisita = tipoAc === 'visitante' || tipoAc === 'proveedor';

  const enviar = async () => {
    if (institucion?.ins_code === undefined) return;

    if (!identificacion.trim() || !nombres.trim() || !apellidos.trim()) {
      Alert.alert('Aviso', 'Complete identificación, nombres y apellidos');
      return;
    }
    if (esVehicular && !patente.trim()) {
      Alert.alert('Aviso', 'Ingrese la patente del vehículo');
      return;
    }
    if (conVisita && !motivo.trim()) {
      Alert.alert('Aviso', 'Ingrese el motivo de la visita');
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
      if (temperatura) formData.append('temperatura', temperatura.trim());
      if (observacion) formData.append('observacion', observacion.trim());

      if (conVehiculo && patente.trim()) {
        formData.append('patente', patente.trim().toUpperCase());
        if (empresa) formData.append('empresa', empresa.trim());
        if (color) formData.append('color', color.trim());
        if (marca) formData.append('marca', marca.trim());
        if (modelo) formData.append('modelo', modelo.trim());
        if (anio) formData.append('anio', anio.trim());
        if (kms) formData.append('kms', kms.trim());
        formData.append('isSello', isSello ? 'true' : 'false');
        formData.append('isNeumaticos', isNeumaticos ? 'true' : 'false');
        formData.append('isCarro', isCarro ? 'true' : 'false');
        formData.append('isPtaConLlave', isPtaConLlave ? 'true' : 'false');
      }

      if (conVisita) {
        formData.append('motivo', motivo.trim());
        if (areaVisita) formData.append('areaVisita', areaVisita.trim());
        if (personaVisita) formData.append('personaVisita', personaVisita.trim());
        if (personasGrupo) formData.append('personasGrupo', personasGrupo.trim());
        if (duracionEstimada) formData.append('duracionEstimada', duracionEstimada.trim());
        if (empresa) formData.append('empresaOrigen', empresa.trim());
      }

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

  const Checkbox = ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value: boolean;
    onChange: (v: boolean) => void;
  }) => (
    <TouchableOpacity style={styles.checkRow} onPress={() => onChange(!value)}>
      <View style={[styles.checkbox, value ? styles.checkboxOn : null]}>
        {value ? <Text style={styles.checkMark}>✓</Text> : null}
      </View>
      <Text style={styles.checkLabel}>{label}</Text>
    </TouchableOpacity>
  );

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

      <Checkbox label="Con acompañante" value={isAcomp} onChange={setIsAcomp} />
      {isAcomp && (
        <TextInput
          placeholder="Nombre del acompañante"
          value={nombAcomp}
          onChangeText={setNombAcomp}
          style={styles.input}
        />
      )}

      {conVehiculo && (
        <>
          <Text style={styles.sectionTitle}>Vehículo{esVehicular ? '' : ' (opcional)'}</Text>
          <TextInput
            placeholder={esVehicular ? 'Patente *' : 'Patente'}
            value={patente}
            onChangeText={setPatente}
            style={styles.input}
            autoCapitalize="characters"
          />
          <TextInput
            placeholder="Empresa de transporte"
            value={empresa}
            onChangeText={setEmpresa}
            style={styles.input}
          />
          <View style={styles.row2}>
            <TextInput
              placeholder="Color"
              value={color}
              onChangeText={setColor}
              style={[styles.input, styles.inputHalf]}
            />
            <TextInput
              placeholder="Marca"
              value={marca}
              onChangeText={setMarca}
              style={[styles.input, styles.inputHalf]}
            />
          </View>
          <View style={styles.row2}>
            <TextInput
              placeholder="Modelo"
              value={modelo}
              onChangeText={setModelo}
              style={[styles.input, styles.inputHalf]}
            />
            <TextInput
              placeholder="Año"
              value={anio}
              onChangeText={setAnio}
              style={[styles.input, styles.inputHalf]}
              keyboardType="numeric"
            />
          </View>
          <TextInput
            placeholder="Kilometraje"
            value={kms}
            onChangeText={setKms}
            style={styles.input}
            keyboardType="numeric"
          />
          <Checkbox label="Con sello" value={isSello} onChange={setIsSello} />
          <Checkbox label="Revisión de neumáticos" value={isNeumaticos} onChange={setIsNeumaticos} />
          <Checkbox label="Revisión de carrocería" value={isCarro} onChange={setIsCarro} />
          <Checkbox label="Puerta con llave" value={isPtaConLlave} onChange={setIsPtaConLlave} />
        </>
      )}

      {conVisita && (
        <>
          <Text style={styles.sectionTitle}>Visita</Text>
          <TextInput
            placeholder="Motivo *"
            value={motivo}
            onChangeText={setMotivo}
            style={styles.input}
          />
          <TextInput
            placeholder="Área a visitar"
            value={areaVisita}
            onChangeText={setAreaVisita}
            style={styles.input}
          />
          <TextInput
            placeholder="Persona que visita"
            value={personaVisita}
            onChangeText={setPersonaVisita}
            style={styles.input}
          />
          <View style={styles.row2}>
            <TextInput
              placeholder="Personas en grupo"
              value={personasGrupo}
              onChangeText={setPersonasGrupo}
              style={[styles.input, styles.inputHalf]}
              keyboardType="numeric"
            />
            <TextInput
              placeholder="Duración estimada (h)"
              value={duracionEstimada}
              onChangeText={setDuracionEstimada}
              style={[styles.input, styles.inputHalf]}
              keyboardType="decimal-pad"
            />
          </View>
        </>
      )}

      <Text style={styles.sectionTitle}>Datos generales</Text>
      <TextInput
        placeholder="Temperatura (°C)"
        value={temperatura}
        onChangeText={setTemperatura}
        style={styles.input}
        keyboardType="decimal-pad"
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
  sectionTitle: {
    marginHorizontal: 20,
    marginTop: 22,
    marginBottom: 2,
    fontSize: 15,
    fontWeight: '700',
    color: '#007AFF',
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
  row2: { flexDirection: 'row', marginHorizontal: 10 },
  inputHalf: { flex: 1, marginHorizontal: 10 },
  textArea: { minHeight: 80, textAlignVertical: 'top' },
  checkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 20,
    marginTop: 14,
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

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
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';

export const PreregistroFormScreen = ({ navigation }: { navigation: any }) => {
  const { institucion } = useAuth();

  const [identificacion, setIdentificacion] = useState('');
  const [nombres, setNombres] = useState('');
  const [apellidos, setApellidos] = useState('');
  const [fechaEstimada, setFechaEstimada] = useState(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
      d.getDate()
    ).padStart(2, '0')}`;
  });
  const [horaEstimada, setHoraEstimada] = useState('');
  const [motivo, setMotivo] = useState('');
  const [areaVisita, setAreaVisita] = useState('');
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (institucion?.ins_code === undefined) return;

    if (!identificacion.trim() || !nombres.trim() || !apellidos.trim()) {
      Alert.alert('Aviso', 'Complete identificación, nombres y apellidos');
      return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(fechaEstimada.trim())) {
      Alert.alert('Aviso', 'La fecha estimada debe tener formato AAAA-MM-DD');
      return;
    }

    setEnviando(true);
    try {
      const response = await api.post(API_ENDPOINTS.ACCESO.PREREGISTRO_CREATE, {
        institucion: institucion.ins_code,
        fechaEstimada: fechaEstimada.trim(),
        horaEstimada: horaEstimada.trim() || undefined,
        identificacion: identificacion.trim(),
        nombres: nombres.trim(),
        apellidos: apellidos.trim(),
        motivo: motivo.trim() || undefined,
        areaVisita: areaVisita.trim() || undefined,
      });
      const data = response.data;
      if (data && data.message) {
        Alert.alert('Éxito', data.message, [{ text: 'OK', onPress: () => navigation.goBack() }]);
      } else if (data && data.errors) {
        Alert.alert('Error', String(Object.values(data.errors)[0]));
      } else {
        Alert.alert('Error', 'No se pudo crear el pre-registro');
      }
    } catch (error: any) {
      const msg = error.response?.data?.message || 'Error al crear el pre-registro';
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
        <Text style={styles.title}>Pre-registro de visitante</Text>
      </View>

      <TextInput
        placeholder="Identificación *"
        value={identificacion}
        onChangeText={setIdentificacion}
        style={styles.input}
        keyboardType="numeric"
      />
      <TextInput
        placeholder="Nombres *"
        value={nombres}
        onChangeText={setNombres}
        style={styles.input}
      />
      <TextInput
        placeholder="Apellidos *"
        value={apellidos}
        onChangeText={setApellidos}
        style={styles.input}
      />
      <View style={styles.row2}>
        <TextInput
          placeholder="Fecha estimada (AAAA-MM-DD) *"
          value={fechaEstimada}
          onChangeText={setFechaEstimada}
          style={[styles.input, styles.inputHalf]}
          keyboardType="numeric"
        />
        <TextInput
          placeholder="Hora estimada (HH:MM)"
          value={horaEstimada}
          onChangeText={setHoraEstimada}
          style={[styles.input, styles.inputHalf]}
          keyboardType="numeric"
        />
      </View>
      <TextInput
        placeholder="Motivo de la visita"
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

      <TouchableOpacity style={styles.saveButton} onPress={enviar} disabled={enviando}>
        {enviando ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.saveButtonText}>Guardar pre-registro</Text>
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
  title: { fontSize: 18, fontWeight: 'bold', color: '#333' },
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

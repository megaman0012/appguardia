import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, ActivityIndicator, Alert, StyleSheet } from 'react-native';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import { API_ENDPOINTS } from '../utils/constants';

export const LoginScreen = ({ navigation }: { navigation: any }) => {
  const [identificacion, setIdentificacion] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();

  const extraerError = (data: any): string => {
    if (data && data.errors) {
      const errors = data.errors;
      if (typeof errors === 'object') {
        const first = Object.keys(errors)[0];
        if (first !== undefined && Array.isArray(errors[first])) {
          return errors[first][0] || 'Credenciales incorrectas';
        }
      }
    }
    return data && data.message ? data.message : 'Credenciales incorrectas';
  };

  const handleLogin = async () => {
    if (!identificacion.trim() || !password.trim()) {
      Alert.alert('Error', 'Por favor ingrese identificación y contraseña');
      return;
    }

    setLoading(true);
    try {
      const response = await api.post(API_ENDPOINTS.AUTH.LOGIN, {
        usu_cedula: identificacion.trim(),
        usu_password: password,
      });

      const data = response.data;

      if (data && data.access_token) {
        await login(data.access_token, {
          nombres: data.usuario?.usu_nombres,
          email: data.usuario?.usu_email,
          acc: data.usuario?.usu_acc,
          abilities: data.abilities,
        });
        navigation.navigate('SeleccionInstitucion');
      } else {
        Alert.alert('Error', extraerError(data));
      }
    } catch (error: any) {
      console.error('Login error:', error);
      if (error.response && error.response.data) {
        Alert.alert('Error', extraerError(error.response.data));
      } else {
        Alert.alert('Error', 'No se pudo conectar al servidor. Verifique su conexión y que el backend esté en ejecución.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.logoContainer}>
        <Text style={styles.appName}>Total Secure App</Text>
      </View>

      <View style={styles.formContainer}>
        <TextInput
          placeholder="Identificación"
          value={identificacion}
          onChangeText={setIdentificacion}
          style={styles.input}
          autoCapitalize="none"
          keyboardType="numeric"
        />
        <TextInput
          placeholder="Contraseña"
          value={password}
          onChangeText={setPassword}
          style={styles.input}
          secureTextEntry={true}
        />
        <TouchableOpacity
          onPress={handleLogin}
          style={styles.button}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator size="small" color="white" />
          ) : (
            <Text style={styles.buttonText}>Iniciar Sesión</Text>
          )}
        </TouchableOpacity>
        <TouchableOpacity
          onPress={() => navigation.navigate('PasswordResetRequest')}
          style={styles.forgotPassword}
        >
          <Text style={styles.forgotPasswordText}>¿Olvidó su contraseña?</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  logoContainer: {
    marginBottom: 30,
  },
  appName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  formContainer: {
    width: '100%',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
    fontSize: 16,
  },
  button: {
    backgroundColor: '#007AFF',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  forgotPassword: {
    marginTop: 20,
    alignItems: 'center',
  },
  forgotPasswordText: {
    color: '#666',
    fontSize: 14,
  },
});

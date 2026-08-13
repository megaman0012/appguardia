import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  registerPushToken,
  removeRegisteredPushToken,
  setupNotificationChannel,
} from '../services/notifications';

export interface Institucion {
  ins_code: number | string;
  ins_descripcion: string;
  ins_direccion?: string;
}

interface AuthContextType {
  user: any | null;
  token: string | null;
  institucion: Institucion | null;
  isLoading: boolean;
  login: (token: string, user: any) => Promise<void>;
  logout: () => Promise<void>;
  setInstitucion: (inst: Institucion | null) => Promise<void>;
}

const defaultValue: AuthContextType = {
  user: null,
  token: null,
  institucion: null,
  isLoading: true,
  login: async () => {},
  logout: async () => {},
  setInstitucion: async () => {},
};

const AuthContext = createContext<AuthContextType>(defaultValue);

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const [user, setUser] = useState<any | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [institucion, setInstitucionState] = useState<Institucion | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadUserData = async () => {
      try {
        const storedToken = await AsyncStorage.getItem('token');
        const storedUser = await AsyncStorage.getItem('user');
        const storedInst = await AsyncStorage.getItem('institucion');
        if (storedToken) setToken(storedToken);
        if (storedUser) setUser(JSON.parse(storedUser));
        if (storedInst) setInstitucionState(JSON.parse(storedInst));
      } catch (error) {
        console.error('Error loading user data from AsyncStorage:', error);
      } finally {
        setIsLoading(false);
      }
    };

    loadUserData();
  }, []);

  useEffect(() => {
    setupNotificationChannel().catch(() => {});
  }, []);

  useEffect(() => {
    const registerDevice = async () => {
      if (!token || !institucion) return;
      try {
        await registerPushToken(institucion.ins_code);
      } catch (error) {
        console.error('Error registrando push token:', error);
      }
    };

    registerDevice();
  }, [token, institucion]);

  const login = async (tk: string, us: any) => {
    try {
      await AsyncStorage.setItem('token', tk);
      await AsyncStorage.setItem('user', JSON.stringify(us));
      setToken(tk);
      setUser(us);
    } catch (error) {
      console.error('Error saving user data to AsyncStorage:', error);
      throw error;
    }
  };

  const logout = async () => {
    try {
      await removeRegisteredPushToken();
      await AsyncStorage.multiRemove(['token', 'user', 'institucion']);
      setToken(null);
      setUser(null);
      setInstitucionState(null);
    } catch (error) {
      console.error('Error clearing user data from AsyncStorage:', error);
      throw error;
    }
  };

  const setInstitucion = async (inst: Institucion | null) => {
    try {
      if (inst) {
        await AsyncStorage.setItem('institucion', JSON.stringify(inst));
      } else {
        await AsyncStorage.removeItem('institucion');
      }
      setInstitucionState(inst);
    } catch (error) {
      console.error('Error saving institucion to AsyncStorage:', error);
    }
  };

  return (
    <AuthContext.Provider
      value={{ user, token, institucion, isLoading, login, logout, setInstitucion }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

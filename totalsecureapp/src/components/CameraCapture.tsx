import React, { useRef, useState } from 'react';
import { View, Text, TouchableOpacity, Image, StyleSheet, ActivityIndicator } from 'react-native';
import { CameraView, useCameraPermissions, CameraCapturedPicture } from 'expo-camera';

interface Props {
  onCapture: (photo: CameraCapturedPicture) => void;
  onCancel: () => void;
  title?: string;
}

export const CameraCapture = ({ onCapture, onCancel, title = 'Tomar foto' }: Props) => {
  const [permission, requestPermission] = useCameraPermissions();
  const [photo, setPhoto] = useState<CameraCapturedPicture | null>(null);
  const [taking, setTaking] = useState(false);
  const cameraRef = useRef<CameraView | null>(null);

  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#007AFF" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.centerText}>Se requiere permiso de cámara</Text>
        <TouchableOpacity style={styles.button} onPress={requestPermission}>
          <Text style={styles.buttonText}>Permitir cámara</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.cancelButton} onPress={onCancel}>
          <Text style={styles.cancelText}>Cancelar</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const handleCapture = async () => {
    if (!cameraRef.current || taking) return;
    setTaking(true);
    try {
      const pic = await cameraRef.current.takePictureAsync({ base64: false });
      setPhoto(pic);
    } catch (e) {
      console.error('Error al capturar foto:', e);
    } finally {
      setTaking(false);
    }
  };

  return (
    <View style={styles.container}>
      {photo ? (
        <>
          <Image source={{ uri: photo.uri }} style={styles.preview} />
          <View style={styles.actions}>
            <TouchableOpacity style={styles.cancelButton} onPress={() => setPhoto(null)}>
              <Text style={styles.cancelText}>Repetir</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.button}
              onPress={() => onCapture(photo)}
            >
              <Text style={styles.buttonText}>Usar foto</Text>
            </TouchableOpacity>
          </View>
        </>
      ) : (
        <>
          <CameraView
            ref={cameraRef}
            style={styles.camera}
            facing="back"
          >
            <View style={styles.cameraTop}>
              <TouchableOpacity style={styles.cancelButton} onPress={onCancel}>
                <Text style={styles.cancelText}>Cancelar</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.cameraBottom}>
              <TouchableOpacity
                style={styles.shutter}
                onPress={handleCapture}
                disabled={taking}
              >
                {taking ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.shutterText}>{title}</Text>
                )}
              </TouchableOpacity>
            </View>
          </CameraView>
        </>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
  },
  camera: {
    flex: 1,
    justifyContent: 'space-between',
  },
  cameraTop: {
    padding: 20,
    paddingTop: 50,
  },
  cameraBottom: {
    padding: 30,
    alignItems: 'center',
  },
  shutter: {
    backgroundColor: '#007AFF',
    borderRadius: 50,
    paddingVertical: 14,
    paddingHorizontal: 40,
  },
  shutterText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  preview: {
    flex: 1,
    width: '100%',
  },
  actions: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    padding: 30,
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
    backgroundColor: '#000',
  },
  centerText: {
    color: '#fff',
    fontSize: 16,
    marginBottom: 20,
  },
  button: {
    backgroundColor: '#007AFF',
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  cancelButton: {
    backgroundColor: '#333',
    borderRadius: 8,
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  cancelText: {
    color: '#fff',
    fontSize: 16,
  },
});

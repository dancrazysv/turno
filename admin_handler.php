<?php
// --- LÍNEAS DE DEPURACIÓN (MANTENER ACTIVAS SÓLO PARA PRUEBAS) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------------------------------------

session_start();
require_once 'config.php';

// Asegurar que solo el administrador pueda acceder
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso de administración denegado.']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Acción no válida o faltan parámetros.'];

// Variables comunes (usadas en CRUD de Áreas)
$termino_ubicacion = $mysqli->real_escape_string($_POST['termino_ubicacion'] ?? 'Escritorio');

// --- LÓGICA DE GUARDADO DE ASIGNACIONES (Función Auxiliar) ---
function save_tramite_assignments($mysqli, $id_usuario, $tramites_array) {
    // 1. Eliminar asignaciones existentes
    $stmt_delete = $mysqli->prepare("DELETE FROM usuario_tramite_asignado WHERE id_usuario = ?");
    $stmt_delete->bind_param("i", $id_usuario);
    $stmt_delete->execute();
    
    // 2. Insertar nuevas asignaciones
    if (!empty($tramites_array)) {
        $values_sql = [];
        $bind_params = [];
        $bind_types = str_repeat("ii", count($tramites_array)); 
        
        foreach ($tramites_array as $id_tramite) {
            $id_tramite_int = (int)$id_tramite;
            if ($id_tramite_int > 0) {
                $values_sql[] = "(?, ?)";
                $bind_params[] = $id_usuario;
                $bind_params[] = $id_tramite_int;
            }
        }
        
        if (!empty($values_sql)) {
            $sql_insert = "INSERT INTO usuario_tramite_asignado (id_usuario, id_tramite) VALUES " . implode(", ", $values_sql);
            $stmt_insert = $mysqli->prepare($sql_insert);
            
            if ($stmt_insert) {
                 $stmt_insert->bind_param($bind_types, ...$bind_params);
                 $stmt_insert->execute();
            } else {
                throw new Exception("Error al preparar la inserción de trámites: " . $mysqli->error);
            }
        }
    }
}
// -------------------------------------------------------------

switch ($action) {
    
    // =======================================================
    // I. MANTENIMIENTO: RESETEO DIARIO DE TURNOS (CORREGIDO)
    // =======================================================
    case 'reset_daily_turns':
        try {
            $mysqli->begin_transaction();
            
            // 1. Resetear contadores en la tabla TIPOS_TRAMITE
            $sql_reset_tramites = "UPDATE tipos_tramite SET ultimo_turno_diario = 0";
            $result1 = $mysqli->query($sql_reset_tramites);
            
            if ($result1 === FALSE) {
                throw new Exception("Error SQL al resetear tipos_tramite: " . $mysqli->error);
            }
            
            // 2. CORRECCIÓN CLAVE: Marcar SOLO los turnos EN ESPERA o LLAMADOS como 'PERDIDO'.
            // Los turnos 'ATENDIDO' se dejan intactos para los reportes históricos.
            $sql_update_turnos = "UPDATE turnos SET estado = 'PERDIDO' WHERE estado IN ('LLAMADO', 'ESPERA')";
            $result2 = $mysqli->query($sql_update_turnos);

            if ($result2 === FALSE) {
                throw new Exception("Error SQL al actualizar turnos: " . $mysqli->error);
            }

            $mysqli->commit();
            $response = ['success' => true, 'message' => 'Contadores de turnos diarios reseteados. Colas activas limpiadas.'];

        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage()];
        }
        break;
        
    // =======================================================
    // II. CRUD ÁREAS 
    // =======================================================
    case 'create_area':
        $nombre_area = $mysqli->real_escape_string($_POST['nombre_area'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if (empty($nombre_area)) {
            $response['message'] = 'El nombre del Área es obligatorio.';
            break;
        }
        
        $sql = "INSERT INTO areas (nombre_area, termino_ubicacion, activo) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ssi", $nombre_area, $termino_ubicacion, $activo);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Área "' . $nombre_area . '" creada con éxito.'];
        } else {
            $response = ['success' => false, 'message' => 'Error al crear área: ' . $mysqli->error];
        }
        break;

    case 'get_area':
        $id_area = (int)($_POST['id_area'] ?? 0);
        if (!$id_area) break;
        
        $sql = "SELECT id_area, nombre_area, termino_ubicacion, activo FROM areas WHERE id_area = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_area);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $response = ['success' => true, 'area' => $result->fetch_assoc()];
        } else {
            $response = ['success' => false, 'message' => 'Área no encontrada.'];
        }
        break;

    case 'update_area':
        $id_area = (int)($_POST['id_area'] ?? 0);
        $nombre_area = $mysqli->real_escape_string($_POST['nombre_area'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (!$id_area || empty($nombre_area)) break;

        $sql = "UPDATE areas SET nombre_area = ?, termino_ubicacion = ?, activo = ? WHERE id_area = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ssii", $nombre_area, $termino_ubicacion, $activo, $id_area);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Área "' . $nombre_area . '" actualizada con éxito.'];
        } else {
            $response = ['success' => false, 'message' => 'Error al actualizar área: ' . $mysqli->error];
        }
        break;

    case 'delete_area':
        $id_area = (int)($_POST['id_area'] ?? 0);
        if (!$id_area) break;
        
        $sql = "DELETE FROM areas WHERE id_area = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_area);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Área eliminada con éxito. (Trámites y referencias eliminadas vía CASCADE)'];
            } else {
                $response = ['success' => false, 'message' => 'Área no encontrada.'];
            }
        } else {
            if ($mysqli->errno == 1451) {
                 $response = ['success' => false, 'message' => 'ERROR: No se puede eliminar el área.'];
            } else {
                 $response = ['success' => false, 'message' => 'Error al eliminar: ' . $mysqli->error];
            }
        }
        break;

    // =======================================================
    // III. CRUD USUARIOS (CON GESTIÓN DE ASIGNACIÓN DE TRÁMITES)
    // =======================================================
    case 'create_user':
        $nombre_completo = $mysqli->real_escape_string($_POST['nombre_completo'] ?? '');
        $username = $mysqli->real_escape_string($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $mysqli->real_escape_string($_POST['rol'] ?? 'empleado');
        $id_area_asignada = $_POST['id_area_asignada'] !== '' ? (int)($_POST['id_area_asignada']) : NULL;
        $escritorio_asignado = $_POST['escritorio_asignado'] !== '' ? (int)($_POST['escritorio_asignado']) : NULL;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $tramites_asignados = $_POST['tramites_asignados'] ?? [];

        if (empty($username) || empty($password)) {
            $response['message'] = 'Usuario y Contraseña son obligatorios.';
            break;
        }

        $mysqli->begin_transaction();
        
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($rol === 'admin') {
                $id_area_asignada = NULL;
                $escritorio_asignado = NULL;
            }

            $sql = "INSERT INTO usuarios (nombre_completo, username, password, rol, id_area_asignada, escritorio_asignado, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            
            $stmt->bind_param("sssssii", $nombre_completo, $username, $hashed_password, $rol, $id_area_asignada, $escritorio_asignado, $activo);

            if (!$stmt->execute()) {
                if ($mysqli->errno == 1062) {
                    throw new Exception("Error: El nombre de usuario ya está en uso.");
                } else {
                    throw new Exception("Error al crear usuario: " . $stmt->error);
                }
            }
            
            $new_user_id = $mysqli->insert_id;
            
            if ($rol === 'empleado') {
                save_tramite_assignments($mysqli, $new_user_id, $tramites_asignados);
            }

            $mysqli->commit();
            $response = ['success' => true, 'message' => 'Usuario "' . $username . '" creado con éxito.'];

        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage()];
        }
        break;

    case 'get_user':
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        if (!$id_usuario) break;

        $sql = "SELECT id_usuario, nombre_completo, username, rol, id_area_asignada, escritorio_asignado, activo FROM usuarios WHERE id_usuario = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user_data = $result->fetch_assoc();
            
            $sql_tramites = "SELECT id_tramite FROM usuario_tramite_asignado WHERE id_usuario = ?";
            $stmt_tramites = $mysqli->prepare($sql_tramites);
            $stmt_tramites->bind_param("i", $id_usuario);
            $stmt_tramites->execute();
            $tramites_result = $stmt_tramites->get_result();
            
            $assigned_tramites = [];
            while($row = $tramites_result->fetch_assoc()) {
                $assigned_tramites[] = $row['id_tramite'];
            }
            
            $user_data['tramites_asignados'] = $assigned_tramites;
            
            $response = ['success' => true, 'user' => $user_data];
        } else {
            $response = ['success' => false, 'message' => 'Usuario no encontrado.'];
        }
        break;

    case 'update_user':
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        $nombre_completo = $mysqli->real_escape_string($_POST['nombre_completo'] ?? '');
        $username = $mysqli->real_escape_string($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $mysqli->real_escape_string($_POST['rol'] ?? 'empleado');
        $id_area_asignada = $_POST['id_area_asignada'] !== '' ? (int)($_POST['id_area_asignada']) : NULL;
        $escritorio_asignado = $_POST['escritorio_asignado'] !== '' ? (int)($_POST['escritorio_asignado']) : NULL;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $tramites_asignados = $_POST['tramites_asignados'] ?? [];

        if (!$id_usuario || empty($username)) break;

        $mysqli->begin_transaction();

        try {
            $set_clause = "nombre_completo = ?, username = ?, rol = ?, activo = ?";
            $bind_params = [$nombre_completo, $username, $rol, $activo];
            $bind_types = "sssi";

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $set_clause .= ", password = ?";
                $bind_types = "ssssi"; 
                array_splice($bind_params, 2, 0, $hashed_password);
            }
            
            if ($rol === 'admin') {
                $id_area_asignada = NULL;
                $escritorio_asignado = NULL;
            }
            
            $set_clause .= ", id_area_asignada = ?, escritorio_asignado = ?";
            
            $final_bind_types = $bind_types . "ssi";
            $final_bind_params = array_merge($bind_params, [$id_area_asignada, $escritorio_asignado, $id_usuario]);


            $sql = "UPDATE usuarios SET {$set_clause} WHERE id_usuario = ?";
            $stmt = $mysqli->prepare($sql);
            
            $stmt->bind_param($final_bind_types, ...$final_bind_params);
            
            if (!$stmt->execute()) {
                if ($mysqli->errno == 1062) {
                    throw new Exception("Error: El nombre de usuario ya está en uso.");
                } else {
                    throw new Exception("Error al actualizar usuario: " . $stmt->error);
                }
            }

            if ($rol === 'empleado') {
                save_tramite_assignments($mysqli, $id_usuario, $tramites_asignados);
            } else {
                 save_tramite_assignments($mysqli, $id_usuario, []);
            }
            
            $mysqli->commit();
            $response = ['success' => true, 'message' => 'Usuario "' . $username . '" actualizado con éxito.'];

        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage()];
        }
        break;

    case 'delete_user':
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        if (!$id_usuario) break;

        $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_usuario);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Usuario eliminado con éxito. (Asignaciones eliminadas vía CASCADE)'];
            } else {
                $response = ['success' => false, 'message' => 'Usuario no encontrado.'];
            }
        } else {
             $response = ['success' => false, 'message' => 'Error al eliminar usuario: ' . $mysqli->error];
        }
        break;

    default:
        break;
}

// Finaliza la salida
header('Content-Type: application/json');
echo json_encode($response);
$mysqli->close();
<?php
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

// ===============================================
// Lógica de Trámites (Sub-colas)
// ===============================================

switch ($action) {
    
    // --- 1. CREAR TRÁMITE ---
    case 'create_tramite':
        $id_area = (int)($_POST['id_area'] ?? 0);
        $nombre_tramite = $mysqli->real_escape_string($_POST['nombre_tramite'] ?? '');
        $prefijo_letra = strtoupper($mysqli->real_escape_string($_POST['prefijo_letra'] ?? ''));
        $ultimo_turno_diario = (int)($_POST['ultimo_turno_diario'] ?? 0);
        $prioridad = (int)($_POST['prioridad'] ?? 0); // NUEVO CAMPO
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if (!$id_area || empty($nombre_tramite) || empty($prefijo_letra)) {
            $response['message'] = 'Faltan datos obligatorios (Área, Nombre o Prefijo).';
            break;
        }
        
        // Consulta SQL con el nuevo campo 'prioridad'
        $sql = "INSERT INTO tipos_tramite (id_area, nombre_tramite, prefijo_letra, ultimo_turno_diario, prioridad, activo) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        // Bind_param: i (area), s (nombre), s (prefijo), i (ultimo), i (prioridad), i (activo)
        $stmt->bind_param("issiii", $id_area, $nombre_tramite, $prefijo_letra, $ultimo_turno_diario, $prioridad, $activo);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Trámite "' . $nombre_tramite . '" creado con éxito.'];
        } else {
            if ($mysqli->errno == 1062) {
                 $response = ['success' => false, 'message' => 'Error: El prefijo de letra "' . $prefijo_letra . '" ya está en uso.'];
            } else {
                 $response = ['success' => false, 'message' => 'Error al crear trámite: ' . $mysqli->error];
            }
        }
        break;

    // --- 2. OBTENER TRÁMITE (Para edición) ---
    case 'get_tramite':
        $id_tramite = (int)($_POST['id_tramite'] ?? 0);
        if (!$id_tramite) break;
        
        // Consulta SELECT incluyendo el campo 'prioridad'
        $sql = "SELECT id_tramite, id_area, nombre_tramite, prefijo_letra, ultimo_turno_diario, prioridad, activo FROM tipos_tramite WHERE id_tramite = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_tramite);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $response = ['success' => true, 'tramite' => $result->fetch_assoc()];
        } else {
            $response = ['success' => false, 'message' => 'Trámite no encontrado.'];
        }
        break;

    // --- 3. ACTUALIZAR TRÁMITE ---
    case 'update_tramite':
        $id_tramite = (int)($_POST['id_tramite'] ?? 0);
        $id_area = (int)($_POST['id_area'] ?? 0);
        $nombre_tramite = $mysqli->real_escape_string($_POST['nombre_tramite'] ?? '');
        $prefijo_letra = strtoupper($mysqli->real_escape_string($_POST['prefijo_letra'] ?? ''));
        $ultimo_turno_diario = (int)($_POST['ultimo_turno_diario'] ?? 0);
        $prioridad = (int)($_POST['prioridad'] ?? 0); // NUEVO CAMPO
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (!$id_tramite || !$id_area || empty($nombre_tramite) || empty($prefijo_letra)) {
            $response['message'] = 'Faltan datos obligatorios para actualizar.';
            break;
        }

        // Consulta UPDATE incluyendo el nuevo campo 'prioridad'
        $sql = "UPDATE tipos_tramite SET id_area = ?, nombre_tramite = ?, prefijo_letra = ?, ultimo_turno_diario = ?, prioridad = ?, activo = ? WHERE id_tramite = ?";
        $stmt = $mysqli->prepare($sql);
        // Bind_param: i (area), s (nombre), s (prefijo), i (ultimo), i (prioridad), i (activo), i (id_tramite)
        $stmt->bind_param("issiiii", $id_area, $nombre_tramite, $prefijo_letra, $ultimo_turno_diario, $prioridad, $activo, $id_tramite);

        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Trámite "' . $nombre_tramite . '" actualizado con éxito.'];
        } else {
            if ($mysqli->errno == 1062) {
                 $response = ['success' => false, 'message' => 'Error: El prefijo de letra "' . $prefijo_letra . '" ya está en uso por otro trámite.'];
            } else {
                 $response = ['success' => false, 'message' => 'Error al actualizar trámite: ' . $mysqli->error];
            }
        }
        break;

    // --- 4. ELIMINAR TRÁMITE ---
    case 'delete_tramite':
        $id_tramite = (int)($_POST['id_tramite'] ?? 0);
        if (!$id_tramite) break;
        
        $sql = "DELETE FROM tipos_tramite WHERE id_tramite = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_tramite);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Trámite eliminado con éxito.'];
            } else {
                $response = ['success' => false, 'message' => 'Trámite no encontrado.'];
            }
        } else {
            if ($mysqli->errno == 1451) {
                 $response = ['success' => false, 'message' => 'ERROR: No se puede eliminar el trámite porque hay turnos asociados a él.'];
            } else {
                 $response = ['success' => false, 'message' => 'Error al eliminar: ' . $mysqli->error];
            }
        }
        break;

    default:
        break;
}

echo json_encode($response);
$mysqli->close();
?>
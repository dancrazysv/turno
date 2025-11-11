<?php
// --- LÍNEAS DE DEPURACIÓN (ACTIVAS) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------

session_start();
require_once 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'empleado') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

header('Content-Type: application/json');

$id_area_asignada = $_SESSION['id_area_asignada'] ?? 0;

if ($id_area_asignada == 0) {
    echo json_encode(['success' => true, 'queue' => []]); 
    exit;
}

// Consulta para obtener turnos en ESPERA del área asignada (Solo de HOY)
$sql = "
    SELECT 
        t.numero_completo, 
        tr.nombre_tramite, 
        t.hora_generacion
    FROM turnos t
    -- USAR LEFT JOIN PARA TOLERANCIA A FALLOS DE DATOS
    LEFT JOIN tipos_tramite tr ON t.id_tramite = tr.id_tramite 
    WHERE t.id_area = ? AND t.estado = 'ESPERA'
      AND DATE(t.hora_generacion) = CURDATE() 
    ORDER BY COALESCE(tr.prioridad, 0) DESC, t.hora_generacion ASC
    LIMIT 200
";

$queue_data = [];
$stmt = $mysqli->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id_area_asignada);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $queue_data[] = [
                'numero_completo' => $row['numero_completo'],
                'nombre_tramite' => $row['nombre_tramite'] ?? 'Trámite no encontrado', // Manejo de NULL
                'hora_generacion' => $row['hora_generacion']
            ];
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar SQL de cola: ' . $stmt->error]);
        $stmt->close();
        $mysqli->close();
        exit;
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'queue' => $queue_data]);

} else {
    echo json_encode(['success' => false, 'message' => 'Error al preparar SQL de cola: ' . $mysqli->error]);
}

$mysqli->close();
?>
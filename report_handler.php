<?php
session_start();
require_once 'config.php';

// Asegurar que solo el administrador pueda acceder
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Acción no válida o faltan parámetros.'];

if ($action === 'generate_report') {
    $filtro_tipo = $mysqli->real_escape_string($_POST['filtro_tipo'] ?? 'diario');
    $filtro_fecha = $mysqli->real_escape_string($_POST['filtro_fecha'] ?? date('Y-m-d', time()));
    $filtro_usuario = (int)($_POST['filtro_usuario'] ?? 0);
    $filtro_tramite = (int)($_POST['filtro_tramite'] ?? 0); 
    $filtro_estado = $mysqli->real_escape_string($_POST['filtro_estado'] ?? 'TODOS'); // NUEVO CAMPO
    
    // --- 1. Definición del Rango de Fechas ---
    $start_date = $filtro_fecha;
    $end_date = $filtro_fecha;

    if ($filtro_tipo === 'mensual') {
        $start_date = date('Y-m-01', strtotime($filtro_fecha));
        $end_date = date('Y-m-t', strtotime($filtro_fecha)); // Último día del mes
    } elseif ($filtro_tipo === 'diario') {
        $end_date = date('Y-m-d', strtotime($filtro_fecha));
    }
    
    if ($filtro_tipo === 'historico') {
        $date_condition = "";
    } else {
        $date_condition = "AND t.hora_generacion >= '{$start_date} 00:00:00' AND t.hora_generacion <= '{$end_date} 23:59:59'";
    }

    // --- 2. Construcción de la Consulta Base ---
    $sql = "
        SELECT 
            t.numero_completo, t.estado, t.escritorio_llamado, t.hora_generacion, t.hora_atencion_fin,
            a.nombre_area, a.termino_ubicacion,
            tr.nombre_tramite,
            u.nombre_completo AS empleado_nombre,
            
            /* Cálculo del Tiempo de Espera (Desde Generación hasta Llamada, en minutos) */
            (TIMESTAMPDIFF(SECOND, t.hora_generacion, t.hora_llamada) / 60) AS tiempo_espera_minutos
            
        FROM turnos t
        JOIN areas a ON t.id_area = a.id_area
        JOIN tipos_tramite tr ON t.id_tramite = tr.id_tramite
        LEFT JOIN usuarios u ON t.id_usuario_atendio = u.id_usuario 
        WHERE 1=1 
        {$date_condition}
    ";
    
    // --- 3. Aplicar Filtros Adicionales ---

    if ($filtro_usuario > 0) {
        $sql .= " AND t.id_usuario_atendio = {$filtro_usuario}";
    }
    
    if ($filtro_tramite > 0) {
        $sql .= " AND t.id_tramite = {$filtro_tramite}";
    }
    
    // APLICAR FILTRO POR ESTADO (NUEVO)
    if ($filtro_estado !== 'TODOS') {
        // Usamos real_escape_string para sanitizar el valor ENUM
        $estado_limpio = $mysqli->real_escape_string($filtro_estado);
        $sql .= " AND t.estado = '{$estado_limpio}'";
    }

    $sql .= " ORDER BY t.hora_generacion ASC"; 

    // --- 4. Ejecutar y Devolver Resultado ---
    $result = $mysqli->query($sql);
    $data = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
    } else {
        $response = ['success' => false, 'message' => 'Error al ejecutar la consulta de reportes: ' . $mysqli->error];
    }
}

echo json_encode($response);
$mysqli->close();
?>
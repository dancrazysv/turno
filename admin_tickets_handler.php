<?php
session_start();
// --- LÍNEAS DE DEPURACIÓN (MANTENER ACTIVAS SÓLO PARA PRUEBAS) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -------------------------------------------------------------------------

require_once 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["rol"] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'transfer_ticket') {
    $id_turno = (int)($_POST['id_turno'] ?? 0);
    $new_area_id = (int)($_POST['new_area_id'] ?? 0);
    $new_tramite_id = (int)($_POST['new_tramite_id'] ?? 0);

    if (!$id_turno || !$new_area_id || !$new_tramite_id) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos de ID de turno, área o trámite.']);
        exit;
    }

    try {
        $mysqli->begin_transaction();

        // 1. Obtener datos del Trámite de Destino (BLOQUEAR)
        $stmt_tramite = $mysqli->prepare("
            SELECT prefijo_letra, ultimo_turno_diario 
            FROM tipos_tramite 
            WHERE id_tramite = ? 
            FOR UPDATE
        ");
        $stmt_tramite->bind_param("i", $new_tramite_id);
        $stmt_tramite->execute();
        $tramite_data = $stmt_tramite->get_result()->fetch_assoc();
        
        // 2. Generar el NUEVO NÚMERO consecutivo para el Trámite de Destino
        $new_correlative = $tramite_data['ultimo_turno_diario'] + 1;
        $new_prefix = $tramite_data['prefijo_letra'];
        $new_numero_completo = $new_prefix . '-' . str_pad($new_correlative, 3, '0', STR_PAD_LEFT);
        
        // 3. Aumentar el contador del Trámite de Destino
        $stmt_update_tramite = $mysqli->prepare("
            UPDATE tipos_tramite 
            SET ultimo_turno_diario = ? 
            WHERE id_tramite = ?
        ");
        $stmt_update_tramite->bind_param("ii", $new_correlative, $new_tramite_id);
        if (!$stmt_update_tramite->execute()) {
            throw new Exception("Fallo al actualizar el contador del trámite: " . $stmt_update_tramite->error);
        }

        // 4. Obtener el número original para el mensaje de confirmación
        $stmt_select_old = $mysqli->prepare("SELECT numero_completo FROM turnos WHERE id_turno = ?");
        $stmt_select_old->bind_param("i", $id_turno);
        $stmt_select_old->execute();
        $old_numero_completo = $stmt_select_old->get_result()->fetch_assoc()['numero_completo'];

        // 5. Actualizar el registro del turno con el nuevo ID, número, y hora de prioridad
        $sql_update = "
            UPDATE turnos 
            SET id_area = ?, id_tramite = ?, 
                numero_correlativo = ?,
                numero_completo = ?, 
                escritorio_llamado = NULL, 
                id_usuario_atendio = NULL, 
                estado = 'ESPERA',
                hora_llamada = NULL,
                anunciado_display = FALSE,
                hora_generacion = ? 
            WHERE id_turno = ?
        ";
        
        // Usamos el inicio del día de hoy como hora de generación para la prioridad
        $prioridad_time = date('Y-m-d 00:00:01'); 
        
        $stmt_update = $mysqli->prepare($sql_update);
        // CADENA DE TIPOS CORREGIDA (iiissi): 
        // i: id_area, i: id_tramite, i: numero_correlativo, s: numero_completo, s: prioridad_time, i: id_turno
        $stmt_update->bind_param("iiissi", 
            $new_area_id, 
            $new_tramite_id, 
            $new_correlative, 
            $new_numero_completo, 
            $prioridad_time, 
            $id_turno
        );

        if (!$stmt_update->execute()) {
            throw new Exception("Fallo al actualizar el turno: " . $stmt_update->error);
        }

        $mysqli->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Ticket {$old_numero_completo} trasladado al nuevo número {$new_numero_completo} con éxito. Colocado al inicio de la cola."
        ]);

    } catch (Exception $e) {
        $mysqli->rollback();
        // Mensaje detallado para depuración
        echo json_encode(['success' => false, 'message' => 'Error de traslado (Transacción): ' . $e->getMessage()]);
    }
}
?>
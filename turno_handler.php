<?php
// --- LÍNEAS DE DEPURACIÓN (ACTIVAS) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------

session_start();
require_once 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Debe iniciar sesión.']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id_usuario = $_SESSION['id_usuario'];
$id_area_asignada = $_SESSION['id_area_asignada'] ?? 0;
$escritorio_asignado = $_SESSION['escritorio_asignado'] ?? 0;
$current_time = date("Y-m-d H:i:s");

$response = ['success' => false, 'message' => 'Acción no válida.'];

if ($id_area_asignada === 0 || $escritorio_asignado === 0) {
    if ($_SESSION['rol'] !== 'admin') {
         $response = ['success' => false, 'message' => 'Su usuario no tiene un Área o Escritorio asignado para llamar turnos.'];
         if ($action === 'call_next_turn' || $action === 'mark_as_attended' || $action === 're_call_turn' || $action === 'transfer_ticket') {
             echo json_encode($response);
             exit;
         }
    }
}

switch ($action) {
    
    // ---------------------------------------------
    // 1. LLAMAR SIGUIENTE TURNO (CORRECCIÓN CON LEFT JOIN)
    // ---------------------------------------------
    case 'call_next_turn':
        if ($id_area_asignada === 0 || $escritorio_asignado === 0) {
             $response = ['success' => false, 'message' => 'Su cuenta no está configurada correctamente para llamar turnos.'];
             break;
        }

        try {
            $mysqli->begin_transaction();

            // 1. Marcar el turno LLAMADO PREVIO (si está en este escritorio) como PERDIDO.
            $stmt = $mysqli->prepare("UPDATE turnos SET estado = 'PERDIDO' WHERE escritorio_llamado = ? AND id_area = ? AND estado = 'LLAMADO'");
            $stmt->bind_param("ii", $escritorio_asignado, $id_area_asignada);
            $stmt->execute();

            // 2. Determinar la restricción de trámites (Modo Exclusivo)
            $tramite_restriction_sql = "";
            $is_locked_out = false; 

            // 2.1 Obtener trámites asignados al usuario actual
            $stmt_restriction = $mysqli->prepare("SELECT id_tramite FROM usuario_tramite_asignado WHERE id_usuario = ?");
            $stmt_restriction->bind_param("i", $id_usuario);
            $stmt_restriction->execute();
            $result_restriction = $stmt_restriction->get_result();
            
            if ($result_restriction->num_rows > 0) {
                // --- CASE 1: USUARIO CON ASIGNACIONES ESPECÍFICAS ---
                $tramites_permitidos = [];
                while ($row = $result_restriction->fetch_assoc()) {
                    $tramites_permitidos[] = (int)$row['id_tramite']; 
                }
                
                if (!empty($tramites_permitidos)) {
                    $ids = implode(',', $tramites_permitidos);
                    $tramite_restriction_sql = " AND t.id_tramite IN ($ids) ";
                }
                
            } else {
                // --- CASE 2: USUARIO SIN ASIGNACIONES. APLICAR RESTRICCIÓN DE ÁREA ---
                $sql_area_check = "
                    SELECT COUNT(DISTINCT uta.id_usuario) as total_usuarios_asignados_en_area
                    FROM usuarios u
                    JOIN usuario_tramite_asignado uta ON u.id_usuario = uta.id_usuario
                    WHERE u.id_area_asignada = ? AND u.activo = 1
                ";
                $stmt_area_check = $mysqli->prepare($sql_area_check);
                $stmt_area_check->bind_param("i", $id_area_asignada);
                $stmt_area_check->execute();
                $area_check_result = $stmt_area_check->get_result()->fetch_assoc();

                if ($area_check_result['total_usuarios_asignados_en_area'] > 0) {
                    // MODO EXCLUSIVO ACTIVO. Bloquear al usuario NO ASIGNADO.
                    $tramite_restriction_sql = " AND 1 = 0 "; // Condición SQL que siempre falla
                    $is_locked_out = true; 
                } 
            } 

            // 2.3 Si está bloqueado y no tiene asignaciones, salir con mensaje.
            if ($is_locked_out) {
                $mysqli->commit(); 
                $response = [
                    'success' => false, 
                    'message' => 'El modo de asignación de trámites está activo en su área. Debe solicitar al administrador que le asigne prefijos específicos para poder llamar turnos.'
                ];
                break; 
            }

            // 3. Buscar el turno más antiguo en ESPERA (del día de HOY), aplicando la restricción de trámite y PRIORIDAD.
            $sql = "SELECT t.id_turno, t.numero_completo, tr.nombre_tramite 
                    FROM turnos t
                    LEFT JOIN tipos_tramite tr ON t.id_tramite = tr.id_tramite
                    WHERE t.id_area = ? AND t.estado = 'ESPERA' 
                    AND DATE(t.hora_generacion) = CURDATE() /* Filtro de fecha de HOY */
                    {$tramite_restriction_sql}
                    ORDER BY COALESCE(tr.prioridad, 0) DESC, t.hora_generacion ASC /* ORDEN: Prioridad Alta, luego FIFO */
                    LIMIT 1 FOR UPDATE";

            $stmt = $mysqli->prepare($sql);
            if (!$stmt) { throw new Exception("Error SQL (Paso 3): " . $mysqli->error); }
            
            $stmt->bind_param("i", $id_area_asignada);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $next_turn = $result->fetch_assoc();
                $id_turno = $next_turn['id_turno'];
                $numero_completo = $next_turn['numero_completo'];

                // 4. Actualizar el turno encontrado a estado LLAMADO, y marcar como NO anunciado
                $stmt = $mysqli->prepare("UPDATE turnos SET estado = 'LLAMADO', escritorio_llamado = ?, hora_llamada = ?, id_usuario_atendio = ?, anunciado_display = FALSE WHERE id_turno = ?");
                $stmt->bind_param("isii", $escritorio_asignado, $current_time, $id_usuario, $id_turno);

                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar el estado del turno: " . $stmt->error);
                }

                $mysqli->commit();
                $response = [
                    'success' => true, 
                    'message' => "Turno $numero_completo llamado con éxito.",
                    'numero_llamado' => $numero_completo,
                    'nombre_tramite' => $next_turn['nombre_tramite'] ?? 'Trámite no encontrado'
                ];
            } else {
                $mysqli->commit(); 
                $response = ['success' => false, 'message' => 'No hay turnos en espera disponibles para su asignación.'];
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage() . ' (SQL Error: ' . $mysqli->error . ')'];
        }
        break;

    // ---------------------------------------------
    // 2. ATENDER TURNO
    // ---------------------------------------------
    case 'mark_as_attended':
        $id_turno = (int)($_POST['id_turno'] ?? 0);
        if (!$id_turno) break;

        try {
            $mysqli->begin_transaction();
            $stmt = $mysqli->prepare("
                SELECT id_turno 
                FROM turnos 
                WHERE id_turno = ? 
                  AND id_area = ? 
                  AND estado = 'LLAMADO'
                  AND escritorio_llamado = ? 
                FOR UPDATE
            ");
            $stmt->bind_param("iii", $id_turno, $id_area_asignada, $escritorio_asignado);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmt = $mysqli->prepare("UPDATE turnos SET estado = 'ATENDIDO', hora_atencion_fin = ? WHERE id_turno = ?");
                $stmt->bind_param("si", $current_time, $id_turno);

                if (!$stmt->execute()) {
                    throw new Exception("Error al marcar como atendido: " . $stmt->error);
                }
                $mysqli->commit();
                $response = ['success' => true, 'message' => 'Turno marcado como atendido.'];
            } else {
                $mysqli->commit(); 
                $response = ['success' => false, 'message' => 'El turno no existe, no está en estado "Llamado" o no pertenece a este escritorio.'];
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage()];
        }
        break;

    // ---------------------------------------------
    // 3. RELLAMAR TURNO 
    // ---------------------------------------------
    case 're_call_turn':
        $id_turno = (int)($_POST['id_turno'] ?? 0);
        if (!$id_turno) break;
        
        try {
            $mysqli->begin_transaction();
            
            $stmt = $mysqli->prepare("
                SELECT id_turno 
                FROM turnos 
                WHERE id_turno = ? AND id_area = ? AND estado = 'LLAMADO' 
                AND escritorio_llamado = ? 
                FOR UPDATE
            ");
            $stmt->bind_param("iii", $id_turno, $id_area_asignada, $escritorio_asignado);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmt_update = $mysqli->prepare("
                    UPDATE turnos 
                    SET hora_llamada = ?, id_usuario_atendio = ?, anunciado_display = FALSE
                    WHERE id_turno = ?
                ");
                $stmt_update->bind_param("sii", $current_time, $id_usuario, $id_turno);
                
                if (!$stmt_update->execute()) {
                    throw new Exception("Error al re-llamar el turno: " . $stmt_update->error);
                }
                $mysqli->commit();
                $response = ['success' => true, 'message' => 'Turno re-llamado con éxito.'];
            } else {
                $mysqli->commit(); 
                $response = ['success' => false, 'message' => 'El turno no está asignado a este escritorio o ya fue atendido.'];
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage()];
        }
        break;
        
    // ---------------------------------------------
    // 4. TRASLADO DE TICKET (Función del Operador)
    // ---------------------------------------------
    case 'transfer_ticket':
        $id_turno = (int)($_POST['id_turno'] ?? 0);
        $new_area_id = (int)($_POST['new_area_id'] ?? 0);
        $new_tramite_id = (int)($_POST['new_tramite_id'] ?? 0);

        if (!$id_turno || !$new_area_id || !$new_tramite_id) {
            $response = ['success' => false, 'message' => 'Faltan datos de ID de turno, área o trámite.'];
            break;
        }

        try {
            $mysqli->begin_transaction();

            $stmt_select_old = $mysqli->prepare("SELECT numero_completo FROM turnos WHERE id_turno = ?");
            $stmt_select_old->bind_param("i", $id_turno);
            $stmt_select_old->execute();
            $old_numero_completo = $stmt_select_old->get_result()->fetch_assoc()['numero_completo'];

            $prioridad_time = date('Y-m-d H:i:s', time() - 1); 

            $sql_update = "
                UPDATE turnos 
                SET id_area = ?, id_tramite = ?, 
                    escritorio_llamado = NULL, 
                    id_usuario_atendio = NULL, 
                    estado = 'ESPERA',
                    hora_llamada = NULL,
                    anunciado_display = FALSE,
                    hora_generacion = ?
                WHERE id_turno = ?
            ";
            
            $stmt_update = $mysqli->prepare($sql_update);
            $stmt_update->bind_param("iisi", 
                $new_area_id, 
                $new_tramite_id, 
                $prioridad_time, 
                $id_turno
            );

            if (!$stmt_update->execute()) {
                throw new Exception("Fallo al actualizar el turno: " . $stmt_update->error);
            }

            $mysqli->commit();
            $response = [
                'success' => true, 
                'message' => "Ticket {$old_numero_completo} trasladado con éxito. Se le ha dado prioridad en la nueva área."
            ];

        } catch (Exception $e) {
            $mysqli->rollback();
            $response = ['success' => false, 'message' => 'Error de traslado (Transacción): ' . $e->getMessage()];
        }
        break;
        
    // ---------------------------------------------
    // 5. OBTENER TURNO ACTUAL (Para actualizar el panel)
    // ---------------------------------------------
    case 'get_current_turn':
        $sql = "SELECT id_turno, numero_completo 
                FROM turnos 
                WHERE id_area = ? AND escritorio_llamado = ? AND estado = 'LLAMADO'";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $id_area_asignada, $escritorio_asignado);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $response = ['success' => true, 'current_turn' => $row];
        } else {
            $response = ['success' => true, 'current_turn' => null];
        }
        break;
        
    // ---------------------------------------------
    // 6. OBTENER CONTEO DE ESPERA (Obsoleto, pero se mantiene por si acaso)
    // ---------------------------------------------
    case 'get_waiting_count':
        $sql = "SELECT COUNT(id_turno) as count FROM turnos WHERE id_area = ? AND estado = 'ESPERA'";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $id_area_asignada);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $response = ['success' => true, 'count' => $row['count']];
        break;

    default:
        break;
}

echo json_encode($response);
$mysqli->close();
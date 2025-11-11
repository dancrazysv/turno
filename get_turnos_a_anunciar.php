<?php
// get_turnos_a_anunciar.php
require_once 'config.php'; 

header('Content-Type: application/json');

$response = ['success' => false, 'data' => []];

try {
    $mysqli->begin_transaction();

    // 1. Seleccionar todos los turnos LLAMADOS que AÚN NO han sido anunciados
    $sql_select = "
        SELECT 
            t.id_turno, 
            t.numero_completo, 
            t.escritorio_llamado, 
            a.termino_ubicacion
        FROM turnos t
        JOIN areas a ON t.id_area = a.id_area
        WHERE t.estado = 'LLAMADO' AND t.anunciado_display = FALSE
        ORDER BY t.hora_llamada ASC
        FOR UPDATE
    ";
    $result = $mysqli->query($sql_select);
    $turnos_a_anunciar = [];
    $turnos_ids = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $turnos_a_anunciar[] = $row;
            $turnos_ids[] = $row['id_turno'];
        }
    }

    // 2. Marcar los turnos seleccionados como ANUNCIADOS
    if (!empty($turnos_ids)) {
        $ids_string = implode(',', $turnos_ids);
        $sql_update = "UPDATE turnos SET anunciado_display = TRUE WHERE id_turno IN ($ids_string)";
        $mysqli->query($sql_update);
    }
    
    $mysqli->commit();
    $response = ['success' => true, 'data' => $turnos_a_anunciar];

} catch (Exception $e) {
    $mysqli->rollback();
    $response = ['success' => false, 'message' => 'Error de transacción: ' . $e->getMessage()];
}

echo json_encode($response);
$mysqli->close();
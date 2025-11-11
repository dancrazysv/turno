<?php
// Asegúrate de que la ruta a config.php sea correcta
require_once 'config.php'; 

header('Content-Type: application/json');

// Obtenemos la fecha de hoy al inicio 
$today = date('Y-m-d 00:00:00');

// Consulta para obtener los últimos 7 turnos llamados o atendidos
$sql = "SELECT 
            t.numero_completo, 
            t.escritorio_llamado, 
            a.termino_ubicacion 
        FROM 
            turnos t
        JOIN 
            areas a ON t.id_area = a.id_area
        WHERE 
            t.estado IN ('LLAMADO', 'ATENDIDO') 
            AND t.hora_generacion >= ? /* Filtra turnos generados desde hoy */
        ORDER BY 
            t.hora_llamada DESC 
        LIMIT 7";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

if ($result) {
    while($row = $result->fetch_assoc()){
        // Aseguramos que el término exista, si no, usamos 'Escritorio'
        $row['termino_ubicacion'] = $row['termino_ubicacion'] ?? 'Escritorio';
        $data[] = $row;
    }
}

// Devolver los datos en formato JSON
echo json_encode($data);

$mysqli->close();
?>
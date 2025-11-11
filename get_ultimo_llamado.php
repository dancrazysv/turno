<?php
// Asegúrate de que la ruta a config.php sea correcta
require_once 'config.php'; 

// Establecer la cabecera para que el navegador sepa que la respuesta es JSON
header('Content-Type: application/json');

// Consulta para obtener SOLO el turno más reciente que está siendo activamente llamado.
$sql = "SELECT 
            t.numero_completo, 
            t.escritorio_llamado, 
            t.hora_llamada,
            a.nombre_area,
             a.termino_ubicacion 
        FROM 
            turnos t
        JOIN 
            areas a ON t.id_area = a.id_area
        WHERE 
            t.estado = 'LLAMADO' 
        ORDER BY 
            t.hora_llamada DESC 
        LIMIT 1";

$result = $mysqli->query($sql);
$data = [];

if ($result && $result->num_rows > 0) {
    // Si se encuentra un turno llamado, se devuelve como objeto.
    $data = $result->fetch_assoc();
}
// Si no se encuentra un turno llamado (ej: después de un reset), $data es un array vacío.

// Devolver los datos en formato JSON
echo json_encode($data);

$mysqli->close();
?>
<?php
// --- LÍNEAS DE DEPURACIÓN TEMPORAL ---
// Descomentar si necesitas depurar errores de PHP:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// ------------------------------------
require_once 'config.php';

$mensaje = "";
$numero_generado = ""; 
$area_generada = ""; 
$area_seleccionada_id = (int)($_GET['id_area'] ?? 0);
$area_seleccionada_nombre = "";
$tramites_result = null;

// --- LÓGICA DE GENERACIÓN DE TURNO (PASO 3) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_tramite'])) {
    $id_tramite = $mysqli->real_escape_string($_POST['id_tramite']);
    
    $mysqli->begin_transaction();
    
    try {
        // 1. Obtener y BLOQUEAR datos del trámite y del área
        $stmt = $mysqli->prepare("
            SELECT 
                t.prefijo_letra, t.ultimo_turno_diario, t.nombre_tramite, t.id_area,
                a.nombre_area 
            FROM tipos_tramite t
            JOIN areas a ON t.id_area = a.id_area
            WHERE t.id_tramite = ? FOR UPDATE
        ");
        $stmt->bind_param("i", $id_tramite);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Trámite no válido o inactivo.");
        }
        
        $data = $result->fetch_assoc();
        $prefijo = $data['prefijo_letra'];
        $area_nombre = $data['nombre_area'];
        $tramite_nombre = $data['nombre_tramite'];
        $id_area = $data['id_area'];
        $nuevo_correlativo = $data['ultimo_turno_diario'] + 1;
        
        // 2. Formatear el número de turno (ej: R-001)
        $numero_formateado = $prefijo . '-' . str_pad($nuevo_correlativo, 3, '0', STR_PAD_LEFT);
        $numero_generado = $numero_formateado; 
        $area_generada = $area_nombre . " / " . $tramite_nombre; 
        
        // 3. Actualizar el correlativo en la tabla TIPOS_TRAMITE
        $stmt = $mysqli->prepare("UPDATE tipos_tramite SET ultimo_turno_diario = ? WHERE id_tramite = ?");
        $stmt->bind_param("ii", $nuevo_correlativo, $id_tramite);
        if (!$stmt->execute()) {
             throw new Exception("Error al actualizar el contador del trámite: " . $stmt->error);
        }
        
        // 4. Insertar el nuevo turno en la tabla TURNOS
        // La inserción ahora usa id_tramite
        $stmt = $mysqli->prepare("INSERT INTO turnos (id_area, id_tramite, numero_correlativo, numero_completo, estado) VALUES (?, ?, ?, ?, 'ESPERA')");
        $stmt->bind_param("iiss", $id_area, $id_tramite, $nuevo_correlativo, $numero_formateado);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al insertar el turno. Revise la clave foránea o el 'id_tramite': " . $stmt->error);
        }
        
        $mysqli->commit();
        
        $mensaje = "¡Turno generado con éxito para {$tramite_nombre}! Su número es: <br><strong class='display-1 text-primary'>{$numero_formateado}</strong><p class='mt-3'>Por favor, tome su ticket.</p>";
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $mensaje = "<div class='alert alert-danger'>Error crítico al generar el turno. ❌<br>Detalles: " . $e->getMessage() . "</div>";
    }
}
// --- LÓGICA DE SELECCIÓN DE TRÁMITE (PASO 2) ---
elseif ($area_seleccionada_id > 0) {
    // Obtener nombre del área
    $stmt = $mysqli->prepare("SELECT nombre_area FROM areas WHERE id_area = ? AND activo = TRUE");
    $stmt->bind_param("i", $area_seleccionada_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        header("Location: index.php"); 
        exit;
    }
    $area_data = $result->fetch_assoc();
    $area_seleccionada_nombre = $area_data['nombre_area'];

    // Obtener trámites del área
    $stmt = $mysqli->prepare("SELECT id_tramite, nombre_tramite, prefijo_letra, ultimo_turno_diario FROM tipos_tramite WHERE id_area = ? AND activo = TRUE ORDER BY nombre_tramite");
    $stmt->bind_param("i", $area_seleccionada_id);
    $stmt->execute();
    $tramites_result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Quiosco de Turnos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .area-card, .tramite-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .area-card:hover, .tramite-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        /* ESTILOS PARA IMPRESIÓN */
        @media print {
            body * { visibility: hidden; }
            #ticket-imprimir, #ticket-imprimir * { visibility: visible; }
            #ticket-imprimir {
                position: absolute; left: 0; top: 0; width: 58mm; padding: 5px; font-family: monospace;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        
        <?php if (!empty($mensaje)): // PASO 3: Muestra Ticket Generado ?>
            <div class="alert alert-success text-center p-5" role="alert">
                <?= $mensaje; ?>
                <hr>
                
                <button onclick="imprimirTicket('<?php echo $numero_generado; ?>', '<?php echo $area_generada; ?>')" class="btn btn-lg btn-primary mt-3 me-3">
                    🖨️ Imprimir Ticket
                </button>
                
                <button onclick="window.location.href='index.php'" class="btn btn-lg btn-secondary mt-3">
                    Volver al Quiosco
                </button>
            </div>
            
            <div id="ticket-imprimir" style="display: none;"></div> 
            
        <?php elseif ($area_seleccionada_id > 0 && $tramites_result && $tramites_result->num_rows > 0): // PASO 2: Muestra Trámites ?>
            <h1 class="text-center mb-4">Área: <?php echo htmlspecialchars($area_seleccionada_nombre); ?></h1>
            <h3 class="text-center mb-5">Seleccione el Trámite Deseado</h3>
            <div class="row justify-content-center">
                <?php while($row = $tramites_result->fetch_assoc()): 
                    $ultimo_numero_completo = $row['prefijo_letra'] . '-' . str_pad($row['ultimo_turno_diario'], 3, '0', STR_PAD_LEFT);
                ?>
                <div class="col-md-4 mb-4">
                    <form method="POST" action="index.php" class="h-100">
                        <input type="hidden" name="id_tramite" value="<?php echo $row['id_tramite']; ?>">
                        <button type="submit" class="tramite-card card text-center p-4 shadow-sm w-100 h-100 border-0 bg-success text-white">
                            <div class="card-body">
                                <h2 class="card-title display-6"><?php echo htmlspecialchars($row['nombre_tramite']); ?></h2>
                                <p class="card-text fs-5 mb-1">(Prefijo: <?php echo $row['prefijo_letra']; ?>)</p>
                                <p class="fs-6 text-warning">Último turno: <strong><?php echo $ultimo_numero_completo; ?></strong></p>
                                <p class="mt-3">Toca para obtener tu turno</p>
                            </div>
                        </button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
            <p class="text-center mt-4"><a href="index.php" class="btn btn-outline-secondary">← Volver a Áreas</a></p>

        <?php elseif ($area_seleccionada_id > 0 && $tramites_result && $tramites_result->num_rows === 0): ?>
            <h1 class="text-center mb-5">Área: <?php echo htmlspecialchars($area_seleccionada_nombre); ?></h1>
            <div class="alert alert-warning text-center">No hay trámites activos para esta área.</div>
            <p class="text-center mt-4"><a href="index.php" class="btn btn-outline-secondary">← Volver a Áreas</a></p>

        <?php else: // PASO 1: Muestra Áreas ?>
            <h1 class="text-center mb-5">Bienvenido. Seleccione su Área de Servicio</h1>
            <div class="row justify-content-center">
                <?php
                $sql = "SELECT id_area, nombre_area FROM areas WHERE activo = TRUE";
                $result = $mysqli->query($sql);
                
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '<div class="col-md-4 mb-4">';
                        // Al hacer clic, redirige a sí mismo con el ID del área en el GET
                        echo '    <a href="index.php?id_area=' . $row['id_area'] . '" class="h-100">';
                        echo '        <div class="area-card card text-center p-4 shadow-sm w-100 h-100 border-0 bg-primary text-white">';
                        echo '            <div class="card-body">';
                        echo '                <h2 class="card-title display-5">' . htmlspecialchars($row['nombre_area']) . '</h2>';
                        echo '                <p class="mt-3">Toca para seleccionar trámite</p>';
                        echo '            </div>';
                        echo '        </div>';
                        echo '    </a>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="text-center">No hay áreas de servicio activas.</p>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <?php $mysqli->close(); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function imprimirTicket(numero, area) {
            const fecha = new Date().toLocaleDateString();
            const hora = new Date().toLocaleTimeString();
            
            const ticketHTML = `
                <div style="text-align: center;">
                    <h3>MI EMPRESA, S.A.</h3>
                    <p>Av. Principal, Edificio X</p>
                    <hr style="border: 1px dashed black;">
                    
                    <p style="font-size: 1.2em;">${area}</p>
                    <h1 style="font-size: 3em; margin: 0;">${numero}</h1>
                    <p style="font-size: 0.8em; margin-top: 5px;">Turno de atención</p>
                    
                    <hr style="border: 1px dashed black;">
                    <p style="font-size: 0.7em;">${fecha} ${hora}</p>
                    <p style="font-size: 0.7em;">¡Gracias por su visita!</p>
                </div>
            `;
            
            document.getElementById('ticket-imprimir').innerHTML = ticketHTML;
            document.getElementById('ticket-imprimir').style.display = 'block'; 
            
            setTimeout(() => {
                window.print();
                document.getElementById('ticket-imprimir').style.display = 'none'; 
            }, 50); 
        }
        
        <?php if (!empty($numero_generado)): ?>
            imprimirTicket('<?php echo $numero_generado; ?>', '<?php echo $area_generada; ?>');
        <?php endif; ?>
    </script>
</body>
</html>
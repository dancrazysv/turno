<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    header("location: login.php");
    exit;
}
require_once 'config.php';

// --- DEFINICIÓN DE FILTROS ---
$estado_filtro = $mysqli->real_escape_string($_GET['estado'] ?? 'ESPERA');
$filtro_fecha = $mysqli->real_escape_string($_GET['fecha'] ?? date('Y-m-d')); 
$fecha_inicio = $filtro_fecha . ' 00:00:00';
$fecha_fin = $filtro_fecha . ' 23:59:59';

// Construcción de la condición WHERE
$filtro_sql = "t.estado = '$estado_filtro'";

if ($estado_filtro === 'ATENDIDO' || $estado_filtro === 'PERDIDO') {
    $filtro_sql .= " AND t.hora_generacion >= '$fecha_inicio' AND t.hora_generacion <= '$fecha_fin'";
}

// Consulta principal para obtener los tickets
$sql = "
    SELECT 
        t.id_turno, 
        t.numero_completo, 
        a.nombre_area, 
        tr.nombre_tramite, 
        t.estado,
        t.hora_generacion,
        t.hora_llamada,
        t.escritorio_llamado,
        u.nombre_completo AS empleado_atendio
    FROM turnos t
    JOIN areas a ON t.id_area = a.id_area
    JOIN tipos_tramite tr ON t.id_tramite = tr.id_tramite
    LEFT JOIN usuarios u ON t.id_usuario_atendio = u.id_usuario
    WHERE $filtro_sql
    ORDER BY t.hora_generacion DESC
    LIMIT 500
";
$tickets_result = $mysqli->query($sql);

// Conteo rápido de estados para el menú (solo cuenta tickets del día actual para los pendientes)
$conteo_sql = "
    SELECT 
        estado, COUNT(id_turno) as count 
    FROM turnos 
    WHERE hora_generacion >= '" . date('Y-m-d 00:00:00') . "' 
    GROUP BY estado
";
$conteo_result = $mysqli->query($conteo_sql);
$conteo = [];
while ($row = $conteo_result->fetch_assoc()) {
    $conteo[$row['estado']] = $row['count'];
}

$estados_posibles = [
    'ESPERA' => 'En Espera',
    'LLAMADO' => 'Llamando',
    'ATENDIDO' => 'Atendidos',
    'PERDIDO' => 'Perdidos'
];

// CONTEO DE ESPERA POR ÁREA Y TRÁMITE (para las tarjetas superiores)
$conteo_area_sql = "
    SELECT a.nombre_area, COUNT(t.id_turno) AS total
    FROM turnos t
    JOIN areas a ON t.id_area = a.id_area
    WHERE t.estado = 'ESPERA' AND t.hora_generacion >= '" . date('Y-m-d 00:00:00') . "'
    GROUP BY a.nombre_area
    ORDER BY total DESC
";
$conteo_area = $mysqli->query($conteo_area_sql)->fetch_all(MYSQLI_ASSOC);

$conteo_tramite_sql = "
    SELECT tr.prefijo_letra, tr.nombre_tramite, COUNT(t.id_turno) AS total
    FROM turnos t
    JOIN tipos_tramite tr ON t.id_tramite = tr.id_tramite
    WHERE t.estado = 'ESPERA' AND t.hora_generacion >= '" . date('Y-m-d 00:00:00') . "'
    GROUP BY tr.nombre_tramite, tr.prefijo_letra
    ORDER BY total DESC
";
$conteo_tramite = $mysqli->query($conteo_tramite_sql)->fetch_all(MYSQLI_ASSOC);


// OBTENER TODAS LAS ÁREAS Y TRÁMITES para el Modal de Traslado
$areas_destino_result = $mysqli->query("SELECT id_area, nombre_area FROM areas WHERE activo = TRUE");
// Es necesario reiniciar el puntero de la consulta de áreas_destino_result para el loop del modal
$areas_destino_result->data_seek(0); 

$tramites_destino_result = $mysqli->query("
    SELECT id_tramite, id_area, prefijo_letra, nombre_tramite 
    FROM tipos_tramite 
    WHERE activo = TRUE ORDER BY id_area, nombre_tramite
");
$tramites_destino_list = $tramites_destino_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tickets | Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-switch {
            background-color: #f0f0f0; 
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 5px;
        }
        .scrollable-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .small-btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
        <div class="container-fluid">
            <span class="navbar-brand">🎫 Monitoreo de Tickets</span>
            <a href="admin_panel.php" class="btn btn-light">← Volver al Panel</a>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Tickets del Sistema (Hoy)</h1>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Total **<?php echo $conteo['ESPERA'] ?? 0; ?>** Tickets en Cola de Espera (Área)</h5>
                    </div>
                    <div class="card-body scrollable-list">
                        <?php if (empty($conteo_area)): ?>
                            <p class="text-muted text-center mb-0">No hay tickets en espera por área.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($conteo_area as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($item['nombre_area']); ?>
                                        <span class="badge bg-danger rounded-pill"><?php echo $item['total']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-info">
                    <div class="card-header bg-info text-dark">
                        <h5 class="mb-0">Detalle por Trámite (Prefijo)</h5>
                    </div>
                    <div class="card-body scrollable-list">
                        <?php if (empty($conteo_tramite)): ?>
                            <p class="text-muted text-center mb-0">No hay tickets en espera por trámite.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($conteo_tramite as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        **[<?php echo htmlspecialchars($item['prefijo_letra']); ?>]** <?php echo htmlspecialchars($item['nombre_tramite']); ?>
                                        <span class="badge bg-primary rounded-pill"><?php echo $item['total']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4 table-switch">
            <h4>Filtros:</h4>
            
            <form id="filterForm" class="row align-items-center">
                <div class="col-auto">
                    <label class="form-label">Estado:</label>
                    <div class="btn-group" role="group">
                        <?php foreach ($estados_posibles as $estado => $label): ?>
                            <a href="?estado=<?php echo $estado; ?>&fecha=<?php echo $filtro_fecha; ?>" 
                               class="btn <?php echo $estado_filtro == $estado ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                <?php echo $label; ?> (<?php echo $conteo[$estado] ?? 0; ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($estado_filtro === 'ATENDIDO' || $estado_filtro === 'PERDIDO'): ?>
                <div class="col-auto">
                    <label for="fecha_input" class="form-label">Seleccionar Día:</label>
                    <input type="date" id="fecha_input" name="fecha" class="form-control" 
                           value="<?php echo $filtro_fecha; ?>" onchange="updateDateFilter(this.value)">
                </div>
                <?php endif; ?>

            </form>
            <p class="mt-3 text-muted">Mostrando tickets en estado: <strong><?php echo $estados_posibles[$estado_filtro]; ?></strong> para el día **<?php echo $filtro_fecha; ?>**.</p>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Turno</th>
                        <th>Área</th>
                        <th>Trámite</th>
                        <th>Estado</th>
                        <th>Generación</th>
                        <th>Llamada</th>
                        <th>Escritorio</th>
                        <th>Empleado</th>
                        <th>Acciones</th> </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($tickets_result && $tickets_result->num_rows > 0) {
                        while($row = $tickets_result->fetch_assoc()) {
                            // Definir clase de color para la fila usando SWITCH
                            $row_class = '';
                            switch ($row['estado']) {
                                case 'ESPERA': $row_class = 'table-warning'; break;
                                case 'LLAMADO': $row_class = 'table-info'; break;
                                case 'ATENDIDO': $row_class = 'table-success'; break;
                                case 'PERDIDO': $row_class = 'table-light text-muted'; break;
                            }
                            
                            echo "<tr class='{$row_class}'>";
                            echo "<td><strong>{$row['numero_completo']}</strong></td>";
                            echo "<td>" . htmlspecialchars($row['nombre_area']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_tramite']) . "</td>";
                            echo "<td>{$row['estado']}</td>";
                            echo "<td>{$row['hora_generacion']}</td>";
                            echo "<td>" . ($row['hora_llamada'] ?? 'N/A') . "</td>";
                            echo "<td>" . ($row['escritorio_llamado'] ?? 'N/A') . "</td>";
                            echo "<td>" . ($row['empleado_atendio'] ?? 'N/A') . "</td>";
                            
                            // 9NA COLUMNA: Acciones (Botón Trasladar)
                            echo '<td>';
                            if ($row['estado'] === 'ESPERA' || $row['estado'] === 'LLAMADO') {
                                echo '<button 
                                        class="btn btn-sm btn-primary small-btn-group" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#transferModal"
                                        data-id-turno="'.$row['id_turno'].'"
                                        data-numero-turno="'.$row['numero_completo'].'"
                                      >
                                        Trasladar
                                      </button>';
                            } else {
                                echo '--';
                            }
                            echo '</td>';
                            
                            echo "</tr>";
                        }
                    } else {
                        echo '<tr><td colspan="9" class="text-center">No hay tickets en este estado para la fecha seleccionada.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="transferModalLabel">Trasladar Ticket: <span id="ticketNumero"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="transferForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="transfer_ticket">
                        <input type="hidden" name="id_turno" id="transfer_id_turno">
                        <div class="alert alert-warning">
                            El ticket será reasignado a una nueva cola con el **mismo número correlativo** pero con un **prefijo nuevo**.
                        </div>

                        <div class="mb-3">
                            <label for="new_area_id" class="form-label">Área de Destino</label>
                            <select class="form-select" id="new_area_id" name="new_area_id" required>
                                <option value="">Seleccione Área</option>
                                <?php while ($area = $areas_destino_result->fetch_assoc()): ?>
                                    <option value="<?php echo $area['id_area']; ?>"><?php echo htmlspecialchars($area['nombre_area']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_tramite_id" class="form-label">Trámite/Prefijo de Destino</label>
                            <select class="form-select" id="new_tramite_id" name="new_tramite_id" required disabled>
                                <option value="">Seleccione Trámite</option>
                                </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Traslado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const TRAMITES_LIST = <?php echo json_encode($tramites_destino_list); ?>;
        
        /**
         * Función JavaScript que recarga la página con la nueva fecha seleccionada,
         * manteniendo el filtro de estado actual.
         */
        function updateDateFilter(selectedDate) {
            const currentStatus = '<?php echo $estado_filtro; ?>';
            window.location.href = `?estado=${currentStatus}&fecha=${selectedDate}`;
        }
        
        /**
         * FILTRA LOS TRÁMITES DE DESTINO BASADO EN EL ÁREA SELECCIONADA EN EL MODAL.
         */
        function filterTramitesDestino(areaId) {
            const tramiteSelect = $('#new_tramite_id');
            tramiteSelect.empty().append('<option value="">Seleccione Trámite</option>');
            
            if (areaId) {
                const filteredTramites = TRAMITES_LIST.filter(t => t.id_area == areaId);
                
                filteredTramites.forEach(tramite => {
                    const optionText = `[${tramite.prefijo_letra}] ${tramite.nombre_tramite}`;
                    tramiteSelect.append(`<option value="${tramite.id_tramite}">${optionText}</option>`);
                });
                tramiteSelect.prop('disabled', false);
            } else {
                tramiteSelect.prop('disabled', true);
            }
        }


        $(document).ready(function() {

            // 1. Lógica para preparar el modal al abrir
            $('#transferModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const idTurno = button.data('id-turno');
                const numeroTurno = button.data('numero-turno');

                $('#ticketNumero').text(numeroTurno);
                $('#transfer_id_turno').val(idTurno);
                
                // Limpiar selecciones previas
                $('#new_area_id').val('');
                filterTramitesDestino(0);
            });
            
            // 2. Evento para filtrar trámites cuando cambia el área
            $('#new_area_id').on('change', function() {
                const areaId = $(this).val();
                filterTramitesDestino(areaId);
            });

            // 3. Manejo del formulario de Traslado
            $('#transferForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.ajax({
                    url: 'admin_tickets_handler.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#transferModal').modal('hide');
                            location.reload(); // Recarga la página para mostrar el ticket en la nueva área/estado
                        } else {
                            alert('Fallo en el traslado: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error de comunicación con el servidor de traslado.');
                    }
                });
            });

        });
    </script>
</body>
</html>
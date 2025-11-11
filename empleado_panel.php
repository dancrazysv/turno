<?php
session_start();
// Verificar que el usuario haya iniciado sesión y sea un empleado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'empleado') {
    header("location: login.php");
    exit;
}
require_once 'config.php';

// Obtener nombre del área y datos del empleado
$area_name = "Área Desconocida";
$termino_ubicacion = "Escritorio"; 
$id_area = $_SESSION["id_area_asignada"] ?? null;
$escritorio = $_SESSION["escritorio_asignado"] ?? null;

if ($id_area) {
    $stmt = $mysqli->prepare("SELECT nombre_area, termino_ubicacion FROM areas WHERE id_area = ?");
    $stmt->bind_param("i", $id_area);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $area_name = $row['nombre_area'];
        $termino_ubicacion = htmlspecialchars($row['termino_ubicacion']); // Asignamos el término dinámico
    }
}

// OBTENER TODAS LAS ÁREAS Y TRÁMITES para el Modal de Traslado (Necesario para el JS del modal)
$areas_destino_result = $mysqli->query("SELECT id_area, nombre_area FROM areas WHERE activo = TRUE ORDER BY nombre_area");
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
    <title>Panel de Atención - Empleado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-body {
            min-height: 200px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Sistema de Turnos - Empleado</span>
            <a href="logout.php" class="btn btn-warning">Cerrar Sesión</a>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="alert alert-info text-center">
            <h3>Bienvenido, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h3>
            <p class="mb-0">Atendiendo **<?php echo htmlspecialchars($area_name); ?>** en el **<?php echo $termino_ubicacion; ?> <?php echo htmlspecialchars($escritorio); ?>**.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card text-center shadow">
                    <div class="card-header bg-dark text-white">Turno Actual en <?php echo htmlspecialchars($area_name); ?></div>
                    <div class="card-body">
                        <h1 id="currentTurnNumber" class="display-2 text-primary">--</h1>
                        <input type="hidden" id="currentTurnId" value="">
                        
                        <p id="statusMessage" class="text-success fs-5">Cargando estado...</p>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button id="btnCallNext" class="btn btn-success btn-lg">📞 Llamar Siguiente Turno</button>
                            <div class="row">
                                <div class="col-6 d-grid">
                                    <button id="btnReCall" class="btn btn-warning btn-lg" disabled>🔔 Re- llamar Turno</button>
                                </div>
                                <div class="col-6 d-grid">
                                    <button id="btnMarkAttended" class="btn btn-danger btn-lg" disabled>✅ Marcar Atendido</button>
                                </div>
                            </div>
                            
                            <button id="btnTransfer" class="btn btn-outline-info btn-sm mt-3" disabled 
                                data-bs-toggle="modal" data-bs-target="#transferModal">
                                🔄 Trasladar Ticket
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">Turnos en Espera (Área <?php echo htmlspecialchars($area_name); ?>)</div>
                    <div class="card-body">
                        <h2 id="waitingCount" class="display-4 text-info">--</h2>
                        <p class="text-muted mb-3">Turnos pendientes en tu cola.</p>
                        
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Trámite</th>
                                        <th>Hora</th>
                                    </tr>
                                </thead>
                                <tbody id="waitingQueueBody">
                                    <tr><td colspan="3" class="text-center">Cargando cola...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="transferModalLabel">Trasladar Ticket: <span id="modalTicketNumero"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="transferForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="transfer_ticket">
                        <input type="hidden" name="id_turno" id="transfer_id_turno">
                        <div class="alert alert-warning">
                            El ticket **mantendrá su número actual** y será colocado al **inicio de la cola** en la nueva área.
                        </div>

                        <div class="mb-3">
                            <label for="new_area_id" class="form-label">Área de Destino</label>
                            <select class="form-select" id="new_area_id" name="new_area_id" required>
                                <option value="">Seleccione Área</option>
                                <?php 
                                $areas_destino_result->data_seek(0); // Reiniciar puntero
                                while ($area = $areas_destino_result->fetch_assoc()): ?>
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
        const TERMINO_UBICACION = "<?php echo $termino_ubicacion; ?>";
        const ESCRITORIO_ASIGNADO = "<?php echo $escritorio; ?>";
        // Convertir la lista de trámites PHP a una constante JS
        const TRAMITES_DESTINO_LIST = <?php echo json_encode($tramites_destino_list); ?>; 
        
        let statusInterval; 

        // Función para actualizar la tabla de turnos en espera
        function updateWaitingQueue() {
            $.ajax({
                url: 'get_queue_data.php', 
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    const queueBody = $('#waitingQueueBody');
                    queueBody.empty(); 
                    
                    const queueLength = (response.queue && Array.isArray(response.queue)) ? response.queue.length : 0;
                    
                    $('#waitingCount').text(queueLength);

                    if (response.success && queueLength > 0) {
                        $.each(response.queue, function(index, turn) {
                            const hora = turn.hora_generacion.substring(11, 16); 
                            
                            const row = `
                                <tr>
                                    <td><strong>${turn.numero_completo}</strong></td>
                                    <td>${turn.nombre_tramite}</td>
                                    <td>${hora}</td> 
                                </tr>
                            `;
                            queueBody.append(row);
                        });
                    } else {
                         const emptyRow = `<tr><td colspan="3" class="text-center text-muted">No hay turnos en espera.</td></tr>`;
                         queueBody.append(emptyRow);
                    } 
                },
                error: function(xhr, status, error) {
                    console.error("Error AJAX al obtener la cola:", status, error);
                    const errorRow = `<tr><td colspan="3" class="text-center text-danger">Error de conexión con la cola.</td></tr>`;
                    $('#waitingQueueBody').html(errorRow);
                }
            });
        }
        
        // Función principal para obtener el estado del turno actual
        function updateTurnoStatus() {
            $.post('turno_handler.php', { action: 'get_current_turn', escritorio: ESCRITORIO_ASIGNADO }, function(response) {
                const isAttending = response.success && response.current_turn;
                
                if (isAttending) {
                    const turno = response.current_turn;
                    $('#currentTurnId').val(turno.id_turno);
                    $('#currentTurnNumber').text(turno.numero_completo);
                    $('#btnCallNext').prop('disabled', true).text('Atendiendo ' + turno.numero_completo);
                    $('#btnReCall').prop('disabled', false);
                    $('#btnMarkAttended').prop('disabled', false);
                    $('#btnTransfer').prop('disabled', false); // HABILITAR TRASLADO
                    $('#statusMessage').text('Atendiendo el turno ' + turno.numero_completo + ' en ' + TERMINO_UBICACION + ' ' + ESCRITORIO_ASIGNADO);
                } else {
                    $('#currentTurnId').val('');
                    $('#currentTurnNumber').text('--');
                    $('#btnCallNext').prop('disabled', false).text('📞 Llamar Siguiente Turno');
                    $('#btnReCall').prop('disabled', true);
                    $('#btnMarkAttended').prop('disabled', true);
                    $('#btnTransfer').prop('disabled', true); // DESHABILITAR TRASLADO
                    $('#statusMessage').text('Listo para llamar al siguiente turno.');
                }
            });
            
            updateWaitingQueue();
        }

        // --- LÓGICA DE TRASLADO DE TICKETS ---

        /**
         * FILTRA LOS TRÁMITES DE DESTINO BASADO EN EL ÁREA SELECCIONADA EN EL MODAL.
         */
        function filterTramitesDestino(areaId) {
            const tramiteSelect = $('#new_tramite_id');
            tramiteSelect.empty().append('<option value="">Seleccione Trámite</option>');
            
            if (areaId) {
                // Usamos == para comparar INT y STRING
                const filteredTramites = TRAMITES_DESTINO_LIST.filter(t => t.id_area == areaId); 
                
                filteredTramites.forEach(tramite => {
                    const optionText = `[${tramite.prefijo_letra}] ${tramite.nombre_tramite}`;
                    tramiteSelect.append(`<option value="${tramite.id_tramite}">${optionText}</option>`);
                });
                tramiteSelect.prop('disabled', false);
            } else {
                tramiteSelect.prop('disabled', true);
            }
        }
        
        // 1. Preparar el Modal de Traslado al abrir
        $('#transferModal').on('show.bs.modal', function() {
            const currentTurnNum = $('#currentTurnNumber').text();
            const currentTurnId = $('#currentTurnId').val();
            
            if (currentTurnNum === '--') {
                alert('No hay un turno activo para trasladar.');
                return false; 
            }

            $('#modalTicketNumero').text(currentTurnNum);
            $('#transfer_id_turno').val(currentTurnId);
            $('#new_area_id').val('');
            filterTramitesDestino(0);
        });
        
        // 2. Evento para filtrar trámites cuando cambia el área
        $('#new_area_id').on('change', function() {
            const areaId = $(this).val();
            filterTramitesDestino(areaId);
        });

        // 3. Manejo del formulario de Traslado (Usa turno_handler.php)
        $('#transferForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            
            clearInterval(statusInterval); 

            $.ajax({
                url: 'turno_handler.php', 
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    alert(response.message);
                    $('#transferModal').modal('hide');
                    
                    // Forzar la actualización después del traslado exitoso
                    updateTurnoStatus(); 
                    startIntervals();
                },
                error: function(xhr, status, error) {
                    alert('Error de comunicación con el servidor de traslado.');
                    startIntervals(); // Reiniciar monitorización si falla
                }
            });
        });


        // --- INICIALIZACIÓN ---

        // Función para INICIAR/REINICIAR los temporizadores
        function startIntervals() {
            clearInterval(statusInterval);
            statusInterval = setInterval(updateTurnoStatus, 5000); 
        }

        // Llamadas iniciales
        updateTurnoStatus();
        startIntervals(); 
    </script>
</body>
</html>
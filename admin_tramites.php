<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    header("location: login.php");
    exit;
}
require_once 'config.php';

// Obtener todas las áreas para el filtro y la selección en el modal
$areas_result = $mysqli->query("SELECT id_area, nombre_area FROM areas ORDER BY nombre_area");
$areas_list = [];
while ($row = $areas_result->fetch_assoc()) {
    $areas_list[] = $row;
}

// Obtener todos los trámites para la tabla principal
$sql_tramites = "
    SELECT 
        t.*, 
        a.nombre_area 
    FROM tipos_tramite t
    JOIN areas a ON t.id_area = a.id_area
    ORDER BY a.nombre_area, t.prefijo_letra
";
$tramites_result = $mysqli->query($sql_tramites);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Trámites | Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
        <div class="container-fluid">
            <span class="navbar-brand">🏷️ Gestión de Trámites y Sub-colas</span>
            <a href="admin_panel.php" class="btn btn-light">← Volver al Panel</a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Configuración de Trámites (Sub-colas)</h1>

        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#tramiteModal" id="btnOpenCreateTramite">
            ➕ Crear Nuevo Trámite
        </button>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Área Principal</th>
                        <th>Nombre Trámite</th>
                        <th>Prefijo</th>
                        <th>Prioridad</th>
                        <th>Último Turno</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tramitesTableBody">
                    <?php 
                    if ($tramites_result->num_rows > 0) {
                        while($row = $tramites_result->fetch_assoc()) {
                            $ultimo_numero = $row['prefijo_letra'] . '-' . str_pad($row['ultimo_turno_diario'], 3, '0', STR_PAD_LEFT);
                            $prioridad_etiqueta = $row['prioridad'] == 1 ? '<span class="badge bg-danger">ALTA</span>' : '<span class="badge bg-secondary">Normal</span>';
                            
                            echo "<tr data-id='{$row['id_tramite']}'>";
                            echo "<td>{$row['id_tramite']}</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_area']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_tramite']) . "</td>";
                            echo "<td>{$row['prefijo_letra']}</td>";
                            echo "<td>{$prioridad_etiqueta}</td>"; // Muestra la prioridad
                            echo "<td>{$ultimo_numero}</td>";
                            echo "<td>" . ($row['activo'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>') . "</td>";
                            echo '<td>';
                            echo '  <button class="btn btn-sm btn-info edit-tramite me-2" data-id="'.$row['id_tramite'].'" data-bs-toggle="modal" data-bs-target="#tramiteModal">Editar</button>';
                            echo '  <button class="btn btn-sm btn-danger delete-tramite" data-id="'.$row['id_tramite'].'">Eliminar</button>';
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">No hay trámites registrados.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="tramiteModal" tabindex="-1" aria-labelledby="tramiteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tramiteModalLabel">Crear Trámite (Sub-cola)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="tramiteForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="tramiteAction" value="create_tramite">
                        <input type="hidden" name="id_tramite" id="id_tramite">

                        <div class="mb-3">
                            <label for="id_area" class="form-label">Área Principal</label>
                            <select class="form-select" id="id_area" name="id_area" required>
                                <?php foreach ($areas_list as $area): ?>
                                    <option value="<?php echo $area['id_area']; ?>"><?php echo htmlspecialchars($area['nombre_area']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="nombre_tramite" class="form-label">Nombre del Trámite (Sub-cola)</label>
                            <input type="text" class="form-control" id="nombre_tramite" name="nombre_tramite" required>
                        </div>

                        <div class="mb-3">
                            <label for="prefijo_letra" class="form-label">Prefijo de Letra Único (Ej: R, M)</label>
                            <input type="text" class="form-control" id="prefijo_letra" name="prefijo_letra" maxlength="1" required>
                            <div class="form-text">Debe ser una única letra MAYÚSCULA y ÚNICA en todo el sistema.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prioridad" class="form-label">Nivel de Prioridad</label>
                            <select class="form-select" id="prioridad" name="prioridad" required>
                                <option value="0">0 - Normal (Por orden de llegada)</option>
                                <option value="1">1 - Alta Prioridad (Llamada primero)</option>
                            </select>
                            <div class="form-text">Los tickets con Prioridad 1 serán llamados antes que los de Prioridad 0.</div>
                        </div>
                        <div class="mb-3">
                            <label for="ultimo_turno_diario" class="form-label">Contador Actual de Turnos</label>
                            <input type="number" class="form-control" id="ultimo_turno_diario" name="ultimo_turno_diario" min="0" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                            <label class="form-check-label" for="activo">Trámite Activo</label>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveTramite">Guardar Trámite</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const HANDLER_URL = 'admin_tramites_handler.php'; 

        $(document).ready(function() {
            // Lógica para Abrir el Modal de Creación
            $('#btnOpenCreateTramite').on('click', function() {
                $('#tramiteModalLabel').text('Crear Nuevo Trámite');
                $('#tramiteForm')[0].reset(); 
                $('#tramiteAction').val('create_tramite');
                $('#id_tramite').val('');
                $('#activo').prop('checked', true);
            });

            // Lógica para Editar Trámite
            $('#tramitesTableBody').on('click', '.edit-tramite', function() {
                const id = $(this).data('id');
                
                $('#tramiteModalLabel').text('Editar Trámite (ID: ' + id + ')');
                $('#tramiteAction').val('update_tramite');
                $('#id_tramite').val(id);

                $.post(HANDLER_URL, { action: 'get_tramite', id_tramite: id }, function(response) {
                    if (response.success) {
                        const tramite = response.tramite;
                        $('#id_area').val(tramite.id_area);
                        $('#nombre_tramite').val(tramite.nombre_tramite);
                        $('#prefijo_letra').val(tramite.prefijo_letra);
                        $('#ultimo_turno_diario').val(tramite.ultimo_turno_diario);
                        $('#prioridad').val(tramite.prioridad); // <-- CARGA LA PRIORIDAD
                        $('#activo').prop('checked', tramite.activo == 1);
                    } else {
                        alert('Error al cargar datos del trámite: ' + response.message);
                    }
                }, 'json');
            });
            
            // Lógica para Guardar (Crear o Editar)
            $('#tramiteForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.post(HANDLER_URL, formData, function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#tramiteModal').modal('hide');
                        location.reload(); 
                    } else {
                        alert('Error: ' + response.message);
                    }
                }, 'json').fail(function() {
                    alert('Error de conexión con el servidor.');
                });
            });

            // Lógica para Eliminar Trámite
            $('#tramitesTableBody').on('click', '.delete-tramite', function() {
                const id = $(this).data('id');
                if (confirm('¿Está seguro de eliminar el Trámite ID ' + id + '? Esto podría dejar turnos sin referencia.')) {
                    $.post(HANDLER_URL, { action: 'delete_tramite', id_tramite: id }, function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload(); 
                        } else {
                            alert('Error al eliminar: ' + response.message);
                        }
                    }, 'json').fail(function() {
                        alert('Error de conexión con el servidor.');
                    });
                }
            });

        });
    </script>
</body>
</html>
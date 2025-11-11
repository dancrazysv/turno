<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    header("location: login.php");
    exit;
}
require_once 'config.php';

// Obtener todas las áreas para llenar la tabla, sin prefijo ni contador
$sql = "SELECT id_area, nombre_area, termino_ubicacion, activo FROM areas ORDER BY id_area ASC"; 
$areas_result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Áreas | Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
        <div class="container-fluid">
            <span class="navbar-brand">⚙️ Gestión de Áreas</span>
            <a href="admin_panel.php" class="btn btn-light">← Volver al Panel</a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Configuración de Áreas de Atención</h1>

        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#areaModal" id="btnOpenCreateArea">
            ➕ Crear Nueva Área
        </button>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Ubicación (Término)</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="areasTableBody">
                    <?php 
                    if ($areas_result->num_rows > 0) {
                        while($row = $areas_result->fetch_assoc()) {
                            echo "<tr data-id='{$row['id_area']}'>";
                            echo "<td>{$row['id_area']}</td>";
                            echo "<td>{$row['nombre_area']}</td>";
                            echo "<td>" . htmlspecialchars($row['termino_ubicacion']) . "</td>";
                            echo "<td>" . ($row['activo'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>') . "</td>";
                            echo '<td>';
                            echo '  <button class="btn btn-sm btn-info edit-area me-2" data-id="'.$row['id_area'].'" data-bs-toggle="modal" data-bs-target="#areaModal">Editar</button>';
                            echo '  <button class="btn btn-sm btn-danger delete-area" data-id="'.$row['id_area'].'">Eliminar</button>';
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo '<tr><td colspan="5" class="text-center">No hay áreas registradas.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="areaModal" tabindex="-1" aria-labelledby="areaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="areaModalLabel">Crear Área de Atención</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="areaForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="areaAction" value="create_area">
                        <input type="hidden" name="id_area" id="id_area">

                        <div class="mb-3">
                            <label for="nombre_area" class="form-label">Nombre del Área</label>
                            <input type="text" class="form-control" id="nombre_area" name="nombre_area" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="termino_ubicacion" class="form-label">Término de Ubicación</label>
                            <select class="form-select" id="termino_ubicacion" name="termino_ubicacion" required>
                                <option value="Escritorio">Escritorio</option>
                                <option value="Módulo">Módulo</option>
                                <option value="Puerta">Puerta</option>
                                <option value="Ventanilla">Ventanilla</option>
                            </select>
                            <div class="form-text">Palabra que aparecerá antes del número (ej: Módulo 5).</div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                            <label class="form-check-label" for="activo">Área Activa</label>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveArea">Guardar Área</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // ------------------------------------------
            // 1. Lógica para Abrir el Modal de Creación
            // ------------------------------------------
            $('#btnOpenCreateArea').on('click', function() {
                $('#areaModalLabel').text('Crear Nueva Área de Atención');
                $('#areaForm')[0].reset(); 
                $('#areaAction').val('create_area');
                $('#id_area').val('');
                $('#activo').prop('checked', true);
            });

            // ------------------------------------------
            // 2. Lógica para Editar Área (Cargar datos al Modal)
            // ------------------------------------------
            $('#areasTableBody').on('click', '.edit-area', function() {
                const id = $(this).data('id');
                
                $('#areaModalLabel').text('Editar Área de Atención (ID: ' + id + ')');
                $('#areaAction').val('update_area');
                $('#id_area').val(id);

                // Obtener datos del área
                $.post('admin_handler.php', { action: 'get_area', id_area: id }, function(response) {
                    if (response.success) {
                        const area = response.area;
                        $('#nombre_area').val(area.nombre_area);
                        $('#termino_ubicacion').val(area.termino_ubicacion); // <-- Carga el término
                        $('#activo').prop('checked', area.activo == 1);
                    } else {
                        alert('Error al cargar datos del área: ' + response.message);
                    }
                }, 'json');
            });
            
            // ------------------------------------------
            // 3. Lógica para Guardar (Crear o Editar)
            // ------------------------------------------
            $('#areaForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.post('admin_handler.php', formData, function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#areaModal').modal('hide');
                        location.reload(); 
                    } else {
                        alert('Error: ' + response.message);
                    }
                }, 'json').fail(function() {
                    alert('Error de conexión con el servidor.');
                });
            });

            // ------------------------------------------
            // 4. Lógica para Eliminar Área
            // ------------------------------------------
            $('#areasTableBody').on('click', '.delete-area', function() {
                const id = $(this).data('id');
                if (confirm('¿Está seguro de eliminar el Área ID ' + id + '? Esto eliminará todos los trámites y turnos asociados.')) {
                    $.post('admin_handler.php', { action: 'delete_area', id_area: id }, function(response) {
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
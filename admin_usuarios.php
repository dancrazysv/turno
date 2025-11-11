<?php
session_start();
// Verificar que el usuario esté logueado y tenga el rol de 'admin'
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    header("location: login.php");
    exit;
}
require_once 'config.php';

// Obtener Áreas
$areas_result = $mysqli->query("SELECT id_area, nombre_area FROM areas WHERE activo = TRUE ORDER BY nombre_area");
$areas_list = [];
while ($row = $areas_result->fetch_assoc()) {
    $areas_list[] = $row;
}

// OBTENER TODOS LOS TRÁMITES PARA LA ASIGNACIÓN (CON SU ID DE ÁREA)
$tramites_result = $mysqli->query("
    SELECT t.id_tramite, t.id_area, t.nombre_tramite, t.prefijo_letra, a.nombre_area
    FROM tipos_tramite t
    JOIN areas a ON t.id_area = a.id_area
    WHERE t.activo = TRUE
    ORDER BY a.nombre_area, t.nombre_tramite
");
$tramites_list = [];
while ($row = $tramites_result->fetch_assoc()) {
    $tramites_list[] = $row;
}

// Obtener Usuarios para la tabla
$sql = "SELECT 
            u.id_usuario, u.nombre_completo, u.username, u.rol, u.escritorio_asignado, u.activo,
            a.nombre_area 
        FROM usuarios u
        LEFT JOIN areas a ON u.id_area_asignada = a.id_area
        ORDER BY u.id_usuario DESC"; 
$usuarios_result = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Usuarios | Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
        <div class="container-fluid">
            <span class="navbar-brand">⚙️ Gestión de Usuarios y Empleados</span>
            <a href="admin_panel.php" class="btn btn-light">← Volver al Panel</a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Configuración de Usuarios del Sistema</h1>

        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#userModal" id="btnOpenCreateUser">
            ➕ Crear Nuevo Usuario
        </button>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Área Asignada</th>
                        <th>Escritorio</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="usuariosTableBody">
                    <?php 
                    if ($usuarios_result->num_rows > 0) {
                        while($row = $usuarios_result->fetch_assoc()) {
                            echo "<tr data-id='{$row['id_usuario']}'>";
                            echo "<td>{$row['id_usuario']}</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_completo']) . "</td>";
                            echo "<td>{$row['username']}</td>";
                            echo "<td>" . ($row['rol'] == 'admin' ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-primary">Empleado</span>') . "</td>";
                            echo "<td>" . ($row['nombre_area'] ?? 'N/A') . "</td>";
                            echo "<td>" . ($row['escritorio_asignado'] ?? 'N/A') . "</td>";
                            echo "<td>" . ($row['activo'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>') . "</td>";
                            echo '<td>';
                            echo '  <button class="btn btn-sm btn-info edit-user me-2" data-id="'.$row['id_usuario'].'" data-bs-toggle="modal" data-bs-target="#userModal">Editar</button>';
                            echo '  <button class="btn btn-sm btn-danger delete-user" data-id="'.$row['id_usuario'].'">Eliminar</button>';
                            echo '</td>';
                            echo "</tr>";
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">No hay usuarios registrados.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">Crear Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="userForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="userAction" value="create_user">
                        <input type="hidden" name="id_usuario" id="id_usuario">

                        <div class="mb-3">
                            <label for="nombre_completo" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario (Login)</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div id="password_help" class="form-text text-danger" style="display:none;">Dejar vacío para no cambiar.</div>
                        </div>
                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="empleado">Empleado</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>

                        <div id="employee-fields">
                            <div class="mb-3">
                                <label for="id_area_asignada" class="form-label">Área de Trabajo</label>
                                <select class="form-select" id="id_area_asignada" name="id_area_asignada">
                                    <option value="">Seleccione Área (Opcional)</option>
                                    <?php foreach ($areas_list as $area): ?>
                                        <option value="<?php echo $area['id_area']; ?>"><?php echo htmlspecialchars($area['nombre_area']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Define los turnos que podrá llamar.</div>
                            </div>
                            <div class="mb-3">
                                <label for="escritorio_asignado" class="form-label">Número de Escritorio/Módulo</label>
                                <input type="number" class="form-control" id="escritorio_asignado" name="escritorio_asignado" min="1">
                                <div class="form-text">Número que se mostrará en la pantalla.</div>
                            </div>

                            <div class="mb-3" id="tramites_container">
                                <label class="form-label">Trámites Permitidos (Prefijos)</label>
                                <div class="form-text mb-2">Seleccione los trámites que este empleado **PUEDE** llamar.</div>
                                <div class="border p-3 rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($tramites_list as $tramite): ?>
                                        <?php 
                                            $area_nombre = htmlspecialchars($tramite['nombre_area']);
                                            $tramite_nombre = htmlspecialchars($tramite['nombre_tramite']);
                                            $prefijo = htmlspecialchars($tramite['prefijo_letra']);
                                        ?>
                                        <div class="form-check tramite-item" data-area-id="<?php echo $tramite['id_area']; ?>">
                                            <input class="form-check-input tramite-checkbox" type="checkbox" name="tramites_asignados[]" value="<?php echo $tramite['id_tramite']; ?>" id="tramite_<?php echo $tramite['id_tramite']; ?>">
                                            <label class="form-check-label" for="tramite_<?php echo $tramite['id_tramite']; ?>">
                                                **[<?php echo $prefijo; ?>]** <?php echo $tramite_nombre; ?> (Área: <?php echo $area_nombre; ?>)
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                            <label class="form-check-label" for="activo">Usuario Activo</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveUser">Guardar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateEmployeeFields(rol) {
            // Muestra u oculta los campos de asignación si no es un empleado
            if (rol === 'admin') {
                $('#employee-fields').slideUp();
                $('#id_area_asignada').val(''); 
                $('#escritorio_asignado').val('');
                $('.tramite-checkbox').prop('checked', false); 
            } else {
                $('#employee-fields').slideDown();
            }
            filterTramites(); // Llama al filtro al cambiar el rol
        }
        
        /**
         * FILTRA LOS CHECKBOXES DE TRÁMITES BASADO EN EL ÁREA SELECCIONADA
         */
        function filterTramites() {
            const selectedAreaId = $('#id_area_asignada').val();
            
            $('.tramite-item').each(function() {
                const itemAreaId = $(this).data('area-id');
                
                if (selectedAreaId === '' || itemAreaId == selectedAreaId) {
                    // Muestra el trámite si no hay área seleccionada o si coincide el ID
                    $(this).show();
                } else {
                    // Oculta el trámite y desmárcalo (crucial para no guardar asignaciones incorrectas)
                    $(this).hide();
                    $(this).find('.tramite-checkbox').prop('checked', false);
                }
            });
        }

        $(document).ready(function() {
            // Asigna el evento change al dropdown de área
            $('#id_area_asignada').on('change', filterTramites);
            
            // Inicializar al cargar
            updateEmployeeFields($('#rol').val());
            filterTramites(); // Ejecuta el filtro inicial

            // Escuchar cambio de Rol
            $('#rol').on('change', function() {
                updateEmployeeFields($(this).val());
            });

            // ------------------------------------------
            // 1. Lógica para Abrir el Modal de Creación
            // ------------------------------------------
            $('#btnOpenCreateUser').on('click', function() {
                $('#userModalLabel').text('Crear Nuevo Usuario');
                $('#userForm')[0].reset(); 
                $('#userAction').val('create_user');
                $('#id_usuario').val('');
                $('#password').prop('required', true).attr('placeholder', '').val('');
                $('#password_help').hide();
                $('#activo').prop('checked', true);
                
                // Forzar el rol a "Empleado" y limpiar el área para asegurar el filtro inicial
                $('#rol').val('empleado'); 
                $('#id_area_asignada').val(''); 
                
                updateEmployeeFields('empleado');
                $('.tramite-checkbox').prop('checked', false); 
                filterTramites(); // Asegura que se muestren/oculten según el área vacía
            });

            // ------------------------------------------
            // 2. Lógica para Editar Usuario (Cargar datos)
            // ------------------------------------------
            $('#usuariosTableBody').on('click', '.edit-user', function() {
                const id = $(this).data('id');
                
                $('#userModalLabel').text('Editar Usuario (ID: ' + id + ')');
                $('#userAction').val('update_user');
                $('#id_usuario').val(id);
                $('#password').prop('required', false).attr('placeholder', 'Dejar vacío para no cambiar').val('');
                $('#password_help').show();
                
                // 1. Limpiar y desmarcar todos los trámites antes de cargar
                $('.tramite-checkbox').prop('checked', false);
                $('.tramite-item').show(); // Mostrar todos temporalmente

                // 2. Obtener datos del usuario, incluyendo trámites
                $.post('admin_handler.php', { action: 'get_user', id_usuario: id }, function(response) {
                    if (response.success) {
                        const user = response.user;
                        $('#nombre_completo').val(user.nombre_completo);
                        $('#username').val(user.username);
                        $('#rol').val(user.rol);
                        
                        // Cargar y luego filtrar
                        $('#id_area_asignada').val(user.id_area_asignada || '');
                        filterTramites(); // Filtra inmediatamente por el área cargada
                        
                        $('#escritorio_asignado').val(user.escritorio_asignado || '');
                        $('#activo').prop('checked', user.activo == 1);
                        
                        updateEmployeeFields(user.rol); 

                        // 3. Cargar Trámites Asignados
                        if (user.tramites_asignados) {
                            user.tramites_asignados.forEach(function(tramite_id) {
                                // Solo marcar si el checkbox existe
                                $('#tramite_' + tramite_id).prop('checked', true);
                            });
                        }
                    } else {
                        alert('Error al cargar datos del usuario: ' + response.message);
                    }
                }, 'json');
            });
            
            // ------------------------------------------
            // 3. Lógica para Guardar (Crear o Editar)
            // ------------------------------------------
            $('#userForm').on('submit', function(e) {
                e.preventDefault();
                // Serializa el formulario para enviar todos los datos, incluyendo los trámites
                const formData = $(this).serialize(); 

                $.post('admin_handler.php', formData, function(response) {
                    if (response.success) {
                        alert(response.message);
                        $('#userModal').modal('hide');
                        location.reload(); 
                    } else {
                        alert('Error: ' + response.message);
                    }
                }, 'json').fail(function() {
                    alert('Error de conexión con el servidor.');
                });
            });

            // ------------------------------------------
            // 4. Lógica para Eliminar Usuario
            // ------------------------------------------
            $('#usuariosTableBody').on('click', '.delete-user', function() {
                const id = $(this).data('id');
                if (confirm('¿Está seguro de eliminar el Usuario ID ' + id + '?')) {
                    $.post('admin_handler.php', { action: 'delete_user', id_usuario: id }, function(response) {
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
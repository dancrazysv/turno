<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    header("location: login.php");
    exit;
}
require_once 'config.php';

// Obtener lista de EMPLEADOS
$usuarios = $mysqli->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'empleado' ORDER BY nombre_completo");

// OBTENER LISTA DE TRÁMITES
$tramites = $mysqli->query("
    SELECT t.id_tramite, t.nombre_tramite, a.nombre_area, t.prefijo_letra
    FROM tipos_tramite t
    JOIN areas a ON t.id_area = a.id_area
    ORDER BY a.nombre_area, t.nombre_tramite
");

$estados_reporte = [
    'TODOS' => 'Todos los Estados',
    'ATENDIDO' => 'Atendido',
    'LLAMADO' => 'Llamado (En curso)',
    'ESPERA' => 'En Espera',
    'PERDIDO' => 'Perdido',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar Reportes | Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
        <div class="container-fluid">
            <span class="navbar-brand">📊 Reportes de Atención</span>
            <a href="admin_panel.php" class="btn btn-light">← Volver al Panel</a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Filtros de Reporte</h1>
        
        <form id="reportForm" class="card p-4 shadow-sm mb-5">
            <div class="row g-3">
                
                <div class="col-md-3">
                    <label for="filtro_tipo" class="form-label">Tipo de Reporte</label>
                    <select id="filtro_tipo" name="filtro_tipo" class="form-select" required>
                        <option value="diario">Por Día</option>
                        <option value="mensual">Por Mes</option>
                        <option value="historico">Histórico (Lento)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="filtro_fecha" class="form-label">Día / Mes</label>
                    <input type="date" id="filtro_fecha" name="filtro_fecha" class="form-control" value="<?php echo date('Y-m-d', time()); ?>" required>
                    <small class="form-text text-muted">Selecciona un día o el primer día del mes.</small>
                </div>

                <div class="col-md-3">
                    <label for="filtro_usuario" class="form-label">Filtrar por Empleado</label>
                    <select id="filtro_usuario" name="filtro_usuario" class="form-select">
                        <option value="0">Todos los Empleados</option>
                        <?php while ($user = $usuarios->fetch_assoc()): ?>
                            <option value="<?php echo $user['id_usuario']; ?>"><?php echo htmlspecialchars($user['nombre_completo']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="filtro_tramite" class="form-label">Filtrar por Trámite</label>
                    <select id="filtro_tramite" name="filtro_tramite" class="form-select">
                        <option value="0">Todos los Trámites</option>
                        <?php while ($tramite = $tramites->fetch_assoc()): ?>
                            <option value="<?php echo $tramite['id_tramite']; ?>">
                                [<?php echo $tramite['prefijo_letra']; ?>] <?php echo htmlspecialchars($tramite['nombre_tramite']); ?> (<?php echo htmlspecialchars($tramite['nombre_area']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="filtro_estado" class="form-label">Filtrar por Estado</label>
                    <select id="filtro_estado" name="filtro_estado" class="form-select">
                        <?php foreach ($estados_reporte as $valor => $etiqueta): ?>
                            <option value="<?php echo $valor; ?>"><?php echo $etiqueta; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-9 d-flex justify-content-end align-items-end">
                    <button type="submit" class="btn btn-primary btn-lg">Generar Reporte</button>
                </div>
            </div>
        </form>

        <h2>Resultados del Reporte</h2>
        <div id="reporteResultado" class="mt-4">
            <div class="alert alert-warning text-center">Selecciona los filtros y haz clic en "Generar Reporte".</div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Lógica para ajustar el campo de fecha según el tipo de reporte
            $('#filtro_tipo').on('change', function() {
                const tipo = $(this).val();
                if (tipo === 'mensual') {
                    $('#filtro_fecha').attr('type', 'month');
                } else if (tipo === 'diario') {
                    $('#filtro_fecha').attr('type', 'date');
                } else {
                    $('#filtro_fecha').attr('type', 'hidden');
                }
            }).trigger('change'); 

            // Manejo del formulario para generar el reporte
            $('#reportForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize() + '&action=generate_report'; 

                $('#reporteResultado').html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p>Generando reporte...</p></div>');

                $.post('report_handler.php', formData, function(response) {
                    if (response.success) {
                        displayReport(response.data);
                    } else {
                        $('#reporteResultado').html(`<div class="alert alert-danger">${response.message}</div>`);
                    }
                }, 'json').fail(function() {
                    $('#reporteResultado').html('<div class="alert alert-danger">Error de conexión o fallo del servidor.</div>');
                });
            });

            // Función para renderizar la tabla de resultados
            function displayReport(data) {
                if (data.length === 0) {
                    $('#reporteResultado').html('<div class="alert alert-info">No se encontraron turnos para los filtros seleccionados.</div>');
                    return;
                }

                let tableHtml = `
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Turno</th>
                                <th>Área / Trámite</th>
                                <th>Escritorio</th>
                                <th>Empleado</th>
                                <th>Estado</th>
                                <th>Tiempo Espera (min)</th>
                                <th>Generación</th>
                                <th>Fin Atención</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                data.forEach(item => {
                    const tiempoEspera = item.tiempo_espera_minutos !== null ? parseFloat(item.tiempo_espera_minutos).toFixed(2) : '--';
                    
                    tableHtml += `
                        <tr>
                            <td>${item.numero_completo}</td>
                            <td>${item.nombre_area} / <strong>${item.nombre_tramite}</strong></td>
                            <td>${item.termino_ubicacion} ${item.escritorio_llamado || '--'}</td>
                            <td>${item.empleado_nombre || 'N/A'}</td>
                            <td>${item.estado}</td>
                            <td>${tiempoEspera}</td>
                            <td>${item.hora_generacion}</td>
                            <td>${item.hora_atencion_fin || 'Pendiente'}</td>
                        </tr>
                    `;
                });

                tableHtml += `</tbody></table>`;
                $('#reporteResultado').html(tableHtml);
            }
        });
    </script>
</body>
</html>
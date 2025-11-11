<?php
session_start();
// Asegurar que el usuario esté logueado y tenga el rol de 'admin'
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'admin') {
    header("location: login.php");
    exit;
}
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow">
        <div class="container-fluid">
            <span class="navbar-brand">⚙️ Panel de Administración</span>
            <span class="text-white me-3">Bienvenido, <?php echo htmlspecialchars($_SESSION["username"]); ?></span>
            <a href="logout.php" class="btn btn-light">Cerrar Sesión</a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Gestión del Sistema de Turnos</h1>
        <div class="row">
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Áreas y Estructuras</h5>
                        <p class="card-text">Define los nombres de las áreas y el término de ubicación (Módulo, Puerta).</p>
                        <a href="admin_areas.php" class="btn btn-primary">Administrar Áreas</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Trámites y Prefijos</h5>
                        <p class="card-text">Define los diferentes trámites (sub-colas), sus prefijos y contadores por área.</p>
                        <a href="admin_tramites.php" class="btn btn-success">Administrar Trámites</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Usuarios y Escritorios</h5>
                        <p class="card-text">Administra empleados, asigna escritorios y define el área de atención por defecto.</p>
                        <a href="admin_usuarios.php" class="btn btn-primary">Administrar Usuarios</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Monitoreo de Tickets</h5>
                        <p class="card-text">Visualiza el estado de la cola (pendientes) y el historial de tickets atendidos.</p>
                        <a href="admin_tickets.php" class="btn btn-info text-white">Ver Tickets</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Mantenimiento</h5>
                        <p class="card-text">Resetea la numeración de turnos diariamente (función crítica).</p>
                        <button id="btnResetTurnos" class="btn btn-danger">⚠️ Resetear Todos los Turnos Diarios</button>
                        <div id="resetStatus" class="mt-2"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Reportes y Estadísticas</h5>
                        <p class="card-text">Visualiza tiempos de espera, número de atenciones por empleado y área.</p>
                        <a href="admin_reportes.php" class="btn btn-success">Ver Reportes</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // Lógica para el Reseteo de Turnos Diarios (mantiene la funcionalidad)
        $('#btnResetTurnos').on('click', function() {
            if (confirm('¿Está seguro de resetear los números de turno diarios de TODAS las áreas a 0? Esta acción es irreversible y borra el historial de llamados/atendidos de hoy.')) {
                $.post('admin_handler.php', { action: 'reset_daily_turns' }, function(response) {
                    if (response.success) {
                        $('#resetStatus').html('<div class="alert alert-success">✅ Reseteo completado. Los trámites comienzan en 0.</div>');
                    } else {
                        $('#resetStatus').html('<div class="alert alert-danger">❌ Error al resetear: ' + response.message + '</div>');
                    }
                }, 'json').fail(function() {
                    $('#resetStatus').html('<div class="alert alert-danger">❌ Error de conexión con el servidor.</div>');
                });
            }
        });
    </script>
</body>
</html>
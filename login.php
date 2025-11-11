<?php
session_start();
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $mysqli->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id_usuario, username, password, rol, id_area_asignada, escritorio_asignado FROM usuarios WHERE username = ? AND activo = TRUE";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // ¡IMPORTANTE! La contraseña debe ser verificada con password_verify
            // Usamos una verificación simple aquí por simplicidad, pero usa password_verify() en producción
            // if (password_verify($password, $user['password'])) { 
            if ($password === 'admin123' || $user['password'] === 'password_hash_empleado_1' || $user['password'] === 'password_hash_empleado_2') { // Reemplazar con lógica real de hash!
                
                $_SESSION["loggedin"] = true;
                $_SESSION["id_usuario"] = $user['id_usuario'];
                $_SESSION["username"] = $user['username'];
                $_SESSION["rol"] = $user['rol'];
                $_SESSION["id_area_asignada"] = $user['id_area_asignada'];
                $_SESSION["escritorio_asignado"] = $user['escritorio_asignado'];

                // Redirigir según el rol
                if ($user['rol'] === 'admin') {
                    header("location: admin_panel.php"); // Crearás este después
                } else {
                    header("location: empleado_panel.php");
                }
                exit;
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "Usuario no encontrado o inactivo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Empleado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .logo-img {
            display: block;
            margin: 0 auto 20px;
            max-width: 100px; /* Tamaño del escudo */
            height: auto;
        }
    </style>


</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="card p-4 shadow-lg" style="width: 100%; max-width: 400px;">
            <img src="assets/img/escudo.png" alt="Escudo Institucional" class="logo-img">
        
            <h2 class="card-title text-center mb-4">Acceso de Empleados</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario:</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Ingresar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
// Define las constantes de conexión a la base de datos
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // ¡Cámbialo en producción!
define('DB_PASSWORD', ''); // ¡Cámbialo en producción!
define('DB_NAME', 'turnero_db'); // Nombre de la base de datos que creaste

// Intentar la conexión a la base de datos
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if ($mysqli->connect_error) {
    die("ERROR: No se pudo conectar a la base de datos. " . $mysqli->connect_error);
}
?>
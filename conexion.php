<?php
$host = "127.0.0.1";
$usuario = "root";
$password = "";
$base_datos = "ppp_unfv_recuperada";

mysqli_report(MYSQLI_REPORT_OFF);

// Intentar primero con el puerto 3306 (estándar para ti y XAMPP)
$puerto = "3306";
$conexion = @new mysqli($host, $usuario, $password, $base_datos, $puerto);

// Si falla la conexión, intentar con el puerto 3307 (el que usaba tu amigo)
if ($conexion->connect_error) {
    $puerto = "3307";
    $conexion = @new mysqli($host, $usuario, $password, $base_datos, $puerto);
    
    if ($conexion->connect_error) {
        die("Error de conexión (probados puertos 3306 y 3307): " . $conexion->connect_error);
    }
}

$conexion->set_charset("utf8mb4");

?>
<?php
// conexion.php
$host = "localhost";
$usuario = "root";
$contrasena = "";
$base_de_datos = "scyberrotsen";

// Crear la conexión
$conexion = mysqli_connect($host, $usuario, $contrasena, $base_de_datos);

// Verificar si la conexión fue exitosa
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>
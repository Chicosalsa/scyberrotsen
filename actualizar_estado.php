<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_equipo = $_POST['id_equipo'];
    $nuevo_estado = $_POST['nuevo_estado'];

    // Validar que el estado sea uno de los permitidos
    $estados_permitidos = ['En diagnóstico', 'En reparación', 'Esperando refacciones', 'Listo para entrega', 'Entregado'];
    
    if (!in_array($nuevo_estado, $estados_permitidos)) {
        header("Location: EquiposEntregados.php?error=2");
        exit;
    }

    // Actualizar el estado en la base de datos
    $sql = "UPDATE equipo SET estadoActual = ? WHERE Id_Equipo = ?";
    
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("si", $nuevo_estado, $id_equipo);

        if ($stmt->execute()) {
            // Redirigir con mensaje de éxito
            header("Location: EquiposEntregados.php?success=1");
        } else {
            // Redirigir con mensaje de error
            header("Location: EquiposEntregados.php?error=1");
        }
        $stmt->close();
    } else {
        // Error en la preparación de la consulta
        error_log("Error al preparar la consulta: " . $conexion->error);
        header("Location: EquiposEntregados.php?error=3");
    }

    $conexion->close();
} else {
    header("Location: EquiposEntregados.php");
}
?>
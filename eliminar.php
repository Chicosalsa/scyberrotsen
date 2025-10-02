<?php
session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conexion, $_GET['id']);
    
    // Obtener el nombre del producto antes de intentar eliminarlo
    $sqlNombre = "SELECT Nombre FROM accesorio WHERE Id_Accesorios = '$id'";
    $resultNombre = $conexion->query($sqlNombre);
    $nombre = "";
    
    if ($resultNombre && $resultNombre->num_rows > 0) {
        $rowNombre = $resultNombre->fetch_assoc();
        $nombre = $rowNombre['Nombre'];
    }
    
    // Verificar si existen ventas asociadas
    $sqlCheck = "SELECT COUNT(*) as count FROM ventaaccesorios WHERE Id_Accesorios = '$id'";
    $resultCheck = $conexion->query($sqlCheck);
    $rowCheck = $resultCheck->fetch_assoc();
    
    if ($rowCheck['count'] > 0) {
        echo "<script>
            alert('No se puede eliminar el producto \"" . htmlspecialchars($nombre, ENT_QUOTES) . "\" porque tiene " . $rowCheck['count'] . " venta(s) asociada(s).');
            window.location.href = 'ActualizacionStock.php';
        </script>";
    } else {
        // Si no hay ventas asociadas, proceder con la eliminación
        $sql = "DELETE FROM accesorio WHERE Id_Accesorios = '$id'";
        if ($conexion->query($sql)) {
            echo "<script>
                alert('Producto \"" . htmlspecialchars($nombre, ENT_QUOTES) . "\" eliminado correctamente.');
                window.location.href = 'ActualizacionStock.php';
            </script>";
        } else {
            echo "<script>
                alert('Error al eliminar el producto: " . $conexion->error . "');
                window.location.href = 'ActualizacionStock.php';
            </script>";
        }
    }
}
?>
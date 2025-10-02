<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar si la sesión se ha iniciado correctamente

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener el nombre de usuario y el rol (admin o no admin)
$username = "";
$isAdmin = false;
    $username = $_SESSION["username"];
    echo "<!-- Debug: Usuario en sesión = " . $username . " -->";

    // Consultar si el usuario es administrador
    $sqlUser = "SELECT Id_Rol FROM users WHERE usuario = ?";
    $stmt = $conexion->prepare($sqlUser);
    if ($stmt === false) {
        die("Error en la preparación de la consulta: " . $conexion->error);
    }
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        die("Error en la ejecución de la consulta: " . $stmt->error);
    }
    $resultUser = $stmt->get_result();
    
    if ($resultUser && $resultUser->num_rows > 0) {
        $userData = $resultUser->fetch_assoc();
        $isAdmin = ($userData['Id_Rol'] == 1);
    } else {
    }

// Cerrar sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Procesar búsqueda
$search = "";
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conexion, $_GET['search']); // Prevent SQL injection
    if (empty($search)) {
        header("Location: ActualizacionStock.php");
        exit;
    }
    $sql = "SELECT * FROM accesorio 
            WHERE Id_Accesorios LIKE '%$search%' 
            OR Nombre LIKE '%$search%'  
            ORDER BY Id_Accesorios";
} else {
    $sql = "SELECT * FROM accesorio ORDER BY Id_Accesorios";
}

$result = $conexion->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conexion->error);
}

// Procesar guardado de datos (actualización masiva)
if (isset($_POST['guardar'])) {
    $successCount = 0;
    $errorCount = 0;
    
    // Recorrer todos los productos enviados
    foreach ($_POST['productos'] as $id => $datos) {
        $id = mysqli_real_escape_string($conexion, $id);
        
        // Obtener datos actuales del producto
        $sqlGetCurrent = "SELECT * FROM accesorio WHERE Id_Accesorios = '$id'";
        $resultGetCurrent = $conexion->query($sqlGetCurrent);
        $datosActuales = $resultGetCurrent->fetch_assoc();
        
        // Obtener los valores del formulario
        $nombre = isset($datos['Nombre']) ? mysqli_real_escape_string($conexion, $datos['Nombre']) : $datosActuales['Nombre'];
        $proveedor = isset($datos['Proveedor']) ? mysqli_real_escape_string($conexion, $datos['Proveedor']) : $datosActuales['Proveedor'];
        $marca = isset($datos['Marca']) ? mysqli_real_escape_string($conexion, $datos['Marca']) : $datosActuales['Marca'];
        $modelo = isset($datos['Modelo']) ? mysqli_real_escape_string($conexion, $datos['Modelo']) : $datosActuales['Modelo'];
        $codigo = isset($datos['Codigo']) ? mysqli_real_escape_string($conexion, $datos['Codigo']) : $datosActuales['Codigo'];
        $precio = isset($datos['Precio']) ? floatval($datos['Precio']) : $datosActuales['Precio'];
        $diaIngreso = isset($datos['DiaIngreso']) ? intval($datos['DiaIngreso']) : $datosActuales['DiaIngreso'];
        $mesIngreso = isset($datos['MesIngreso']) ? intval($datos['MesIngreso']) : $datosActuales['MesIngreso'];
        $anioIngreso = isset($datos['AnioIngreso']) ? intval($datos['AnioIngreso']) : $datosActuales['AnioIngreso'];
        $stockDisponible = isset($datos['stockDisponible']) ? intval($datos['stockDisponible']) : $datosActuales['stockDisponible'];
        $presentacion_producto = isset($datos['Presentacion_Producto']) ? mysqli_real_escape_string($conexion, $datos['Presentacion_Producto']) : $datosActuales['Presentacion_Producto'];

        // Verificar si el nombre ya existe en la base de datos (excluyendo el registro actual)
        $sqlCheckName = "SELECT COUNT(*) AS count FROM accesorio WHERE Nombre = '$nombre' AND Id_Accesorios != '$id'";
        $resultCheckName = $conexion->query($sqlCheckName);
        $rowCheckName = $resultCheckName->fetch_assoc();

        if ($rowCheckName['count'] > 0) {
            $errorCount++;
            continue; // Saltar este producto si el nombre ya existe
        }

        // Construir la consulta de actualización
        $cambios = false;
        $updates = [];

        // Verificar cambios y construir la consulta de actualización
        if ($isAdmin) {
            if ($nombre != $datosActuales['Nombre']) {
                $updates[] = "Nombre = '$nombre'";
                $cambios = true;
            }
            if ($proveedor != $datosActuales['Proveedor']) {
                $updates[] = "Proveedor = '$proveedor'";
                $cambios = true;
            }
            if ($marca != $datosActuales['Marca']) {
                $updates[] = "Marca = '$marca'";
                $cambios = true;
            }
            if ($modelo != $datosActuales['Modelo']) {
                $updates[] = "Modelo = '$modelo'";
                $cambios = true;
            }
            if ($codigo != $datosActuales['Codigo']) {
                $updates[] = "Codigo = '$codigo'";
                $cambios = true;
            }
            if ($precio != $datosActuales['Precio']) {
                $updates[] = "Precio = $precio";
                $cambios = true;
            }
            if ($diaIngreso != $datosActuales['DiaIngreso'] || $mesIngreso != $datosActuales['MesIngreso'] || $anioIngreso != $datosActuales['AnioIngreso']) {
                $updates[] = "DiaIngreso = $diaIngreso, MesIngreso = $mesIngreso, AnioIngreso = $anioIngreso";
                $cambios = true;
            }
            if ($presentacion_producto != $datosActuales['Presentacion_Producto']) {
                $updates[] = "Presentacion_Producto = '$presentacion_producto'";
                $cambios = true;
            }
        }

        if ($stockDisponible != $datosActuales['stockDisponible']) {
            $updates[] = "stockDisponible = $stockDisponible";
            $cambios = true;
        }

        if ($cambios) {
            $sqlUpdate = "UPDATE accesorio 
                          SET " . implode(", ", $updates) . " 
                          WHERE Id_Accesorios = '$id'";
        
            if ($conexion->query($sqlUpdate)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
    }
    
    if ($successCount > 0) {
        echo "<script>alert('Se guardaron $successCount productos correctamente.');</script>";
    }
    if ($errorCount > 0) {
        echo "<script>alert('Hubo $errorCount errores al guardar algunos productos.');</script>";
    }
    if ($successCount == 0 && $errorCount == 0) {
        echo "<script>alert('No se detectaron cambios para guardar.');</script>";
    }
    
    echo "<script>window.location.href = 'ActualizacionStock.php';</script>";
}

// Procesar adición de nuevos datos
if (isset($_POST['add']) && $isAdmin) {
    // Obtener los valores del formulario
    $nombre = isset($_POST['Nombre']) ? mysqli_real_escape_string($conexion, $_POST['Nombre']) : null;
    $proveedor = isset($_POST['Proveedor']) ? mysqli_real_escape_string($conexion, $_POST['Proveedor']) : null;
    $marca = isset($_POST['Marca']) ? mysqli_real_escape_string($conexion, $_POST['Marca']) : null;
    $modelo = isset($_POST['Modelo']) ? mysqli_real_escape_string($conexion, $_POST['Modelo']) : null;
    $codigo = isset($_POST['Codigo']) ? mysqli_real_escape_string($conexion, $_POST['Codigo']) : null;
    $precio = isset($_POST['Precio']) ? floatval($_POST['Precio']) : null;
    $diaIngreso = isset($_POST['DiaIngreso']) ? intval($_POST['DiaIngreso']) : null;
    $mesIngreso = isset($_POST['MesIngreso']) ? intval($_POST['MesIngreso']) : null;
    $anioIngreso = isset($_POST['AnioIngreso']) ? intval($_POST['AnioIngreso']) : null;
    $stockDisponible = isset($_POST['stockDisponible']) ? intval($_POST['stockDisponible']) : null;
    $presentacion_producto = isset($_POST['Presentacion_Producto']) ? mysqli_real_escape_string($conexion, $_POST['Presentacion_Producto']) : null;

    // Validar que todos los campos estén llenos
    if (empty($nombre) || empty($proveedor) || empty($marca) || empty($modelo) || empty($codigo) || empty($precio) || empty($diaIngreso) || empty($mesIngreso) || empty($anioIngreso) || empty($stockDisponible) || empty($presentacion_producto)) {
        echo "<script>alert('Todos los campos deben estar llenos.');</script>";
    } else {
        // Verificar si el nombre ya existe en la base de datos
        $sqlCheckName = "SELECT COUNT(*) AS count FROM accesorio WHERE Nombre = '$nombre'";
        $resultCheckName = $conexion->query($sqlCheckName);
        $rowCheckName = $resultCheckName->fetch_assoc();

        if ($rowCheckName['count'] > 0) {
            echo "<script>alert('El nombre del producto ya existe. Por favor, elige otro nombre.');</script>";
        } else {
            // Insertar los datos en la base de datos
            $sqlInsert = "INSERT INTO accesorio (Nombre, Proveedor, Marca, Modelo, Codigo, Precio, stockDisponible, Presentacion_Producto, DiaIngreso, MesIngreso, AnioIngreso) 
                          VALUES ('$nombre', '$proveedor', '$marca', '$modelo', '$codigo', $precio, $stockDisponible, '$presentacion_producto', $diaIngreso, $mesIngreso, $anioIngreso)";

            if ($conexion->query($sqlInsert)) {
                echo "<script>alert('El producto se ha agregado correctamente.');</script>";
                echo "<script>window.location.href = 'ActualizacionStock.php';</script>"; // Recargar la página
            } else {
                echo "<script>alert('Error al agregar el producto: " . $conexion->error . "');</script>";
            }
        }
    }
}

// Procesar eliminación de producto
if (isset($_POST['delete'])) {
    if ($isAdmin) { // Solo los administradores pueden eliminar
        $id = mysqli_real_escape_string($conexion, $_POST['id']);
        
        // Fetch product name for the confirmation message
        $stmt = $conexion->prepare("SELECT Nombre FROM accesorio WHERE Id_Accesorios = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result_name = $stmt->get_result();
        $nombre = "";
        
        if ($row = $result_name->fetch_assoc()) {
            $nombre = $row['Nombre'];
        }
        
        // Verificar si existen ventas asociadas al producto
        $stmt_check = $conexion->prepare("SELECT COUNT(*) as count FROM ventaaccesorios WHERE Id_Accesorios = ?");
        $stmt_check->bind_param("s", $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        
        if ($row_check['count'] > 0) {
            // Si existen ventas asociadas, mostrar alerta
            echo "<script>alert('No se puede eliminar el producto \"" . htmlspecialchars($nombre, ENT_QUOTES) . "\" porque tiene ventas asociadas.');</script>";
        } else {
            // Si no hay ventas asociadas, proceder con la confirmación de eliminación
            echo "<script>
                if (confirm('¿Está seguro de eliminar el producto: " . htmlspecialchars($nombre, ENT_QUOTES) . "?')) {
                    window.location.href = 'eliminar.php?id=" . htmlspecialchars($id, ENT_QUOTES) . "';
                }
            </script>";
        }
    } else {
        echo "<script>alert('No tienes permisos para eliminar productos.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="iconSet.png" type="image/png">    
    <title>Ciber Rotsen - Accesorios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
       /* Estilos generales del body */
       .botonSubir {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 10vh; /* Ajusta según necesites */
        }
        
        .header-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .search-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-guardar {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
        }

        .btn-guardar:hover {
            background-color: #218838;
        }

        .btn-salir {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-salir:hover {
            background-color: #c82333;
        }

        body {
            color: #5D5F63;
            background: #dde4f5;
            font-family: 'Open Sans', sans-serif;
            padding: 0;
            margin: 0;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        /* Estilo del logo */
        #logo {
            width: 75px;
            aspect-ratio: 240 / 227; /* Relación de aspecto original */
            margin: 20px;
            grid-column: 1; /* Coloca el logo en la primera columna */
            align-self: start; /* Alinea el logo en la parte superior */
            background-color: #f1f6ff;
        }

        /* Contenedor principal */
        .column {
            background-color: #f1f6ff;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr; /* Tres columnas iguales */
            align-items: start; /* Alinea los elementos en la parte superior */
            height: 105px;
            gap: 0px; /* Elimina cualquier separación adicional */
        }

        /* Estilo del título h1 */
        h1 {
            font-size: 30px;
            color: #283785;
            font-family: Verdana, sans-serif;
            margin: 20px 0px 0px 30px; /* Reducir margen inferior a 5px */
            grid-column: 2 / 4; /* El título abarca las columnas 2 y 3 */
            text-align: center; /* Centra el texto horizontalmente */
        }

        /* Estilo del subtítulo h2 */
        h2 {
            font-size: 14px;
            color: #283785;
            font-family: Verdana, sans-serif;
            margin: 20px 50px 0px, 0px; /* Sin margen superior ni inferior */
            grid-column: 2 / 4; /* El subtítulo abarca las columnas 2 y 3 */
            text-align: center; /* Centra el texto horizontalmente */
        }

        h1, h2 {
            line-height: 1;
        }

        /* Estilos del menú lateral */
        .sidebar {
            width: 240px;
            height: 100%;
            background: #f0f6fe;
            position: absolute;
            -webkit-transition: all .2s ease-in-out;
            -moz-transition: all .2s ease-in-out;
            -o-transition: all .2s ease-in-out;
            -ms-transition: all .2s ease-in-out;
            transition: all .2s ease-in-out;
            z-index: 100;
        }

        .sidebar #leftside-navigation ul,
        .sidebar #leftside-navigation ul ul {
            margin: -2px 0 0;
            padding: 0;
        }

        .sidebar #leftside-navigation ul li {
            list-style-type: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar #leftside-navigation ul li.active > a {
            color: #f0f6fe;
        }

        .sidebar #leftside-navigation ul li a {
            color: #52555a;
            text-decoration: none;
            display: block;
            padding: 18px 0 18px 25px;
            font-size: 15px;
            font-weight: bold;
            outline: 0;
            -webkit-transition: all 200ms ease-in;
            -moz-transition: all 200ms ease-in;
            -o-transition: all 200ms ease-in;
            -ms-transition: all 200ms ease-in;
            transition: all 200ms ease-in;
        }

        .sidebar #leftside-navigation ul li a:hover {
            color: #000000;
        }

        .sidebar #leftside-navigation ul li a span {
            display: inline-block;
        }

        .sidebar #leftside-navigation ul li a i {
            width: 20px;
        }

        .sidebar #leftside-navigation ul ul {
            display: none;
        }

        .sidebar #leftside-navigation ul ul li {
            background: #f0f6fe;
            margin-bottom: 0;
            margin-left: 0;
            margin-right: 0;
            border-bottom: none;
        }

        .sidebar #leftside-navigation ul ul li a {
            font-size: 14px;
            padding-top: 13px;
            padding-bottom: 13px;
            color: #52555a;
        }
        .container {
            margin-left: 240px;
            padding: 20px;
        }
        table {
            background-color: #f0f7ff ;
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #283785;
            color: white;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 300px;
            padding: 10px;
            font-size: 16px;
        }
        .btn {
            padding: 5px 10px;
            background-color: #283785;
            color: white;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #1a2657;
        }   
        .error-message {
            background-color: #ffebee; /* Fondo rojo claro */
            color: #c62828; /* Texto rojo oscuro */
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error-message a {
            color: #283785; /* Color del enlace */
            text-decoration: underline;
            cursor: pointer;
        }
        
        .fecha-inputs {
            display: flex;
            gap: 5px;
        }
        
        .fecha-inputs input {
            width: 50px;
        }
    </style>
</head>
<body>
    

   <?php include 'sidebar.php'; ?>

    <div class="container"><!-- Titulos -->
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <!-- agregar variables de usuario logueado -->
            <h2 id="textoBajoh1" class="titulo">Bienvenido <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
    </div>

    <div class="container">
        <!-- Fila con búsqueda, guardar y salir -->
        <div class="search-container" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <!-- Campo de búsqueda a la izquierda -->
            <div class="search-box" style="flex: 1;">
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="search" placeholder="Buscar por ID o Nombre" value="<?php echo htmlspecialchars($search); ?>" style="width: 300px; padding: 10px; font-size: 16px;">
                    <button type="submit" class="btn">Buscar</button>
                </form>
            </div>

            <!-- Botones a la derecha -->
            <div class="action-buttons" style="display: flex; gap: 10px;">
                <form method="POST" action="" style="margin: 0;">
                    <button type="submit" name="guardar" class="btn-guardar">Guardar Cambios</button>
                </form>
                <a href="menu.php" class="btn-salir">Salir</a>
            </div>
        </div>

        <!-- Mensaje de error si no se encuentran productos -->
        <?php if ($result->num_rows === 0 && isset($_GET['search'])) : ?>
            <div class="error-message">
                No se encontraron productos. ¿Deseas agregar uno nuevo? Rellena los campos vacios de abajo:
            </div>
        <?php endif; ?>

        <!-- Tabla de accesorios -->
        <form method="POST" action="" id="mainForm">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Proveedor</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Fecha de Ingreso</th>
                        <th>Código</th>
                        <th>Precio</th>
                        <th>Presentación</th>
                        <th>Stock Disponible</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Fila vacía para agregar nuevos datos (solo visible para administradores) -->
                    <?php if ($isAdmin) : ?>
                        <tr>
                            <form method="POST" action="">
                                <td>
                                    <?php
                                    // Obtener el último ID registrado
                                    $sqlLastId = "SELECT MAX(Id_Accesorios) AS lastId FROM accesorio";
                                    $resultLastId = $conexion->query($sqlLastId);
                                    $lastId = $resultLastId->fetch_assoc()['lastId'];
                                    $newId = $lastId + 1; // Generar el nuevo ID
                                    echo $newId; // Mostrar el nuevo ID
                                    ?>
                                </td>
                                <td><input type="text" name="Nombre" style="width: 100px;" required></td>
                                <td><input type="text" name="Proveedor" style="width: 100px;" required></td>
                                <td><input type="text" name="Marca" style="width: 100px;" required></td>
                                <td><input type="text" name="Modelo" style="width: 100px;" required></td>
                                <td>
                                    <div class="fecha-inputs">
                                        <input type="number" name="DiaIngreso" placeholder="Día" min="1" max="31" style="width: 50px;" required>
                                        <input type="number" name="MesIngreso" placeholder="Mes" min="1" max="12" style="width: 50px;" required>
                                        <input type="number" name="AnioIngreso" placeholder="Año" min="2000" max="2100" style="width: 60px;" required>
                                    </div>
                                </td>
                                <td><input type="text" name="Codigo" style="width: 100px;" required></td>
                                <td><input type="number" name="Precio" step="0.01" min="0" style="width: 80px;" required></td>
                                <td><input type="text" name="Presentacion_Producto" style="width: 100px;" required></td>
                                <td><input type="number" name="stockDisponible" style="width: 80px;" required></td>
                                <td>
                                    <button type="submit" name="add" class="btn">Subir</button>
                                </td>
                            </form>
                        </tr>
                    <?php endif; ?>

                    <!-- Filas existentes -->
                    <?php while ($row = $result->fetch_assoc()) : ?>
                        <tr>
                            <input type="hidden" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][id]" value="<?php echo htmlspecialchars($row['Id_Accesorios']); ?>">
                            
                            <!-- Columna ID (no editable) -->
                            <td><?php echo htmlspecialchars($row['Id_Accesorios']); ?></td>

                            <!-- Columna Nombre (deshabilitada para no administradores) -->
                            <td>
                                <input type="text" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Nombre]" value="<?php echo htmlspecialchars($row['Nombre']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Proveedor (deshabilitada para no administradores) -->
                            <td>
                                <input type="text" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Proveedor]" value="<?php echo htmlspecialchars($row['Proveedor']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Marca (deshabilitada para no administradores) -->
                            <td>
                                <input type="text" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Marca]" value="<?php echo htmlspecialchars($row['Marca']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Modelo (deshabilitada para no administradores) -->
                            <td>
                                <input type="text" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Modelo]" value="<?php echo htmlspecialchars($row['Modelo']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Fecha de Ingreso (deshabilitada para no administradores) -->
                            <td>
                                <div class="fecha-inputs">
                                    <input type="number" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][DiaIngreso]" value="<?php echo htmlspecialchars($row['DiaIngreso']); ?>" placeholder="Día" min="1" max="31" style="width: 50px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                                    <input type="number" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][MesIngreso]" value="<?php echo htmlspecialchars($row['MesIngreso']); ?>" placeholder="Mes" min="1" max="12" style="width: 50px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                                    <input type="number" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][AnioIngreso]" value="<?php echo htmlspecialchars($row['AnioIngreso']); ?>" placeholder="Año" min="2000" max="2100" style="width: 60px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                                </div>
                            </td>

                            <!-- Columna Código (deshabilitada para no administradores) -->
                            <td>
                                <input type="text" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Codigo]" value="<?php echo htmlspecialchars($row['Codigo']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Precio (deshabilitada para no administradores) -->
                            <td>
                                <input type="number" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Precio]" value="<?php echo htmlspecialchars($row['Precio']); ?>" step="0.01" min="0" style="width: 80px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Presentación del Producto (deshabilitada para no administradores) -->
                            <td>
                                <input type="text" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][Presentacion_Producto]" value="<?php echo htmlspecialchars($row['Presentacion_Producto']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'disabled'; ?>>
                            </td>

                            <!-- Columna Stock Disponible (editable para todos) -->
                            <td>
                                <input type="number" name="productos[<?php echo htmlspecialchars($row['Id_Accesorios']); ?>][stockDisponible]" value="<?php echo htmlspecialchars($row['stockDisponible']); ?>" style="width: 80px;">
                            </td>

                            <!-- Columna Acciones (solo botón Eliminar) -->
                            <td>
                                <?php if ($isAdmin) : ?>
                                    <button type="submit" name="delete" class="btn" value="<?php echo htmlspecialchars($row['Id_Accesorios']); ?>">Eliminar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </form>

        <div class="botonSubir">
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Script para manejar la apertura y cierre de submenús
        $("#leftside-navigation .sub-menu > a").click(function (e) {
            e.preventDefault();
            const subMenu = $(this).next("ul");
            const arrow = $(this).find(".arrow");

            $("#leftside-navigation ul ul").not(subMenu).slideUp();
            $("#leftside-navigation .arrow").not(arrow).removeClass("fa-angle-up").addClass("fa-angle-down");

            subMenu.slideToggle();

            if (subMenu.is(":visible")) {
                arrow.removeClass("fa-angle-down").addClass("fa-angle-up");
            } else {
                arrow.removeClass("fa-angle-up").addClass("fa-angle-down");
            }
        });
        
        // Manejar el botón eliminar
        $('button[name="delete"]').click(function(e) {
            e.preventDefault();
            var productId = $(this).val();
            var productName = $(this).closest('tr').find('input[name*="[Nombre]"]').val();
            
            if (confirm('¿Está seguro de eliminar el producto: ' + productName + '?')) {
                // Crear un formulario temporal para enviar la solicitud de eliminación
                var form = $('<form>').attr({
                    method: 'POST',
                    action: ''
                });
                $('<input>').attr({
                    type: 'hidden',
                    name: 'delete',
                    value: '1'
                }).appendTo(form);
                $('<input>').attr({
                    type: 'hidden',
                    name: 'id',
                    value: productId
                }).appendTo(form);
                form.appendTo('body').submit();
            }
        });
    </script>
</body>
</html>
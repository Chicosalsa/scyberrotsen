<?php
session_start();
require_once __DIR__ . '/conexion.php'; // Incluir la conexión a la base de datos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener el nombre de usuario y el rol (admin o no admin)
$username = "";
$isAdmin = false;
if (isset($_SESSION["username"])) {
    $username = $_SESSION["username"];
    // Consultar si el usuario es administrador
    $sqlUser = "SELECT admin FROM users WHERE usuario = '$username'";
    $resultUser = $conexion->query($sqlUser);
    if ($resultUser && $resultUser->num_rows > 0) {
        $userData = $resultUser->fetch_assoc();
        $isAdmin = $userData['admin'] == 1;
    }
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
    $sql = "SELECT * FROM accesorio 
            WHERE Id_Accesorios LIKE '%$search%' 
            OR Nombre LIKE '%$search%' 
            OR Proveedor LIKE '%$search%' 
            OR Marca LIKE '%$search%' 
            OR Codigo LIKE '%$search%' 
            OR Modelo LIKE '%$search%' 
            ORDER BY Id_Accesorios";
} else {
    $sql = "SELECT * FROM accesorio ORDER BY Id_Accesorios";
}

$result = $conexion->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conexion->error);
}

// Procesar actualización de datos
if (isset($_POST['update'])) {
    $id = mysqli_real_escape_string($conexion, $_POST['id']);
    $nombre = mysqli_real_escape_string($conexion, $_POST['Nombre']);
    $proveedor = mysqli_real_escape_string($conexion, $_POST['Proveedor']);
    $marca = mysqli_real_escape_string($conexion, $_POST['Marca']);
    $modelo = mysqli_real_escape_string($conexion, $_POST['Modelo']);
    $codigo = mysqli_real_escape_string($conexion, $_POST['Codigo']);
    $precio = floatval($_POST['Precio']);
    $fecha_ingreso = mysqli_real_escape_string($conexion, $_POST['Fecha_Ingreso']);

    $sqlUpdate = "UPDATE accesorio 
                  SET Nombre = '$nombre', 
                      Proveedor = '$proveedor', 
                      Marca = '$marca', 
                      Modelo = '$modelo', 
                      Codigo = '$codigo', 
                      Precio = $precio, 
                      Fecha_Ingreso = '$fecha_ingreso' 
                  WHERE Id_Accesorios = '$id'";

    if ($conexion->query($sqlUpdate)) {
        echo "<script>alert('Los datos del producto $nombre con ID: $id han sido modificados correctamente.');</script>";
        echo "<script>window.location.href = 'ActualizacionStock.php';</script>"; // Recargar la página
    } else {
        echo "<script>alert('Error al actualizar: " . $conexion->error . "');</script>";
    }
}

// Procesar eliminación de producto
if (isset($_POST['delete'])) {
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
    
    echo "<script>
        if (confirm('¿Está seguro de eliminar el producto: " . htmlspecialchars($nombre, ENT_QUOTES) . "?')) {
            window.location.href = 'eliminar.php?id=" . htmlspecialchars($id, ENT_QUOTES) . "';
        }
    </script>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Accesorios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
       /* Estilos generales del body */
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

        /* Estilos para los cuadros de acceso rápido */
        .quick-access {
            display: flex;
            flex-direction: column; /* Apila los cuadros verticalmente */
            align-items: center; /* Centra los cuadros horizontalmente */
            margin-top: 20px;
            padding: 0 20px; /* Espaciado lateral */
        }

        .quick-access .box {
            background: #f1f7fe;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            width: 80%; /* Estira los cuadros */
            max-width: 1000px; /* Limita el ancho máximo */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
            margin-bottom: 20px; /* Espaciado entre cuadros */
            margin-left: 160px;
        }

        .quick-access .box:hover {
            transform: translateY(-5px);
        }

        .quick-access .box i {
            font-size: 40px;
            color: #283785;
            margin-bottom: 10px;
        }

        .quick-access .box h3 {
            font-size: 18px;
            color: #283785;
            margin: 10px 0;
        }

        .quick-access .box p {
            font-size: 14px;
            color: #5D5F63;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f6ff;
            margin: 0;
            padding: 0;
        }
        .container {
            margin-left: 240px;
            padding: 20px;
        }
        table {
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
    </style>
</head>
<body>
    

   <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <!-- agregar variables de usuario logueado -->
            <h2 id="textoBajoh1" class="titulo">Bienvenido <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
        
    </div>

    <!-- Contenedor principal con tabla de productos -->
    <div class="container">
        <!-- Campo de búsqueda -->
        <div class="search-box">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Buscar por ID, nombre, proveedor, marca o código" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Buscar</button>
            </form>
        </div>

        <!-- Tabla de accesorios -->
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
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) : ?>
                    <tr>
                        <!-- Columna ID (no editable) -->
                        <td><?php echo htmlspecialchars($row['Id_Accesorios']); ?></td>

                        <!-- Columna Nombre (editable solo para admin) -->
                        <td>
                            <input type="text" name="Nombre" value="<?php echo htmlspecialchars($row['Nombre']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Proveedor (editable solo para admin) -->
                        <td>
                            <input type="text" name="Proveedor" value="<?php echo htmlspecialchars($row['Proveedor']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Marca (editable solo para admin) -->
                        <td>
                            <input type="text" name="Marca" value="<?php echo htmlspecialchars($row['Marca']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Modelo (editable solo para admin) -->
                        <td>
                            <input type="text" name="Modelo" value="<?php echo htmlspecialchars($row['Modelo']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Fecha de Ingreso (editable solo para admin) -->
                        <td>
                            <input type="date" name="Fecha_Ingreso" value="<?php echo htmlspecialchars($row['fechaIngreso']); ?>" style="width: 120px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Código (editable solo para admin) -->
                        <td>
                            <input type="text" name="Codigo" value="<?php echo htmlspecialchars($row['Codigo']); ?>" style="width: 100px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Precio (editable solo para admin) -->
                        <td>
                            <input type="number" name="Precio" value="<?php echo htmlspecialchars($row['Precio']); ?>" step="0.01" min="0" style="width: 80px;" <?php echo $isAdmin ? '' : 'readonly'; ?>>
                        </td>

                        <!-- Columna Acciones (botones Subir y Eliminar) -->
                        <td>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['Id_Accesorios']); ?>">
                                <button type="submit" name="update" class="btn">Subir</button>
                                <button type="submit" name="delete" class="btn">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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
    </script>
</body>
</html>
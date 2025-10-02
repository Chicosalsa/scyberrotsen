<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
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
            OR Presentacion_Producto LIKE '%$search%'
            ORDER BY Id_Accesorios";
} else {
    $sql = "SELECT * FROM accesorio ORDER BY Id_Accesorios";
}

$result = $conexion->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conexion->error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Accesorios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
    <style>
       /* Estilos generales del body */
       .botonSubir {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 10vh; /* Ajusta según necesites */
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
            padding: 8px 16px;
            background-color: #283785;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 14px;
            min-width: 60px; /* Ancho mínimo fijo */
            height: 30px; /* Altura fija */
            line-height: 19px; /* Para centrar verticalmente el texto */
            box-sizing: border-box;
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
        
        .fecha-display {
            font-size: 14px;
            color: #555;
        }
    </style>
    <link rel="Stylesheet" href="menuStyleSheet.css">

       <?php include 'sidebar.php'; ?>

    <div class="container"><!-- Titulos -->
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen - Inventario</h1>
            <h2>Inventario</h2>
        </div>
    </div>

    <div class="container">
        <div class="search-box">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Buscar por ID, Nombre o Presentación" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Buscar</button>
                <a href="/CiberRotsen/ActualizacionStock.php" class="btn">Editar</a>
            </form>
        </div>
        <?php if ($result->num_rows === 0 && isset($_GET['search'])) : ?>
            <div class="error-message">
            No se encontraron productos. ¿Deseas <a href="ActualizacionStock.php" onclick="document.querySelector('form[method=\'POST\']').scrollIntoView();">agregar uno nuevo</a>?
        </div>
    <?php endif; ?>
<!-- Tabla de accesorios -->
<?php if ($result->num_rows > 0) : ?>
    <!-- Mostrar la tabla solo si hay resultados -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Proveedor</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Presentación</th>
                <th>Fecha de Ingreso</th>
                <th>Código</th>
                <th>Precio</th>
                <th>Stock Disponible</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['Id_Accesorios']); ?>">
                        
                        <!-- Columna ID (no editable) -->
                        <td><?php echo htmlspecialchars($row['Id_Accesorios']); ?></td>

                        <!-- Columna Nombre -->
                        <td>
                            <?php echo htmlspecialchars($row['Nombre']); ?>
                        </td>

                        <!-- Columna Proveedor -->
                        <td>
                            <?php echo htmlspecialchars($row['Proveedor']); ?> 
                        </td>

                        <!-- Columna Marca -->
                        <td>
                            <?php echo htmlspecialchars($row['Marca']); ?> 
                        </td>

                        <!-- Columna Modelo -->
                        <td>
                            <?php echo htmlspecialchars($row['Modelo']); ?>  
                        </td>

                        <!-- Columna Presentación del Producto -->
                        <td>
                            <?php echo htmlspecialchars($row['Presentacion_Producto']); ?>
                        </td>

                        <!-- Columna Fecha de Ingreso (adaptada para nueva estructura) -->
                        <td>
                            <span class="fecha-display">
                                <?php 
                                echo htmlspecialchars($row['DiaIngreso']) . '/' . 
                                     htmlspecialchars($row['MesIngreso']) . '/' . 
                                     htmlspecialchars($row['AnioIngreso']); 
                                ?>
                            </span>
                        </td>

                        <!-- Columna Código -->
                        <td>
                            <?php echo htmlspecialchars($row['Codigo']); ?>
                        </td>

                        <!-- Columna Precio -->
                        <td>
                            $<?php echo number_format(htmlspecialchars($row['Precio']), 2); ?>
                        </td>

                        <!-- Columna Stock Disponible -->
                        <td>
                            <?php 
                            $stock = htmlspecialchars($row['stockDisponible']);
                            if ($stock <= 5) {
                                echo '<span style="color: #dc3545; font-weight: bold;">' . $stock . ' ⚠️</span>';
                            } elseif ($stock <= 10) {
                                echo '<span style="color: #ffc107; font-weight: bold;">' . $stock . ' ⚠️</span>';
                            } else {
                                echo '<span style="color: #28a745;">' . $stock . '</span>';
                            }
                            ?>
                        </td>
                    </form>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
<?php endif; ?>
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
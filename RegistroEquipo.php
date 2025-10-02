<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Establecer zona horaria
date_default_timezone_set('America/Mexico_City');

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener información del usuario
$username = "";
$isAdmin = false;
if (isset($_SESSION["username"])) {
    $username = $_SESSION["username"];
    
    $sqlUser = "SELECT Admin FROM users WHERE usuario = ?";
    $stmt = $conexion->prepare($sqlUser);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $resultUser = $stmt->get_result();
        
        if ($resultUser && $resultUser->num_rows > 0) {
            $userData = $resultUser->fetch_assoc();
            $isAdmin = ($userData['Admin'] == 1);
        }
    }
}

// Procesar nuevo equipo para reparación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_repair'])) {
    // Obtener datos del formulario
    $nombreCliente = mysqli_real_escape_string($conexion, $_POST['nombre_cliente']);
    $telefono = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $email = mysqli_real_escape_string($conexion, $_POST['email']);
    $tipoEquipo = mysqli_real_escape_string($conexion, $_POST['tipo_equipo']);
    $marca = mysqli_real_escape_string($conexion, $_POST['marca']);
    $modelo = mysqli_real_escape_string($conexion, $_POST['modelo']);
    $numSerie = mysqli_real_escape_string($conexion, $_POST['num_serie']);
    $problema = mysqli_real_escape_string($conexion, $_POST['problema']);
    $diagnostico = mysqli_real_escape_string($conexion, $_POST['diagnostico']);
    $costoEstimado = floatval($_POST['costo_estimado']);
    $fechaIngreso = date('Y-m-d H:i:s'); // Fecha y hora actual
    $fechaSalidaEstimada = $_POST['fecha_salida_estimada']; // Nueva fecha de salida estimada

    // Validar datos
    if (empty($nombreCliente) || empty($tipoEquipo) || empty($problema) || empty($fechaSalidaEstimada)) {
        echo "<script>alert('Por favor complete los campos obligatorios.');</script>";
    } else {
        // Generar ID de equipo
        $sqlLastEquipo = "SELECT Id_Equipo FROM equipo ORDER BY LENGTH(Id_Equipo) DESC, Id_Equipo DESC LIMIT 1";
        $resultLastEquipo = $conexion->query($sqlLastEquipo);
        $lastEquipoId = $resultLastEquipo->fetch_assoc()['Id_Equipo'];
        $newEquipoId = str_pad((int)$lastEquipoId + 1, strlen($lastEquipoId), '0', STR_PAD_LEFT);

        // Generar ID de servicio
        $sqlLastServicio = "SELECT Id_Servicio FROM servicio ORDER BY LENGTH(Id_Servicio) DESC, Id_Servicio DESC LIMIT 1";
        $resultLastServicio = $conexion->query($sqlLastServicio);
        $lastServicioId = $resultLastServicio->fetch_assoc()['Id_Servicio'];
        $newServicioId = str_pad((int)$lastServicioId + 1, strlen($lastServicioId), '0', STR_PAD_LEFT);

        // Iniciar transacción
        $conexion->begin_transaction();

        try {
            // 1. Insertar en la tabla equipo
            $sqlEquipo = "INSERT INTO equipo (Id_Equipo, Marca, Modelo, Tipo, estadoActual, NumSerie, FechaIngreso, ProblemaReportado) 
                         VALUES (?, ?, ?, ?, 'En diagnóstico', ?, ?, ?)";
            $stmtEquipo = $conexion->prepare($sqlEquipo);
            $stmtEquipo->bind_param("issssss", $newEquipoId, $marca, $modelo, $tipoEquipo, $numSerie, $fechaIngreso, $problema);
            $stmtEquipo->execute();

            // 2. Insertar en la tabla servicio
            $nombreServicio = "Reparación de " . $tipoEquipo;
            $sqlServicio = "INSERT INTO servicio (Id_Servicio, nombreServicio, Descripcion, Precio) 
                           VALUES (?, ?, ?, ?)";
            $stmtServicio = $conexion->prepare($sqlServicio);
            $stmtServicio->bind_param("issd", $newServicioId, $nombreServicio, $diagnostico, $costoEstimado);
            $stmtServicio->execute();

            // 3. Insertar en la tabla reparacionequipos
            $sqlReparacion = "INSERT INTO reparacionequipos (Id_Reparacion, nombreCliente, tipoEquipo, Estado, Diagnostico, costoEstimado, Id_Servicio, Id_Equipo, FechaSalida) 
                             VALUES (?, ?, ?, 'En diagnóstico', ?, ?, ?, ?, ?)";
            $stmtReparacion = $conexion->prepare($sqlReparacion);
            $stmtReparacion->bind_param("isssdiis", $newEquipoId, $nombreCliente, $tipoEquipo, $diagnostico, $costoEstimado, $newServicioId, $newEquipoId, $fechaSalidaEstimada);
            $stmtReparacion->execute();

            // Confirmar transacción
            $conexion->commit();

            echo "<script>alert('Registro completado exitosamente.\\nID Equipo: $newEquipoId\\nID Servicio: $newServicioId');</script>";
            echo "<script>window.location.href = 'RegistroEquipo.php';</script>";
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conexion->rollback();
            echo "<script>alert('Error al registrar: " . $e->getMessage() . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
    <!--No tocar -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Equipo</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <link rel="stylesheet" href="menuStyleSheet.css" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .date-input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #dce4f4;
        }
        
        .container {
            margin-left: 240px;
            padding: 20px;
            background-color: #dce4f4;
        }
        
        .sidebar {
            width: 240px;
            height: 100%;
            background: #f0f6fe;
            position: fixed;
            z-index: 100;
        }
        
        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-width: 800px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .required:after {
            content: " *";
            color: red;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        textarea {
            height: 100px;
            resize: vertical;
        }
        
        .submit-btn {
            background-color: #283785;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            background-color: #1a2657;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        #wa {
            width: 100%;
            max-width: 800px;
            margin-left: 430px;
            background-color: #dce4f4;
            ;
        }
    </style>
</head>
<body>
    <!-- Menú lateral -->
    <aside class="sidebar">
        <img src="logoCut.png" alt="logo" id="logo" href="menu.php">
        <div id="leftside-navigation" class="nano">
            <ul class="nano-content">
            <li>
                    <a href="menu.php"><i class="fa fa-home"></i><span>Pantalla principal</span></a>
                </li>
                <li class="sub-menu">
                    <a href="javascript:void(0);"><i class="fa fa-money"></i><span>Ventas</span><i class="arrow fa fa-angle-down pull-right"></i></a>
                    <ul>
                        <li><a href="venta_Accesorios.php">Ventas de accesorios</a></li>
                        <li><a href="Inventario.php">Inventario</a></li>
                        <li><a href="ActualizacionStock.php">Actualización de stock</a></li>
                        <li><a href="ImprimirTicket.php">Impresion de Ticket</a></li>
                        <li><a href="cortecaja.php">Corte de caja</a></li>
                        <li><a href="ReporteStock.php">Reporte de stock</a></li>
                    </ul>
                </li>
                <li class="sub-menu">
                    <a href="javascript:void(0);"><i class="fa fa-print"></i><span>Impresión / Escaneo</span><i class="arrow fa fa-angle-down pull-right"></i></a>
                    <ul>
                        <li><a href="Impresion.php">Registrar pedido de impresion / copias nuevo</a></li>
                        <li><a href="Escaner.php">Registrar pedido de escaneo nuevo</a></li>
                        <li><a href="ImprEscanPendientes.php">Pedidos pendientes</a></li>
                        <li><a href="ImprEscanCompletado.php">Pedidos entregados</a></li>
                    </ul>
                </li>
                <li>
                    <li class="sub-menu">
                    <a href="javascript:void(0);"><i class="fa fa-desktop"></i><span>Ciber</span><i class="arrow fa fa-angle-down pull-right"></i></a>
                    <ul>
                        <li><a href="RegistroCiber.php">Registrar servicio nuevo</a></li>
                        <li><a href="ServicioCiber.php">Servicios activos</a></li>
                        <li><a href="ServicioCiberFinalizado.php">Servicios finalizados</a></li>
                    </ul>
                </li>
                <li></li>
                </li>
                <li class="sub-menu">
                    <a href="javascript:void(0);"><i class="fa fa-wrench"></i><span>Reparaciones</span><i class="arrow fa fa-angle-down pull-right"></i></a>
                    <ul>
                        <li><a href="RegistroEquipo.php">Registrar equipos</a></li>
                        <li><a href="EquiposPendientes.php">Equipos sin entregar</a></li>
                        <li><a href="EquiposEntregados.php">Equipos entregados</a></li>
                    </ul>   
                </li>
                <li>
                    <a href="menu.php?logout=true"><i class="fa fa-sign-out"></i><span>Cerrar sesión</span></a>
                </li>
            </ul>
        </div>
    </aside>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <!-- agregar variables de usuario logueado -->
            <h2 id="textoBajoh1" class="titulo">Bienvenido <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
    </div>

    <!-- Cuadros de acceso rápido -->
    <div class="container" id="wa">
        <div class="form-container">
            <h2>Información del Cliente</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre_cliente" class="required">Nombre del Cliente:</label>
                        <input type="text" id="nombre_cliente" name="nombre_cliente" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" id="telefono" name="telefono">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <h2>Información del Equipo</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo_equipo" class="required">Tipo de Equipo:</label>
                        <select id="tipo_equipo" name="tipo_equipo" required>
                            <option value="">Seleccione...</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Desktop">Computadora de escritorio</option>
                            <option value="Impresora">Impresora</option>
                            <option value="Monitor">Monitor</option>
                            <option value="Celular">Celular</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="marca">Marca:</label>
                        <input type="text" id="marca" name="marca">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="modelo">Modelo:</label>
                        <input type="text" id="modelo" name="modelo">
                    </div>
                    <div class="form-group">
                        <label for="num_serie">Número de Serie:</label>
                        <input type="text" id="num_serie" name="num_serie">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="problema" class="required">Problema Reportado:</label>
                    <textarea id="problema" name="problema" required></textarea>
                </div>
                
                <h2>Información de Reparación</h2>
                <div class="form-group">
                    <label for="diagnostico">Diagnóstico Inicial:</label>
                    <textarea id="diagnostico" name="diagnostico"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="costo_estimado">Costo Estimado:</label>
                        <input type="number" id="costo_estimado" name="costo_estimado" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label for="fecha_salida_estimada" class="required">Fecha de Salida Estimada:</label>
                        <input type="date" id="fecha_salida_estimada" name="fecha_salida_estimada" class="date-input" required>
                    </div>
                </div>
                
                <button type="submit" name="add_repair" class="submit-btn">Registrar Equipo</button>
            </form>
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

        // Auto-generar ID de equipo basado en tipo
        document.getElementById('tipo_equipo').addEventListener('change', function() {
            const tipo = this.value.substring(0, 3).toUpperCase();
            const randomNum = Math.floor(100 + Math.random() * 900);
            document.getElementById('num_serie').placeholder = tipo + '-' + randomNum;
        });
    </script>
</body>
</html>


<!--codigo de los cuadros de acceso rapido para que muestre las consulta
<?php 
    $host = "localhost"; // Servidor de la base de datos (generalmente "localhost" en XAMPP)
    $usuario = "root";   // Usuario de la base de datos
    $contrasena = "";    // Contraseña de la base de datos (vacía por defecto en XAMPP)
    $base_de_datos = "scyberrotsen"; // Nombre de la base de datos
    
    // Crear la conexión
    $conexion = mysqli_connect($host, $usuario, $contrasena, $base_de_datos);
    
    // Verificar si la conexión fue exitosa
    if (!$conexion) {
        die("Error de conexión: " . mysqli_connect_error());
    }

?>
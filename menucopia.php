<?php
session_start();
require_once __DIR__ . '/conexion.php'; // Incluir la conexión a la base de datos

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

$sqlConsultaCiber = "SELECT 
    Id_Servicio, 
    nombreCliente AS `Nombre Del Cliente`, 
    TIMESTAMPDIFF(HOUR, HoraInicio, HoraFinal) AS Tiempo, 
    (costoPorHora * TIMESTAMPDIFF(HOUR, HoraInicio, HoraFinal)) AS `Precio Total`,
    CASE
        WHEN HoraFinal < NOW() THEN 'Completado'
        ELSE 'Vigente'
    END AS `ServicioVigente?`
    FROM serviciointernet";

$result = $conexion->query($sqlConsultaCiber);

// Verificar si la consulta fue exitosa
if (!$result) {
    die("Error en la consulta: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
    <!--No tocar -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <link rel="stylesheet" href="menuStyleSheet.css" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /*
        Revisar menuStyleSheet.css para ver los estilos
        */
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
                        <li><a href="#">Ventas de accesorios</a></li>
                        <li><a href="Inventario.php">Inventario</a></li>
                        <li><a href="ActualizacionStock.php">Actualización de stock</a></li>
                        <li><a href="#">Impresion de Ticket</a></li>
                        <li><a href="cortecaja.php">Corte de caja</a></li>
                        <li><a href="#">Reporte de stock</a></li>
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
    <div class="quick-access">
        <div class="box">
            <h3>Ciber</h3>
            <p>No se cuenta con servicios de ciber activos
            </p>
            
        </div>
        <div class="box">
            <h3>Reparaciones</h3>
            <p>no se cuenta con servicios  de reparaciones activos</p>
        </div>
        <div class="box">
            <h3>Copiado/Escaneo</h3>
            <p>no se cuenta con servicios de copias o escaneo activos </p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Script para manejar la apertura y cierre de submenús
        $("#leftside-navigation .sub-menu > a").click(function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado del enlace
            const subMenu = $(this).next("ul");
            const arrow = $(this).find(".arrow");

            // Cierra todos los submenús excepto el actual
            $("#leftside-navigation ul ul").not(subMenu).slideUp();
            $("#leftside-navigation .arrow").not(arrow).removeClass("fa-angle-up").addClass("fa-angle-down");

            // Abre o cierra el submenú actual
            subMenu.slideToggle();

            // Cambia la flecha
            if (subMenu.is(":visible")) {
                arrow.removeClass("fa-angle-down").addClass("fa-angle-up");
            } else {
                arrow.removeClass("fa-angle-up").addClass("fa-angle-down");
            }
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
<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Procesar actualización de estado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $idServicio = $_POST['id_servicio'];
    $nuevoEstado = $_POST['nuevo_estado'];
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Actualizar estado
        $sqlUpdate = "UPDATE impresionescaner SET completado = ? WHERE Id_Servicio = ?";
        $stmt = $conexion->prepare($sqlUpdate);
        $stmt->bind_param("ii", $nuevoEstado, $idServicio);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar el estado: " . $stmt->error);
        }
        
        // Si se marcó como completado, redirigir para generar ticket
        if ($nuevoEstado == 1) {
            $conexion->commit();
            header("Location: TicketServicioImprEsc.php?id=" . $idServicio);
            exit;
        } else {
            $conexion->commit();
            $_SESSION['success_message'] = "Estado actualizado correctamente";
            header("Location: ImprEscanPendientes.php");
            exit;
        }
    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: ImprEscanPendientes.php");
        exit;
    }
}

// Obtener pedidos pendientes (completado = 0)
$sqlPendientes = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, s.Precio, 
                 i.tipoDocumento, i.numCopias, i.Color, i.costoPorCopia, i.completado
                 FROM servicio s
                 JOIN impresionescaner i ON s.Id_Servicio = i.Id_Servicio
                 WHERE i.completado = 0
                 ORDER BY s.Id_Servicio";

$resultPendientes = $conexion->query($sqlPendientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Servicios Completados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">

</head>
<body>
    <style>
            /* Botón de subir (nuevo) */
    .botonSubir {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 10vh;
    }

    /* Contenedor principal desplazado por el sidebar (nuevo) */
    .container {
        margin-left: 240px; /* Ajuste para el menú lateral */
        padding: 20px;
        
    }

    /* Estilos para centrar la tabla */
    .table-container {
        display: flex;
        justify-content: center;
        width: 100%;
        margin: 20px 0;
    }

    table {
        background-color: #f0f7ff;
        border-collapse: collapse;
        margin: 0 auto; /* Esto ayuda a centrar la tabla */
        width: auto; /* Cambiado de 100% a auto para que no ocupe todo el ancho */
        max-width: 95%; /* Asegura que la tabla no sea más ancha que el contenedor */
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
    td{
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
        max-width: 300px; /* Controla el ancho máximo de la celda */
        word-wrap: break-word; /* Permite que las palabras se rompan si son muy largas */
        white-space: normal; /* Permite que el texto continúe en la siguiente línea */
    }

    /* Estilos para inputs (nuevo) */
    input[type="text"], 
    input[type="number"] {
        width: 100%;
        padding: 5px;
        box-sizing: border-box;
    }

    /* Barra de búsqueda (nuevo) */
    .search-box {
        margin-bottom: 20px;
    }

    .search-box input {
        width: 300px;
        padding: 10px;
        font-size: 16px;
    }

    /* Botones (nuevo) */
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

    /* Mensajes de error (nuevo) */
    .error-message {
        background-color: #ffebee;
        color: #c62828;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
        text-align: center;
    }

    .error-message a {
        color: #283785;
        text-decoration: underline;
        cursor: pointer;
    }
    .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-width: 600px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        .submit-btn {
            background-color: #283785;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-btn:hover {
            background-color: #1a2657;
        }
        #wa{
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            margin-left: 550px; /* Added margin-left to move it to the right */
            flex-direction: column;
            justify-content: center;
            align-items: center;

        }
        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-width: 800px; /* Increased from 600px to 800px (added 200px) */
            margin-left: auto;
            margin-right: auto; /* Added these two lines to center the container */
        }
        table {
            background-color: #f0f7ff;
            border-collapse: collapse;
            margin: 0 auto;
            width: auto;
            max-width: 95%;
            margin-left: 400px; /* This will move the table 200px to the right */
        }
    </Style>

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
            <h2 id="textoBajoh1" class="titulo">Pedidos de Impresion/Escaneo Pendiente</h2> 
        </div>
    </div>
    <div> 
    <?php if ($resultPendientes->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Documento</th>
                        <th>Copias</th>
                        <th>Color</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($pedido = $resultPendientes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $pedido['Id_Servicio']; ?></td>
                            <td><?php echo $pedido['nombreServicio']; ?></td>
                            <td><?php echo $pedido['Descripcion']; ?></td>
                            <td><?php echo $pedido['tipoDocumento']; ?></td>
                            <td><?php echo $pedido['numCopias']; ?></td>
                            <td><?php echo $pedido['Color'] ? 'Sí' : 'No'; ?></td>
                            <td>$<?php echo number_format($pedido['Precio'], 2); ?></td>
                            <td class="status-pending">Pendiente</td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_servicio" value="<?php echo $pedido['Id_Servicio']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="1">
                                    <button type="submit" name="update_status" class="btn">
                                        Marcar como entregado
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>>
            </table>
        <?php else: ?>
            <div class="form-container">
                <p>No hay pedidos pendientes en este momento.</p>
            </div>
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
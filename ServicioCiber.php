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

// Obtener servicios activos (no finalizados y sin descripción "Finalizado")
$sqlActivos = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, s.Precio, 
               i.nombreCliente, i.HoraInicio, i.HoraFinal, i.costoPorHora, i.TipoServicio,
               TIMESTAMPDIFF(SECOND, NOW(), i.HoraFinal) AS segundosRestantes
               FROM servicio s
               JOIN serviciointernet i ON s.Id_Servicio = i.Id_Servicio
               WHERE (s.Descripcion IS NULL OR s.Descripcion != 'Finalizado')
               ORDER BY i.HoraFinal ASC";

$resultActivos = $conexion->query($sqlActivos);

// Procesar formularios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['finalizar_servicio'])) {
        $idServicio = $_POST['id_servicio'];
        
        // Iniciar transacción
        $conexion->begin_transaction();
        
        try {
            // Actualizar hora final a ahora (marcar como completado)
            $sqlFinalizar = "UPDATE serviciointernet SET HoraFinal = NOW() WHERE Id_Servicio = ?";
            $stmt = $conexion->prepare($sqlFinalizar);
            $stmt->bind_param("i", $idServicio);
            $stmt->execute();
            
            // Actualizar descripción del servicio a "Finalizado"
            $sqlDescripcion = "UPDATE servicio SET Descripcion = 'Finalizado' WHERE Id_Servicio = ?";
            $stmtDesc = $conexion->prepare($sqlDescripcion);
            $stmtDesc->bind_param("i", $idServicio);
            $stmtDesc->execute();
            
            // Confirmar transacción
            $conexion->commit();
            
            echo "<script>alert('Servicio finalizado correctamente');</script>";
            echo "<script>window.location.href = 'ServicioCiber.php';</script>";
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conexion->rollback();
            echo "<script>alert('Error al finalizar el servicio: " . addslashes($e->getMessage()) . "');</script>";
        }
    } elseif (isset($_POST['agregar_tiempo'])) {
        $idServicio = $_POST['id_servicio'];
        $minutos_extra = intval($_POST['minutos_extra']);
        
        if ($minutos_extra > 0) {
            // Iniciar transacción
            $conexion->begin_transaction();
            
            try {
                // Obtener la hora final actual
                $sqlHoraFinal = "SELECT HoraFinal FROM serviciointernet WHERE Id_Servicio = ?";
                $stmt = $conexion->prepare($sqlHoraFinal);
                $stmt->bind_param("i", $idServicio);
                $stmt->execute();
                $result = $stmt->get_result();
                $servicio = $result->fetch_assoc();
                $horaFinalActual = new DateTime($servicio['HoraFinal']);
                
                // Calcular nueva hora final
                $horaFinalNueva = clone $horaFinalActual;
                $horaFinalNueva->add(new DateInterval('PT'.$minutos_extra.'M'));
                
                // Actualizar hora final en la base de datos
                $sqlActualizar = "UPDATE serviciointernet SET HoraFinal = ? WHERE Id_Servicio = ?";
                $stmt = $conexion->prepare($sqlActualizar);
                $horaFinalStr = $horaFinalNueva->format('Y-m-d H:i:s');
                $stmt->bind_param("si", $horaFinalStr, $idServicio);
                $stmt->execute();
                
                // Actualizar precio en la tabla servicio
                $sqlPrecio = "UPDATE servicio s 
                              JOIN serviciointernet i ON s.Id_Servicio = i.Id_Servicio
                              SET s.Precio = s.Precio + (i.costoPorHora * ? / 60)
                              WHERE s.Id_Servicio = ?";
                $stmtPrecio = $conexion->prepare($sqlPrecio);
                $horas_extra = $minutos_extra / 60;
                $stmtPrecio->bind_param("di", $horas_extra, $idServicio);
                $stmtPrecio->execute();
                
                // Confirmar transacción
                $conexion->commit();
                
                echo "<script>alert('Se agregaron $minutos_extra minutos al servicio correctamente');</script>";
                echo "<script>window.location.href = 'ServicioCiber.php';</script>";
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $conexion->rollback();
                echo "<script>alert('Error al agregar tiempo: " . addslashes($e->getMessage()) . "');</script>";
            }
        } else {
            echo "<script>alert('Debe ingresar una cantidad válida de minutos');</script>";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
    <!--No tocar -->
<head>
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Ciber</title>
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
            max-width: 1000px;
            margin: 0 auto;
            margin-left: 50px; /* Added margin-left to move it to the right */
            flex-direction: column;
            justify-content: center;
            align-items: center;

        }
        #waa{
            
            width: 100%;
            margin-right:10px
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
            margin-left: 0px; /* This will move the table 200px to the right */
        }
        .badge-success {
            background-color: #28a745;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .badge-warning {
            background-color: #ffc107;
            color: black;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
        }

        .status-active {
            color: #28a745;
        }

        .status-warning {
            color: #ffc107;
        }

        .status-danger {
            color: #dc3545;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #f0f7ff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
            border-radius: 5px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
            <h2 id="textoBajoh1" class="titulo">Servicios de ciber activos</h2> 
        </div>
    </div>
        
    <div class="container" id="waa" style="float: right;">
        <div class="form-container">
            <?php if ($resultActivos->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Tiempo Restante</th>
                            <th>Costo/Hora</th>
                            <th>Total</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($servicio = $resultActivos->fetch_assoc()): 
                            $segundosRestantes = $servicio['segundosRestantes'];
                            $horasRestantes = floor($segundosRestantes / 3600);
                            $minutosRestantes = floor(($segundosRestantes % 3600) / 60);
                            $segundos = $segundosRestantes % 60;
                            
                            // Determinar clase CSS según tiempo restante
                            if ($segundosRestantes > 1800) { // 30 minutos = 1800 segundos
                                $estadoClase = "status-active";
                                $badgeClase = "badge-success";
                            } elseif ($segundosRestantes > 600) { // 10 minutos = 600 segundos
                                $estadoClase = "status-warning";
                                $badgeClase = "badge-warning";
                            } else {
                                $estadoClase = "status-danger";
                                $badgeClase = "badge-danger";
                            }
                        ?>
                            <tr>
                                <td><?php echo $servicio['Id_Servicio']; ?></td>
                                <td><?php echo htmlspecialchars($servicio['nombreCliente']); ?></td>
                                <td><?php echo htmlspecialchars($servicio['TipoServicio']); ?></td>
                                <td><?php echo date('H:i', strtotime($servicio['HoraInicio'])); ?></td>
                                <td><?php echo ($servicio['HoraFinal'] ? date('H:i', strtotime($servicio['HoraFinal'])) : '--:--'); ?></td>
                                <td class="<?php echo $estadoClase; ?>">
                                    <span class="<?php echo $badgeClase; ?>">
                                        <?php 
                                        if ($segundosRestantes > 0) {
                                            printf("%02d:%02d:%02d", $horasRestantes, $minutosRestantes, $segundos);
                                        } else {
                                            echo "00:00:00";
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($servicio['costoPorHora'], 2); ?></td>
                                <td>$<?php echo number_format($servicio['Precio'], 2); ?></td>
                                <td><?php echo htmlspecialchars($servicio['Descripcion'] ?? 'Sin descripción'); ?></td>
                                <td class="<?php echo $estadoClase; ?>">Activo</td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button onclick="abrirModal(<?php echo $servicio['Id_Servicio']; ?>)" class="btn" style="background-color: #17a2b8;">
                                            + Tiempo
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id_servicio" value="<?php echo $servicio['Id_Servicio']; ?>">
                                            <button type="submit" name="finalizar_servicio" class="btn btn-danger" 
                                                    onclick="return confirm('¿Está seguro de finalizar este servicio?')">
                                                Finalizar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="background-color: #f0f7ff; padding: 20px; border-radius: 5px; margin-top: 20px;">
                    <p>No hay servicios activos en este momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para agregar tiempo -->
    <div id="tiempoModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Agregar tiempo al servicio</h3>
            <form id="formTiempo" method="POST">
                <input type="hidden" id="modalIdServicio" name="id_servicio">
                <div class="form-group">
                    <label for="minutos_extra">Minutos a agregar:</label>
                    <input type="number" id="minutos_extra" name="minutos_extra" min="1" value="30" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn" onclick="document.getElementById('tiempoModal').style.display='none'">Cancelar</button>
                    <button type="submit" name="agregar_tiempo" class="btn" style="background-color: #28a745;">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Script para manejar la apertura y cierre de submenús
        $(document).ready(function() {
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
        });

        // Funciones para el modal de agregar tiempo
        function abrirModal(idServicio) {
            document.getElementById('modalIdServicio').value = idServicio;
            document.getElementById('tiempoModal').style.display = 'block';
        }

        // Cerrar modal al hacer clic en la X
        document.querySelector('.close').onclick = function() {
            document.getElementById('tiempoModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target == document.getElementById('tiempoModal')) {
                document.getElementById('tiempoModal').style.display = 'none';
            }
        }
    </script>
</body>
</html>
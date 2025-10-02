<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener servicios de ciber finalizados
$sql = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, s.Precio,
               si.nombreCliente, si.HoraInicio, si.HoraFinal, si.TipoServicio
        FROM servicio s
        JOIN serviciointernet si ON s.Id_Servicio = si.Id_Servicio
        WHERE s.Descripcion = 'Finalizado'
        ORDER BY si.HoraFinal DESC";

$result = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
    <!--No tocar -->
<head>
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Servicios finalizados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">

</head>
<body>
    <style>
       .container {
            margin-left: 240px;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #283785;
            color: white;
        }
        .btn-ticket {
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-ticket:hover {
            background-color: #45a049;
        }
        #wa{
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            margin-left: 10px; /* Added margin-left to move it to the right */
            flex-direction: column;
            justify-content: center;
            align-items: center;

        }
        #waa{
            width: 100%;
            margin-left:470px
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
        .btn {
            padding: 6px 12px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            opacity: 0.8;
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
            <h2 id="textoBajoh1" class="titulo">Servicios Completados</h2> 
        </div>
    </div>
        
    <div class="container" id="waa" style="float: center;">
    <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Horario</th>
                        <th>Duración</th>
                        <th>Costo</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($servicio = $result->fetch_assoc()): 
                        $horaInicio = new DateTime($servicio['HoraInicio']);
                        $horaFinal = new DateTime($servicio['HoraFinal']);
                        $duracion = $horaInicio->diff($horaFinal);
                    ?>
                        <tr>
                            <td><?= $servicio['Id_Servicio'] ?></td>
                            <td><?= htmlspecialchars($servicio['nombreCliente']) ?></td>
                            <td><?= htmlspecialchars($servicio['TipoServicio']) ?></td>
                            <td>
                                <?= $horaInicio->format('H:i') ?> - 
                                <?= $horaFinal->format('H:i') ?><br>
                                <small><?= $horaFinal->format('d/m/Y') ?></small>
                            </td>
                            <td><?= $duracion->format('%H:%I') ?> horas</td>
                            <td>$<?= number_format($servicio['Precio'], 2) ?></td>
                            <td>
                                <a href="TicketCiberFinalizado.php?id=<?= $servicio['Id_Servicio'] ?>" 
                                   class="btn-ticket">
                                    Generar Ticket
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay servicios finalizados para mostrar.</p>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toUpperCase();
            const table = document.getElementById("equiposTable");
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName("td");
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? "" : "none";
            }
        }

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
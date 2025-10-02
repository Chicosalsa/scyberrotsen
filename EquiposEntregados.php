<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}
// Mostrar mensajes
if (isset($_GET['success'])) {
    echo '<div class="alert alert-success" style="padding: 15px; margin-bottom: 20px; border: 1px solid #d6e9c6; border-radius: 4px; color: #3c763d; background-color: #dff0d8;">El estado del equipo ha sido actualizado correctamente.</div>';
}
if (isset($_GET['error'])) {
    echo '<div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; border: 1px solid #ebccd1; border-radius: 4px; color: #a94442; background-color: #f2dede;">Hubo un error al actualizar el estado del equipo.</div>';
}

// Obtener equipos entregados (estadoActual = 'Entregado')
$sqlEntregados = "SELECT e.Id_Equipo, e.Marca, e.Modelo, e.Tipo, 
                 e.NumSerie, e.FechaIngreso, e.ProblemaReportado,
                 r.nombreCliente, r.Diagnostico, r.costoEstimado, r.Id_Servicio, r.FechaSalida
                 FROM equipo e
                 JOIN reparacionequipos r ON e.Id_Equipo = r.Id_Equipo
                 WHERE e.estadoActual = 'Entregado'
                 ORDER BY r.FechaSalida DESC, e.FechaIngreso DESC";
$resultEntregados = $conexion->query($sqlEntregados);

// Check for SQL error
if ($resultEntregados === false) {
    echo "Error en la consulta: " . $conexion->error;
}
?>
<!DOCTYPE html>
<html lang="es">
<!--No tocar -->

<head>
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Reparaciones Completas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">

</head>

<body>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            margin-left: 240px;
            padding: 20px;
        }

        .sidebar {
            width: 240px;
            height: 100%;
            background: #f0f6fe;
            position: fixed;
            z-index: 100;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #283785;
            color: white;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-success {
            background-color: #4CAF50;
            color: white;
        }

        .btn-warning {
            background-color: #FFC107;
            color: black;
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-danger {
            background-color: #f44336;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-en-diagnostico {
            background-color: #FFC107;
            color: black;
        }

        .status-en-reparacion {
            background-color: #17a2b8;
            color: white;
        }

        .status-esperando-refacciones {
            background-color: #6c757d;
            color: white;
        }

        .status-listopara-entrega {
            background-color: #28a745;
            color: white;
        }

        .form-inline {
            display: inline;
        }

        .actions-cell {
            white-space: nowrap;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 300px;
            padding: 10px;
            font-size: 16px;
        }

        #wa {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            margin-left: 10px;
            /* Added margin-left to move it to the right */
            flex-direction: column;
            justify-content: center;
            align-items: center;

        }

        #waa {
            width: 100%;
            margin-left: 310px
        }

        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-width: 800px;
            /* Increased from 600px to 800px (added 200px) */
            margin-left: auto;
            margin-right: auto;
            /* Added these two lines to center the container */
        }

        table {
            background-color: #f0f7ff;
            border-collapse: collapse;
            margin: 0 auto;
            width: auto;
            max-width: 95%;
            margin-left: 0px;
            /* This will move the table 200px to the right */
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }

        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }

        .form-control {
            padding: 6px 12px;
            font-size: 14px;
            line-height: 1.42857143;
            color: #555;
            background-color: #fff;
            background-image: none;
            border: 1px solid #ccc;
            border-radius: 4px;
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
                    <a href="javascript:void(0);"><i class="fa fa-money"></i><span>Ventas</span><i
                            class="arrow fa fa-angle-down pull-right"></i></a>
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
                    <a href="javascript:void(0);"><i class="fa fa-print"></i><span>Impresión / Escaneo</span><i
                            class="arrow fa fa-angle-down pull-right"></i></a>
                    <ul>
                        <li><a href="Impresion.php">Registrar pedido de impresion / copias nuevo</a></li>
                        <li><a href="Escaner.php">Registrar pedido de escaneo nuevo</a></li>
                        <li><a href="ImprEscanPendientes.php">Pedidos pendientes</a></li>
                        <li><a href="ImprEscanCompletado.php">Pedidos entregados</a></li>
                    </ul>
                </li>
                <li>
                <li class="sub-menu">
                    <a href="javascript:void(0);"><i class="fa fa-desktop"></i><span>Ciber</span><i
                            class="arrow fa fa-angle-down pull-right"></i></a>
                    <ul>
                        <li><a href="RegistroCiber.php">Registrar servicio nuevo</a></li>
                        <li><a href="ServicioCiber.php">Servicios activos</a></li>
                        <li><a href="ServicioCiberFinalizado.php">Servicios finalizados</a></li>
                    </ul>
                </li>
                <li></li>
                </li>
                <li class="sub-menu">
                    <a href="javascript:void(0);"><i class="fa fa-wrench"></i><span>Reparaciones</span><i
                            class="arrow fa fa-angle-down pull-right"></i></a>
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
            <h2 id="textoBajoh1" class="titulo">Servicios de reparaciones completas</h2>
        </div>
    </div>

    <div class="container" id="waa" style="float: center;">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Buscar equipos..." onkeyup="searchTable()">
        </div>

        <?php if ($resultEntregados && $resultEntregados->num_rows > 0): ?>
            <table id="equiposTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Problema</th>
                        <th>Fecha Ingreso</th>
                        <th>Fecha Entrega</th>
                        <th>Costo Final</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($equipo = $resultEntregados->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $equipo['Id_Equipo']; ?></td>
                            <td><?php echo htmlspecialchars($equipo['nombreCliente']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($equipo['Marca']); ?>
                                <?php echo htmlspecialchars($equipo['Modelo']); ?><br>
                                <small><?php echo htmlspecialchars($equipo['Tipo']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($equipo['ProblemaReportado']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($equipo['FechaIngreso'])); ?></td>
                            <td><?php echo !empty($equipo['FechaSalida']) ? date('d/m/Y', strtotime($equipo['FechaSalida'])) : 'No registrada'; ?>
                            </td>
                            <td>$<?php echo number_format($equipo['costoFinal'] ?? $equipo['costoEstimado'], 2); ?></td>
                            <td>
                                <form class="form-inline" action="actualizar_estado.php" method="post">
                                    <input type="hidden" name="id_equipo" value="<?php echo $equipo['Id_Equipo']; ?>">
                                    <select name="nuevo_estado" class="form-control" onchange="confirmarCambioEstado(this)">
                                        <option value="Entregado" selected>Entregado</option>
                                        <option value="En diagnóstico">En diagnóstico</option>
                                        <option value="En reparación">En reparación</option>
                                        <option value="Esperando refacciones">Esperando refacciones</option>
                                        <option value="Listo para entrega">Listo para entrega</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <a href="DetallesEquiposEntregados.php?id=<?php echo $equipo['Id_Equipo']; ?>"
                                    class="btn btn-success" style="margin-left: 5px;">
                                    Detalles
                                </a>
                                <a href="ticket_entrega.php?id=<?php echo $equipo['Id_Equipo']; ?>" class="btn"
                                    style="background-color: #6c757d; margin-left: 5px;">
                                    Ticket
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="background-color: #f0f7ff; padding: 20px; border-radius: 5px; margin-top: 20px;">
                <p>No hay equipos entregados registrados.</p>
            </div>
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
        // Función para confirmar el cambio de estado
        function confirmarCambioEstado(selectElement) {
            const nuevoEstado = selectElement.value;
            const estadoActual = "Entregado"; // Puedes obtener el valor actual de otra manera si es necesario

            if (nuevoEstado !== estadoActual) {
                if (confirm(`¿Estás seguro de que deseas cambiar el estado de este equipo de "${estadoActual}" a "${nuevoEstado}"?`)) {
                    selectElement.form.submit();
                } else {
                    // Revertir al valor original si el usuario cancela
                    selectElement.value = estadoActual;
                }
            }
        }

        // Tu función searchTable() existente...
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
    </script>
</body>

</html>
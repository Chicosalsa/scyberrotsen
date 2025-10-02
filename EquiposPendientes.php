<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener equipos pendientes (estadoActual != 'Entregado')
$sqlPendientes = "SELECT e.Id_Equipo, e.Marca, e.Modelo, e.Tipo, e.estadoActual, 
                 e.NumSerie, e.FechaIngreso, e.ProblemaReportado,
                 r.nombreCliente, r.Diagnostico, r.costoEstimado, r.Id_Servicio, r.FechaSalida, r.Id_Reparacion
                 FROM equipo e
                 JOIN reparacionequipos r ON e.Id_Equipo = r.Id_Equipo
                 WHERE e.estadoActual != 'Entregado' and e.estadoActual != 'Cancelado'
                 ORDER BY e.FechaIngreso DESC";

$resultPendientes = $conexion->query($sqlPendientes);

// Procesar actualización de estado y fecha de salida
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['actualizar_estado'])) {
        $idEquipo = $_POST['id_equipo'];
        $nuevoEstado = $_POST['nuevo_estado'];
        $fechaSalida = ($nuevoEstado == 'Entregado') ? date('Y-m-d') : NULL;

        // Iniciar transacción
        $conexion->begin_transaction();

        try {
            // Actualizar estado en la tabla equipo
            $sqlUpdate = "UPDATE equipo SET estadoActual = ? WHERE Id_Equipo = ?";
            $stmt = $conexion->prepare($sqlUpdate);

            if ($stmt === false) {
                throw new Exception("Error en la preparación: " . $conexion->error);
            }

            $stmt->bind_param("ss", $nuevoEstado, $idEquipo);

            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar equipo: " . $stmt->error);
            }

            // Actualizar estado y fecha de salida en reparacionequipos
            $sqlUpdateReparacion = "UPDATE reparacionequipos SET Estado = ?, FechaSalida = ? WHERE Id_Equipo = ?";
            $stmtRep = $conexion->prepare($sqlUpdateReparacion);

            if ($stmtRep === false) {
                throw new Exception("Error en la preparación de reparación: " . $conexion->error);
            }

            $stmtRep->bind_param("sss", $nuevoEstado, $fechaSalida, $idEquipo);

            if (!$stmtRep->execute()) {
                throw new Exception("Error al actualizar reparación: " . $stmtRep->error);
            }

            // Confirmar transacción
            $conexion->commit();

            $_SESSION['success_message'] = "Estado actualizado correctamente a: " . $nuevoEstado;
            header("Location: EquiposPendientes.php");
            exit;

        } catch (Exception $e) {
            $conexion->rollback();
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: EquiposPendientes.php");
            exit;
        }
    } elseif (isset($_POST['actualizar_fecha'])) {
        $idReparacion = $_POST['id_reparacion'];
        $nuevaFecha = $_POST['nueva_fecha'];

        try {
            $sqlUpdateFecha = "UPDATE reparacionequipos SET FechaSalida = ? WHERE Id_Reparacion = ?";
            $stmtFecha = $conexion->prepare($sqlUpdateFecha);

            if ($stmtFecha === false) {
                throw new Exception("Error en la preparación: " . $conexion->error);
            }

            $stmtFecha->bind_param("ss", $nuevaFecha, $idReparacion);

            if (!$stmtFecha->execute()) {
                throw new Exception("Error al actualizar fecha: " . $stmtFecha->error);
            }

            $_SESSION['success_message'] = "Fecha de entrega actualizada correctamente";
            header("Location: EquiposPendientes.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: EquiposPendientes.php");
            exit;
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
    <title>Ciber Rotsen - Equipos Pendientes</title>
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
            margin-left: 260px
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
            <h2 id="textoBajoh1" class="titulo">Equipos Pendientes</h2>
        </div>
    </div>

    <div class="container" id="waa" style="float: center;">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Buscar equipos..." onkeyup="searchTable()">
        </div>

        <?php if ($resultPendientes->num_rows > 0): ?>
            <table id="equiposTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Problema</th>
                        <th>Fecha Ingreso</th>
                        <th>Estado</th>
                        <th>Costo Estimado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($equipo = $resultPendientes->fetch_assoc()): ?>
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
                            <td>
                                <span
                                    class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $equipo['estadoActual'])); ?>">
                                    <?php echo $equipo['estadoActual']; ?>
                                </span>
                                <?php if ($equipo['FechaSalida']): ?>
                                    <br><small>Entrega: <?php echo date('d/m/Y', strtotime($equipo['FechaSalida'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($equipo['costoEstimado'], 2); ?></td>
                            <td class="actions-cell">
                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="id_equipo" value="<?php echo $equipo['Id_Equipo']; ?>">
                                    <select name="nuevo_estado" class="form-control" style="padding: 6px; margin-right: 5px;">
                                        <option value="En diagnóstico">En diagnóstico</option>
                                        <option value="En reparación">En reparación</option>
                                        <option value="Esperando refacciones">Esperando refacciones</option>
                                        <option value="Listo para entrega">Listo para entrega</option>
                                        <option value="Entregado">Entregado</option>
                                        <option value="Cancelado">Cancelado</option>
                                    </select>
                                    <button type="submit" name="actualizar_estado" class="btn btn-info">Actualizar</button>
                                </form>

                                <!-- Formulario para actualizar fecha de entrega -->
                                <form method="POST" class="form-inline" style="margin-top: 5px;">
                                    <input type="hidden" name="id_reparacion" value="<?php echo $equipo['Id_Reparacion']; ?>">
                                    <input type="date" name="nueva_fecha"
                                        value="<?php echo $equipo['FechaSalida'] ? htmlspecialchars($equipo['FechaSalida']) : ''; ?>"
                                        class="form-control" style="padding: 6px; margin-right: 5px;">
                                    <button type="submit" name="actualizar_fecha" class="btn btn-warning">Cambiar fecha</button>
                                </form>

                                <a href="DetallesEquipo.php?id=<?php echo $equipo['Id_Equipo']; ?>" class="btn btn-success"
                                    style="margin-left: 5px; margin-top: 5px;">
                                    Detalles
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="background-color: #f0f7ff; padding: 20px; border-radius: 5px; margin-top: 20px;">
                <p>No hay equipos pendientes de reparación en este momento.</p>
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
    </script>
</body>

</html>
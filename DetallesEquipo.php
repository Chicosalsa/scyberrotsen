<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener el ID del equipo si se ha enviado
$idEquipo = isset($_GET['id']) ? $_GET['id'] : ''; // Cambiado a string porque Id_Equipo es varchar

// Procesar actualizaciones si se envía el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_equipo'])) {
    try {
        // Obtener datos del formulario
        $tipo = mysqli_real_escape_string($conexion, $_POST['tipo']);
        $marca = mysqli_real_escape_string($conexion, $_POST['marca']);
        $modelo = mysqli_real_escape_string($conexion, $_POST['modelo']);
        $numSerie = mysqli_real_escape_string($conexion, $_POST['numSerie']);
        $estadoActual = mysqli_real_escape_string($conexion, $_POST['estadoActual']);
        $problemaReportado = mysqli_real_escape_string($conexion, $_POST['problemaReportado']);
        $nombreCliente = mysqli_real_escape_string($conexion, $_POST['nombreCliente']);
        $estadoReparacion = mysqli_real_escape_string($conexion, $_POST['estadoReparacion']);
        $diagnostico = mysqli_real_escape_string($conexion, $_POST['diagnostico']);
        $costoEstimado = floatval($_POST['costoEstimado']);
        $fechaSalida = !empty($_POST['fechaSalida']) ? $_POST['fechaSalida'] : null;

        // Iniciar transacción
        $conexion->begin_transaction();

        // Actualizar tabla equipo
        $sqlEquipo = "UPDATE equipo SET 
                      Tipo = ?, 
                      Marca = ?, 
                      Modelo = ?, 
                      NumSerie = ?, 
                      estadoActual = ?, 
                      ProblemaReportado = ? 
                      WHERE Id_Equipo = ?";
        $stmtEquipo = $conexion->prepare($sqlEquipo);
        $stmtEquipo->bind_param("sssssss", $tipo, $marca, $modelo, $numSerie, $estadoActual, $problemaReportado, $idEquipo);
        $stmtEquipo->execute();

        // Actualizar tabla reparacionequipos
        $sqlReparacion = "UPDATE reparacionequipos SET 
                         nombreCliente = ?, 
                         Estado = ?, 
                         Diagnostico = ?, 
                         costoEstimado = ?,
                         FechaSalida = ?
                         WHERE Id_Equipo = ?";
        $stmtReparacion = $conexion->prepare($sqlReparacion);
        $stmtReparacion->bind_param("sssdss", $nombreCliente, $estadoReparacion, $diagnostico, $costoEstimado, $fechaSalida, $idEquipo);
        $stmtReparacion->execute();

        // Confirmar transacción
        $conexion->commit();

        // Mensaje de éxito
        echo "<script>alert('Información del equipo actualizada correctamente.');</script>";
        // Recargar la página para mostrar los datos actualizados
        echo "<script>window.location.href = 'DetallesEquipo.php?id=" . $idEquipo . "';</script>";
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        echo "<script>alert('Error al actualizar: " . $e->getMessage() . "');</script>";
    }
}

// Si hay un ID válido, obtener los detalles del equipo
if (!empty($idEquipo)) {
    try {
        // Consulta para obtener datos del equipo y datos del cliente desde reparacionequipos
        $query = "SELECT e.*, r.nombreCliente, r.Estado as estadoReparacion, r.Diagnostico, r.costoEstimado, r.FechaSalida, r.Id_Reparacion 
                 FROM equipo e
                 LEFT JOIN reparacionequipos r ON e.Id_Equipo = r.Id_Equipo
                 WHERE e.Id_Equipo = ?";
        
        $stmt = $conexion->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conexion->error);
        }
        
        $stmt->bind_param("s", $idEquipo); // "s" porque Id_Equipo es varchar
        
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $equipo = $result->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        die("Error al obtener detalles del equipo: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Detalles De Equipo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">
    <style>
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
        }
        
        .btn-volver {
            background-color: #2196F3;
            color: white;
            padding: 8px 16px;
            margin-top: 20px;
            display: inline-block;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
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
            <h2 id="textoBajoh1" class="titulo">Detalles del Equipo</h2> 
        </div>
    </div>
        
    <div class="container" id="wa" style="float: center;">
        <?php if (!empty($equipo)): ?>
            <div class="form-container">
                <form method="POST" action="">
                    <h2>Información del Equipo</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_equipo">ID Equipo:</label>
                            <input type="text" id="id_equipo" value="<?= htmlspecialchars($equipo['Id_Equipo'] ?? '') ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="tipo" class="required">Tipo:</label>
                            <select id="tipo" name="tipo" required>
                                <option value="Laptop" <?= ($equipo['Tipo'] == 'Laptop') ? 'selected' : '' ?>>Laptop</option>
                                <option value="Desktop" <?= ($equipo['Tipo'] == 'Desktop') ? 'selected' : '' ?>>Computadora de escritorio</option>
                                <option value="Impresora" <?= ($equipo['Tipo'] == 'Impresora') ? 'selected' : '' ?>>Impresora</option>
                                <option value="Monitor" <?= ($equipo['Tipo'] == 'Monitor') ? 'selected' : '' ?>>Monitor</option>
                                <option value="Celular" <?= ($equipo['Tipo'] == 'Celular') ? 'selected' : '' ?>>Celular</option>
                                <option value="Tablet" <?= ($equipo['Tipo'] == 'Tablet') ? 'selected' : '' ?>>Tablet</option>
                                <option value="Otro" <?= ($equipo['Tipo'] == 'Otro') ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="marca">Marca:</label>
                            <input type="text" id="marca" name="marca" value="<?= htmlspecialchars($equipo['Marca'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="modelo">Modelo:</label>
                            <input type="text" id="modelo" name="modelo" value="<?= htmlspecialchars($equipo['Modelo'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="numSerie">Número de Serie:</label>
                            <input type="text" id="numSerie" name="numSerie" value="<?= htmlspecialchars($equipo['NumSerie'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="fechaIngreso">Fecha de Ingreso:</label>
                            <input type="text" id="fechaIngreso" value="<?= htmlspecialchars($equipo['FechaIngreso'] ?? '') ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="estadoActual">Estado Actual:</label>
                            <select id="estadoActual" name="estadoActual">
                                <option value="En diagnóstico" <?= ($equipo['estadoActual'] == 'En diagnóstico') ? 'selected' : '' ?>>En diagnóstico</option>
                                <option value="En reparación" <?= ($equipo['estadoActual'] == 'En reparación') ? 'selected' : '' ?>>En reparación</option>
                                <option value="Esperando refacciones" <?= ($equipo['estadoActual'] == 'Esperando refacciones') ? 'selected' : '' ?>>Esperando refacciones</option>
                                <option value="Listo para entrega" <?= ($equipo['estadoActual'] == 'Listo para entrega') ? 'selected' : '' ?>>Listo para entrega</option>
                                <option value="Entregado" <?= ($equipo['estadoActual'] == 'Entregado') ? 'selected' : '' ?>>Entregado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="problemaReportado" class="required">Problema Reportado:</label>
                        <textarea id="problemaReportado" name="problemaReportado" required><?= htmlspecialchars($equipo['ProblemaReportado'] ?? '') ?></textarea>
                    </div>
                    
                    <h2>Información del Cliente</h2>
                    <div class="form-group">
                        <label for="nombreCliente" class="required">Nombre:</label>
                        <input type="text" id="nombreCliente" name="nombreCliente" value="<?= htmlspecialchars($equipo['nombreCliente'] ?? 'No asignado') ?>" required>
                    </div>
                    
                    <h2>Detalles de Reparación</h2>
                    <div class="form-group">
                        <label for="estadoReparacion">Estado Reparación:</label>
                        <select id="estadoReparacion" name="estadoReparacion">
                            <option value="En diagnóstico" <?= ($equipo['estadoReparacion'] == 'En diagnóstico') ? 'selected' : '' ?>>En diagnóstico</option>
                            <option value="En reparación" <?= ($equipo['estadoReparacion'] == 'En reparación') ? 'selected' : '' ?>>En reparación</option>
                            <option value="Esperando refacciones" <?= ($equipo['estadoReparacion'] == 'Esperando refacciones') ? 'selected' : '' ?>>Esperando refacciones</option>
                            <option value="Listo para entrega" <?= ($equipo['estadoReparacion'] == 'Listo para entrega') ? 'selected' : '' ?>>Listo para entrega</option>
                            <option value="Entregado" <?= ($equipo['estadoReparacion'] == 'Entregado') ? 'selected' : '' ?>>Entregado</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnostico">Diagnóstico:</label>
                        <textarea id="diagnostico" name="diagnostico"><?= htmlspecialchars($equipo['Diagnostico'] ?? 'No asignado') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fechaSalida">Fecha de Entrega:</label>
                            <input type="date" id="fechaSalida" name="fechaSalida" 
                                   value="<?= htmlspecialchars($equipo['FechaSalida'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="costoEstimado">Costo:</label>
                            <input type="number" id="costoEstimado" name="costoEstimado" step="0.01" min="0" 
                                   value="<?= number_format($equipo['costoEstimado'] ?? 0, 2, '.', '') ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_equipo" class="submit-btn">Actualizar Equipo</button>
                </form>
            </div>
        <?php else: ?>
            <p class="alert">No se encontró el equipo solicitado.</p>
        <?php endif; ?>
        
        <a href="EquiposPendientes.php" class="btn-volver">← Volver a la lista</a>
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
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

// Si hay un ID válido, obtener los detalles del equipo
if (!empty($idEquipo)) {
    try {
        // Consulta para obtener datos del equipo y datos del cliente desde reparacionequipos
        $query = "SELECT e.*, r.nombreCliente, r.Estado as estadoReparacion, r.Diagnostico, r.costoEstimado, r.FechaSalida 
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
    <title>Ciber Rotsen - Detalles De Equipo Entregados</title>
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
            color: #283785;
        }
        
        .info-field {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.5;
        }
        
        .info-textarea {
            height: 100px;
            resize: none;
            background-color: #f8f9fa;
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
            margin: 0 auto;
        }
        
        .btn-volver {
            background-color: #2196F3;
            color: white;
            padding: 8px 16px;
            margin-top: 20px;
            display: inline-block;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        
        .btn-volver:hover {
            background-color: #0b7dda;
        }
        
        .section-title {
            margin-top: 20px;
            margin-bottom: 15px;
            color: #283785;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .status-diagnostico {
            background-color: #FFC107;
            color: #333;
        }
        
        .status-reparacion {
            background-color: #17a2b8;
        }
        
        .status-esperando {
            background-color: #6c757d;
        }
        
        .status-listo {
            background-color: #28a745;
        }
        
        .status-entregado {
            background-color: #dc3545;
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
        
    <div class="container" id="wa">
        <?php if (!empty($equipo)): ?>
            <div class="form-container">
                <h3 class="section-title">Detalles de equipos entregados</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ID Equipo:</label>
                        <div class="info-field"><?= htmlspecialchars($equipo['Id_Equipo'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <label>Tipo:</label>
                        <div class="info-field"><?= htmlspecialchars($equipo['Tipo'] ?? '') ?></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Marca:</label>
                        <div class="info-field"><?= htmlspecialchars($equipo['Marca'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <label>Modelo:</label>
                        <div class="info-field"><?= htmlspecialchars($equipo['Modelo'] ?? '') ?></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Número de Serie:</label>
                        <div class="info-field"><?= htmlspecialchars($equipo['NumSerie'] ?? '') ?></div>
                    </div>
                    <div class="form-group">
                        <label>Fecha de Ingreso:</label>
                        <div class="info-field"><?= htmlspecialchars($equipo['FechaIngreso'] ?? '') ?></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Estado Actual:</label>
                    <?php
                    $estadoActual = $equipo['estadoActual'] ?? '';
                    $estadoClass = '';
                    
                    if ($estadoActual == 'En diagnóstico') {
                        $estadoClass = 'status-diagnostico';
                    } elseif ($estadoActual == 'En reparación') {
                        $estadoClass = 'status-reparacion';
                    } elseif ($estadoActual == 'Esperando refacciones') {
                        $estadoClass = 'status-esperando';
                    } elseif ($estadoActual == 'Listo para entrega') {
                        $estadoClass = 'status-listo';
                    } elseif ($estadoActual == 'Entregado') {
                        $estadoClass = 'status-entregado';
                    }
                    ?>
                    <div class="status-badge <?= $estadoClass ?>"><?= htmlspecialchars($estadoActual) ?></div>
                </div>
                
                <div class="form-group">
                    <label>Problema Reportado:</label>
                    <textarea class="info-field info-textarea" readonly><?= htmlspecialchars($equipo['ProblemaReportado'] ?? '') ?></textarea>
                </div>
                
                <h3 class="section-title">Información del Cliente</h3>
                <div class="form-group">
                    <label>Nombre:</label>
                    <div class="info-field"><?= htmlspecialchars($equipo['nombreCliente'] ?? 'No asignado') ?></div>
                </div>
                
                <h3 class="section-title">Detalles de Reparación</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de Entrega:</label>
                        <div class="info-field"><?= !empty($equipo['FechaSalida']) ? htmlspecialchars($equipo['FechaSalida']) : 'No registrada' ?></div>
                    </div>
                    <div class="form-group">
                        <label>Costo:</label>
                        <div class="info-field">$<?= number_format($equipo['costoEstimado'] ?? 0, 2) ?></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Diagnóstico:</label>
                    <textarea class="info-field info-textarea" readonly><?= htmlspecialchars($equipo['Diagnostico'] ?? 'No asignado') ?></textarea>
                </div>
            </div>
        <?php else: ?>
            <p class="alert">No se encontró el equipo solicitado.</p>
        <?php endif; ?>
        
        <a href="EquiposEntregados.php" class="btn-volver">← Volver a la lista</a>
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
<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}
date_default_timezone_set('America/Mexico_City');

// Obtener fecha actual para el corte
$fecha_actual = date('Y-m-d');

// Procesar corte de caja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_corte'])) {
    $fecha_corte = $_POST['fecha_corte'] ?? $fecha_actual;
    
    // Consulta para obtener ventas del día - ADAPTADA para nueva BD
    $sql_ventas = "SELECT v.Id_Venta, 
                   CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fechaVenta,
                   v.montoVenta, v.MontoPago,
                   s.Descripcion,
                   GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ' x $', a.Precio, ')') SEPARATOR ', ') AS productos
                   FROM venta v
                   JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
                   JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
                   JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                   WHERE CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) = ?
                   GROUP BY v.Id_Venta
                   ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC";
    
    $stmt_ventas = $conexion->prepare($sql_ventas);
    $stmt_ventas->bind_param("s", $fecha_corte);
    $stmt_ventas->execute();
    $result_ventas = $stmt_ventas->get_result();
    $ventas_dia = $result_ventas->fetch_all(MYSQLI_ASSOC);
    
    // Consulta para obtener servicios de impresión ENTREGADOS - ADAPTADA
    $sql_impresion = "SELECT i.Impresion_Id, s.nombreServicio, i.NomDoc, i.Color, 
                             i.tipoDocumentp, i.numCopias, i.PrecioTotal, 
                             CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as FechaEntrega,
                             i.MontoPago, s.Descripcion
                      FROM impresion i
                      JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                      WHERE i.Entregado = 1 AND CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) = ?
                      ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC";
    
    $stmt_impresion = $conexion->prepare($sql_impresion);
    $stmt_impresion->bind_param("s", $fecha_corte);
    $stmt_impresion->execute();
    $result_impresion = $stmt_impresion->get_result();
    $impresiones_dia = $result_impresion->fetch_all(MYSQLI_ASSOC);
    
    // Consulta para obtener servicios de escaneo ENTREGADOS - ADAPTADA
    $sql_escaner = "SELECT e.Escanner_Id, s.nombreServicio, e.TipoDocumento, 
                           e.PrecioTotal, 
                           CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as FechaEntrega,
                           e.MontoPago, s.Descripcion
                    FROM escaner e
                    JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                    WHERE e.Entregado = 1 AND CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) = ?
                    ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";
    
    $stmt_escaner = $conexion->prepare($sql_escaner);
    $stmt_escaner->bind_param("s", $fecha_corte);
    $stmt_escaner->execute();
    $result_escaner = $stmt_escaner->get_result();
    $escaneos_dia = $result_escaner->fetch_all(MYSQLI_ASSOC);
    
    // Calcular totales - USANDO montoVenta en lugar de calcular
    $total_ventas = 0;
    $efectivo_total = 0;
    $total_impresiones = 0;
    $total_escaneos = 0;
    
    foreach ($ventas_dia as $venta) {
        $total_ventas += $venta['montoVenta']; // Usar montoVenta en lugar de totalCalculado
        $efectivo_total += $venta['MontoPago'];
    }
    
    foreach ($impresiones_dia as $impresion) {
        $total_impresiones += $impresion['PrecioTotal'];
        $efectivo_total += $impresion['MontoPago'];
    }
    
    foreach ($escaneos_dia as $escaneo) {
        $total_escaneos += $escaneo['PrecioTotal'];
        $efectivo_total += $escaneo['MontoPago'];
    }
}

// Si no se ha generado corte, mostrar datos del día actual por defecto
if (!isset($ventas_dia)) {
    $fecha_corte = $fecha_actual;
    
    // Consulta para obtener ventas del día - ADAPTADA
    $sql_ventas = "SELECT v.Id_Venta, 
                   CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fechaVenta,
                   v.montoVenta, v.MontoPago,
                   s.Descripcion,
                   GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ' x $', a.Precio, ')') SEPARATOR ', ') AS productos
                   FROM venta v
                   JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
                   JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
                   JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                   WHERE CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) = ?
                   GROUP BY v.Id_Venta
                   ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC";
    
    $stmt_ventas = $conexion->prepare($sql_ventas);
    $stmt_ventas->bind_param("s", $fecha_corte);
    $stmt_ventas->execute();
    $result_ventas = $stmt_ventas->get_result();
    $ventas_dia = $result_ventas->fetch_all(MYSQLI_ASSOC);
    
    // Consulta para obtener servicios de impresión ENTREGADOS - ADAPTADA
    $sql_impresion = "SELECT i.Impresion_Id, s.nombreServicio, i.NomDoc, i.Color, 
                             i.tipoDocumentp, i.numCopias, i.PrecioTotal, 
                             CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as FechaEntrega,
                             i.MontoPago, s.Descripcion
                      FROM impresion i
                      JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                      WHERE i.Entregado = 1 AND CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) = ?
                      ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC";
    
    $stmt_impresion = $conexion->prepare($sql_impresion);
    $stmt_impresion->bind_param("s", $fecha_corte);
    $stmt_impresion->execute();
    $result_impresion = $stmt_impresion->get_result();
    $impresiones_dia = $result_impresion->fetch_all(MYSQLI_ASSOC);
    
    // Consulta para obtener servicios de escaneo ENTREGADOS - ADAPTADA
    $sql_escaner = "SELECT e.Escanner_Id, s.nombreServicio, e.TipoDocumento, 
                           e.PrecioTotal, 
                           CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as FechaEntrega,
                           e.MontoPago, s.Descripcion
                    FROM escaner e
                    JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                    WHERE e.Entregado = 1 AND CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) = ?
                    ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";
    
    $stmt_escaner = $conexion->prepare($sql_escaner);
    $stmt_escaner->bind_param("s", $fecha_corte);
    $stmt_escaner->execute();
    $result_escaner = $stmt_escaner->get_result();
    $escaneos_dia = $result_escaner->fetch_all(MYSQLI_ASSOC);
    
    // Calcular totales - USANDO montoVenta en lugar de calcular
    $total_ventas = 0;
    $efectivo_total = 0;
    $total_impresiones = 0;
    $total_escaneos = 0;
    
    foreach ($ventas_dia as $venta) {
        $total_ventas += $venta['montoVenta']; // Usar montoVenta en lugar de totalCalculado
        $efectivo_total += $venta['MontoPago'];
    }
    
    foreach ($impresiones_dia as $impresion) {
        $total_impresiones += $impresion['PrecioTotal'];
        $efectivo_total += $impresion['MontoPago'];
    }
    
    foreach ($escaneos_dia as $escaneo) {
        $total_escaneos += $escaneo['PrecioTotal'];
        $efectivo_total += $escaneo['MontoPago'];
    }
}

$total_servicios = $total_impresiones + $total_escaneos;
$total_general = $total_ventas + $total_servicios;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte de Caja - Ciber Rotsen</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <link rel="stylesheet" href="menuStyleSheet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .container-corte {
            margin-left: 250px;
            padding: 20px;
        }

        .panel-corte {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .panel-corte h2 {
            color: #283785;
            border-bottom: 2px solid #283785;
            padding-bottom: 10px;
            margin-top: 0;
        }

        .panel-corte h3 {
            color: #283785;
            margin-top: 20px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #283785;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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

        .resumen-totales {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #283785;
        }

        .total-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .total-general {
            font-weight: bold;
            font-size: 18px;
            color: #283785;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }

        .no-resultados {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin: 10px 0;
        }

        .fecha-corte {
            font-size: 16px;
            color: #666;
            margin-bottom: 15px;
            font-style: italic;
        }

        .botones-corte {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .imprimir-btn {
            background-color: #17a2b8;
            color: white;
        }

        .servicio-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .color-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
        }

        .color-true {
            background-color: #ff6b6b;
            color: white;
        }

        .color-false {
            background-color: #4ecdc4;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Menú lateral -->
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Corte de Caja</h1>
            <h2 id="textoBajoh1" class="titulo">Usuario: <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
    </div>

    <div class="container-corte">
        <!-- Panel de selección de fecha -->
        <div class="panel-corte">
            <h2>Generar Corte de Caja</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="fecha_corte">Seleccionar fecha:</label>
                    <input type="date" id="fecha_corte" name="fecha_corte" class="form-control" 
                           value="<?php echo htmlspecialchars($fecha_corte); ?>" max="<?php echo $fecha_actual; ?>">
                </div>
                <div class="botones-corte">
                    <button type="submit" name="generar_corte" class="btn btn-primary">Generar Corte</button>
                    <button type="button" onclick="location.href='cortecajapdf.php?fecha=<?php echo urlencode($fecha_corte); ?>'" class="btn imprimir-btn">Imprimir Reporte</button>
                </div>
            </form>
        </div>

        <!-- Resultados del corte -->
        <div class="panel-corte">
            <h2>Reporte de Corte</h2>
            <p class="fecha-corte">Fecha del corte: <?php echo htmlspecialchars($fecha_corte); ?></p>
            
            <!-- Ventas de Accesorios -->
            <h3>Ventas de Accesorios</h3>
            <?php if (!empty($ventas_dia)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Venta</th>
                            <th>Productos</th>
                            <th>Total Venta</th>
                            <th>Efectivo Recibido</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas_dia as $venta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($venta['Id_Venta']); ?></td>
                            <td><?php echo htmlspecialchars($venta['productos']); ?></td>
                            <td>$<?php echo number_format($venta['montoVenta'], 2); ?></td>
                            <td>$<?php echo number_format($venta['MontoPago'], 2); ?></td>
                            <td><?php echo htmlspecialchars($venta['Descripcion']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-resultados">
                    No hay ventas de accesorios registradas para esta fecha.
                </div>
            <?php endif; ?>

            <!-- Servicios de Impresión -->
            <h3>Servicios de Impresión (Entregados)</h3>
            <?php if (!empty($impresiones_dia)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Impresión</th>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Copias</th>
                            <th>Color</th>
                            <th>Precio Total</th>
                            <th>Efectivo Recibido</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($impresiones_dia as $impresion): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($impresion['Impresion_Id']); ?></td>
                            <td><?php echo htmlspecialchars($impresion['NomDoc']); ?></td>
                            <td><?php echo htmlspecialchars($impresion['tipoDocumentp']); ?></td>
                            <td><?php echo htmlspecialchars($impresion['numCopias']); ?></td>
                            <td>
                                <?php echo $impresion['Color'] ? 'Color' : 'Blanco/Negro'; ?>
                                <span class="color-badge <?php echo $impresion['Color'] ? 'color-true' : 'color-false'; ?>">
                                    <?php echo $impresion['Color'] ? 'C' : 'B/N'; ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($impresion['PrecioTotal'], 2); ?></td>
                            <td>$<?php echo number_format($impresion['MontoPago'], 2); ?></td>
                            <td><?php echo htmlspecialchars($impresion['Descripcion']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-resultados">
                    No hay servicios de impresión entregados para esta fecha.
                </div>
            <?php endif; ?>

            <!-- Servicios de Escaneo -->
            <h3>Servicios de Escaneo (Entregados)</h3>
            <?php if (!empty($escaneos_dia)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Escaneo</th>
                            <th>Tipo Documento</th>
                            <th>Precio Total</th>
                            <th>Efectivo Recibido</th>
                            <th>Fecha Entrega</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($escaneos_dia as $escaneo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($escaneo['Escanner_Id']); ?></td>
                            <td><?php echo htmlspecialchars($escaneo['TipoDocumento']); ?></td>
                            <td>$<?php echo number_format($escaneo['PrecioTotal'], 2); ?></td>
                            <td>$<?php echo number_format($escaneo['MontoPago'], 2); ?></td>
                            <td><?php echo htmlspecialchars($escaneo['FechaEntrega']); ?></td>
                            <td><?php echo htmlspecialchars($escaneo['Descripcion']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-resultados">
                    No hay servicios de escaneo entregados para esta fecha.
                </div>
            <?php endif; ?>
            
            <!-- Resumen de totales -->
            <div class="resumen-totales">
                <div class="total-item">
                    <span>Total ventas de accesorios:</span>
                    <span>$<?php echo number_format($total_ventas, 2); ?></span>
                </div>
                <div class="total-item">
                    <span>Total servicios de impresión:</span>
                    <span>$<?php echo number_format($total_impresiones, 2); ?></span>
                </div>
                <div class="total-item">
                    <span>Total servicios de escaneo:</span>
                    <span>$<?php echo number_format($total_escaneos, 2); ?></span>
                </div>
                <div class="total-item">
                    <span>Total servicios (impresión + escaneo):</span>
                    <span>$<?php echo number_format($total_servicios, 2); ?></span>
                </div>
                <div class="total-item total-general">
                    <span>Total general:</span>
                    <span>$<?php echo number_format($total_general, 2); ?></span>
                </div>
                <div class="total-item total-general">
                    <span>Total efectivo recibido:</span>
                    <span>$<?php echo number_format($efectivo_total, 2); ?></span>
                </div>
                
            </div>
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

        // Establecer fecha máxima como hoy
        document.getElementById('fecha_corte').max = new Date().toISOString().split("T")[0];
    </script>
</body>
</html>
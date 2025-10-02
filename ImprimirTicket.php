<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Procesar búsqueda
$resultados = [];
$tipo_resultados = 'todos'; // Para mostrar qué tipo de resultados se están mostrando

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Consulta por defecto (todos los tickets ordenados por fecha) - ADAPTADA
    $sql_default = "SELECT 
        'venta' as tipo,
        v.Id_Venta as id,
        CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fecha,
        v.montoVenta as monto,
        v.MontoPago as monto_pagado,
        s.Descripcion as descripcion,
        GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ')') SEPARATOR ', ') AS detalles,
        NULL as tipo_servicio,
        NULL as documento,
        NULL as color,
        NULL as copias,
        NULL as tipo_documento
    FROM venta v
    JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
    JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
    JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
    GROUP BY v.Id_Venta
    
    UNION ALL
    
    SELECT 
        'impresion' as tipo,
        i.Impresion_Id as id,
        CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as fecha,
        i.PrecioTotal as monto,
        i.MontoPago as monto_pagado,
        s.Descripcion as descripcion,
        i.NomDoc as detalles,
        'Impresión' as tipo_servicio,
        i.tipoDocumentp as documento,
        i.Color as color,
        i.numCopias as copias,
        NULL as tipo_documento
    FROM impresion i
    JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
    WHERE i.Entregado = 1
    
    UNION ALL
    
    SELECT 
        'escaner' as tipo,
        e.Escanner_Id as id,
        CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as fecha,
        e.PrecioTotal as monto,
        e.MontoPago as monto_pagado,
        s.Descripcion as descripcion,
        NULL as detalles,
        'Escaneo' as tipo_servicio,
        NULL as documento,
        NULL as color,
        NULL as copias,
        e.TipoDocumento as tipo_documento
    FROM escaner e
    JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
    WHERE e.Entregado = 1
    
    ORDER BY fecha DESC
    LIMIT 50";

    // Si hay una búsqueda específica, modificar la consulta
    if (isset($_GET['buscar_tickets'])) {
        $tipo_busqueda = $_GET['tipo_busqueda'];
        $termino = trim($_GET['termino_busqueda']);

        if (!empty($termino)) {
            switch ($tipo_busqueda) {
                case 'id':
                    // Para búsqueda por ID, usar consultas separadas para evitar problemas de UNION
                    $resultados = [];
                    
                    // Buscar en ventas - ADAPTADA
                    $sql_ventas = "SELECT 
                        'venta' as tipo,
                        v.Id_Venta as id,
                        CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fecha,
                        v.montoVenta as monto,
                        v.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ')') SEPARATOR ', ') AS detalles,
                        NULL as tipo_servicio,
                        NULL as documento,
                        NULL as color,
                        NULL as copias,
                        NULL as tipo_documento
                    FROM venta v
                    JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
                    JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
                    JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                    WHERE v.Id_Venta LIKE ?
                    GROUP BY v.Id_Venta
                    ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC";
                    
                    $stmt_ventas = $conexion->prepare($sql_ventas);
                    $param = "%$termino%";
                    $stmt_ventas->bind_param("s", $param);
                    $stmt_ventas->execute();
                    $result_ventas = $stmt_ventas->get_result();
                    $ventas = $result_ventas->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $ventas);
                    
                    // Buscar en impresiones - ADAPTADA
                    $sql_impresion = "SELECT 
                        'impresion' as tipo,
                        i.Impresion_Id as id,
                        CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as fecha,
                        i.PrecioTotal as monto,
                        i.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        i.NomDoc as detalles,
                        'Impresión' as tipo_servicio,
                        i.tipoDocumentp as documento,
                        i.Color as color,
                        i.numCopias as copias,
                        NULL as tipo_documento
                    FROM impresion i
                    JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                    WHERE i.Entregado = 1 AND i.Impresion_Id LIKE ?
                    ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC";
                    
                    $stmt_impresion = $conexion->prepare($sql_impresion);
                    $stmt_impresion->bind_param("s", $param);
                    $stmt_impresion->execute();
                    $result_impresion = $stmt_impresion->get_result();
                    $impresiones = $result_impresion->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $impresiones);
                    
                    // Buscar en escaneos - ADAPTADA
                    $sql_escaner = "SELECT 
                        'escaner' as tipo,
                        e.Escanner_Id as id,
                        CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as fecha,
                        e.PrecioTotal as monto,
                        e.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        NULL as detalles,
                        'Escaneo' as tipo_servicio,
                        NULL as documento,
                        NULL as color,
                        NULL as copias,
                        e.TipoDocumento as tipo_documento
                    FROM escaner e
                    JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                    WHERE e.Entregado = 1 AND e.Escanner_Id LIKE ?
                    ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";
                    
                    $stmt_escaner = $conexion->prepare($sql_escaner);
                    $stmt_escaner->bind_param("s", $param);
                    $stmt_escaner->execute();
                    $result_escaner = $stmt_escaner->get_result();
                    $escaneos = $result_escaner->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $escaneos);
                    
                    // Ordenar todos los resultados por fecha
                    usort($resultados, function($a, $b) {
                        return strtotime($b['fecha']) - strtotime($a['fecha']);
                    });
                    break;
                
                case 'articulo':
                    // Para búsqueda por artículo, usar consultas separadas
                    $resultados = [];
                    
                    // Buscar en ventas - ADAPTADA
                    $sql_ventas = "SELECT 
                        'venta' as tipo,
                        v.Id_Venta as id,
                        CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fecha,
                        v.montoVenta as monto,
                        v.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ')') SEPARATOR ', ') AS detalles,
                        NULL as tipo_servicio,
                        NULL as documento,
                        NULL as color,
                        NULL as copias,
                        NULL as tipo_documento
                    FROM venta v
                    JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
                    JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
                    JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                    WHERE a.Nombre LIKE ? OR a.Codigo LIKE ?
                    GROUP BY v.Id_Venta
                    ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC";
                    
                    $stmt_ventas = $conexion->prepare($sql_ventas);
                    $param = "%$termino%";
                    $stmt_ventas->bind_param("ss", $param, $param);
                    $stmt_ventas->execute();
                    $result_ventas = $stmt_ventas->get_result();
                    $ventas = $result_ventas->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $ventas);
                    
                    // Buscar en impresiones - ADAPTADA
                    $sql_impresion = "SELECT 
                        'impresion' as tipo,
                        i.Impresion_Id as id,
                        CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as fecha,
                        i.PrecioTotal as monto,
                        i.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        i.NomDoc as detalles,
                        'Impresión' as tipo_servicio,
                        i.tipoDocumentp as documento,
                        i.Color as color,
                        i.numCopias as copias,
                        NULL as tipo_documento
                    FROM impresion i
                    JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                    WHERE i.Entregado = 1 AND (i.NomDoc LIKE ? OR i.tipoDocumentp LIKE ?)
                    ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC";
                    
                    $stmt_impresion = $conexion->prepare($sql_impresion);
                    $stmt_impresion->bind_param("ss", $param, $param);
                    $stmt_impresion->execute();
                    $result_impresion = $stmt_impresion->get_result();
                    $impresiones = $result_impresion->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $impresiones);
                    
                    // Buscar en escaneos - ADAPTADA
                    $sql_escaner = "SELECT 
                        'escaner' as tipo,
                        e.Escanner_Id as id,
                        CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as fecha,
                        e.PrecioTotal as monto,
                        e.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        NULL as detalles,
                        'Escaneo' as tipo_servicio,
                        NULL as documento,
                        NULL as color,
                        NULL as copias,
                        e.TipoDocumento as tipo_documento
                    FROM escaner e
                    JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                    WHERE e.Entregado = 1 AND e.TipoDocumento LIKE ?
                    ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";
                    
                    $stmt_escaner = $conexion->prepare($sql_escaner);
                    $stmt_escaner->bind_param("s", $param);
                    $stmt_escaner->execute();
                    $result_escaner = $stmt_escaner->get_result();
                    $escaneos = $result_escaner->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $escaneos);
                    
                    // Ordenar todos los resultados por fecha
                    usort($resultados, function($a, $b) {
                        return strtotime($b['fecha']) - strtotime($a['fecha']);
                    });
                    break;
                
                case 'fecha':
                    // Para búsqueda por fecha, usar consultas separadas - ADAPTADA
                    $resultados = [];
                    
                    // Buscar en ventas
                    $sql_ventas = "SELECT 
                        'venta' as tipo,
                        v.Id_Venta as id,
                        CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fecha,
                        v.montoVenta as monto,
                        v.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ')') SEPARATOR ', ') AS detalles,
                        NULL as tipo_servicio,
                        NULL as documento,
                        NULL as color,
                        NULL as copias,
                        NULL as tipo_documento
                    FROM venta v
                    JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
                    JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
                    JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                    WHERE CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) = ?
                    GROUP BY v.Id_Venta
                    ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC";
                    
                    $stmt_ventas = $conexion->prepare($sql_ventas);
                    $stmt_ventas->bind_param("s", $termino);
                    $stmt_ventas->execute();
                    $result_ventas = $stmt_ventas->get_result();
                    $ventas = $result_ventas->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $ventas);
                    
                    // Buscar en impresiones
                    $sql_impresion = "SELECT 
                        'impresion' as tipo,
                        i.Impresion_Id as id,
                        CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as fecha,
                        i.PrecioTotal as monto,
                        i.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        i.NomDoc as detalles,
                        'Impresión' as tipo_servicio,
                        i.tipoDocumentp as documento,
                        i.Color as color,
                        i.numCopias as copias,
                        NULL as tipo_documento
                    FROM impresion i
                    JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                    WHERE i.Entregado = 1 AND CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) = ?
                    ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC";
                    
                    $stmt_impresion = $conexion->prepare($sql_impresion);
                    $stmt_impresion->bind_param("s", $termino);
                    $stmt_impresion->execute();
                    $result_impresion = $stmt_impresion->get_result();
                    $impresiones = $result_impresion->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $impresiones);
                    
                    // Buscar en escaneos
                    $sql_escaner = "SELECT 
                        'escaner' as tipo,
                        e.Escanner_Id as id,
                        CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as fecha,
                        e.PrecioTotal as monto,
                        e.MontoPago as monto_pagado,
                        s.Descripcion as descripcion,
                        NULL as detalles,
                        'Escaneo' as tipo_servicio,
                        NULL as documento,
                        NULL as color,
                        NULL as copias,
                        e.TipoDocumento as tipo_documento
                    FROM escaner e
                    JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                    WHERE e.Entregado = 1 AND CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) = ?
                    ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";
                    
                    $stmt_escaner = $conexion->prepare($sql_escaner);
                    $stmt_escaner->bind_param("s", $termino);
                    $stmt_escaner->execute();
                    $result_escaner = $stmt_escaner->get_result();
                    $escaneos = $result_escaner->fetch_all(MYSQLI_ASSOC);
                    $resultados = array_merge($resultados, $escaneos);
                    
                    // Ordenar todos los resultados por fecha
                    usort($resultados, function($a, $b) {
                        return strtotime($b['fecha']) - strtotime($a['fecha']);
                    });
                    break;
            }
            $tipo_resultados = 'busqueda';
        } else {
            // Si no hay término de búsqueda, mostrar todos con UNION
            try {
                $stmt = $conexion->prepare($sql_default);
                $stmt->execute();
                $result = $stmt->get_result();
                $resultados = $result->fetch_all(MYSQLI_ASSOC);
            } catch (Exception $e) {
                // Si hay error con UNION, usar consultas separadas
                $resultados = [];
                
                // Ventas - ADAPTADA
                $sql_ventas = "SELECT 
                    'venta' as tipo,
                    v.Id_Venta as id,
                    CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fecha,
                    v.montoVenta as monto,
                    v.MontoPago as monto_pagado,
                    s.Descripcion as descripcion,
                    GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ')') SEPARATOR ', ') AS detalles,
                    NULL as tipo_servicio,
                    NULL as documento,
                    NULL as color,
                    NULL as copias,
                    NULL as tipo_documento
                FROM venta v
                JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
                JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
                JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                GROUP BY v.Id_Venta
                ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC
                LIMIT 20";
                
                $stmt_ventas = $conexion->prepare($sql_ventas);
                $stmt_ventas->execute();
                $result_ventas = $stmt_ventas->get_result();
                $ventas = $result_ventas->fetch_all(MYSQLI_ASSOC);
                $resultados = array_merge($resultados, $ventas);
                
                // Impresiones - ADAPTADA
                $sql_impresion = "SELECT 
                    'impresion' as tipo,
                    i.Impresion_Id as id,
                    CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as fecha,
                    i.PrecioTotal as monto,
                    i.MontoPago as monto_pagado,
                    s.Descripcion as descripcion,
                    i.NomDoc as detalles,
                    'Impresión' as tipo_servicio,
                    i.tipoDocumentp as documento,
                    i.Color as color,
                    i.numCopias as copias,
                    NULL as tipo_documento
                FROM impresion i
                JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                WHERE i.Entregado = 1
                ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC
                LIMIT 15";
                
                $stmt_impresion = $conexion->prepare($sql_impresion);
                $stmt_impresion->execute();
                $result_impresion = $stmt_impresion->get_result();
                $impresiones = $result_impresion->fetch_all(MYSQLI_ASSOC);
                $resultados = array_merge($resultados, $impresiones);
                
                // Escaneos - ADAPTADA
                $sql_escaner = "SELECT 
                    'escaner' as tipo,
                    e.Escanner_Id as id,
                    CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as fecha,
                    e.PrecioTotal as monto,
                    e.MontoPago as monto_pagado,
                    s.Descripcion as descripcion,
                    NULL as detalles,
                    'Escaneo' as tipo_servicio,
                    NULL as documento,
                    NULL as color,
                    NULL as copias,
                    e.TipoDocumento as tipo_documento
                FROM escaner e
                JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                WHERE e.Entregado = 1
                ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC
                LIMIT 15";
                
                $stmt_escaner = $conexion->prepare($sql_escaner);
                $stmt_escaner->execute();
                $result_escaner = $stmt_escaner->get_result();
                $escaneos = $result_escaner->fetch_all(MYSQLI_ASSOC);
                $resultados = array_merge($resultados, $escaneos);
                
                // Ordenar todos los resultados por fecha
                usort($resultados, function($a, $b) {
                    return strtotime($b['fecha']) - strtotime($a['fecha']);
                });
                
                // Limitar a 50 resultados
                $resultados = array_slice($resultados, 0, 50);
            }
        }
    } else {
        // Consulta por defecto sin búsqueda
        try {
            $stmt = $conexion->prepare($sql_default);
            $stmt->execute();
            $result = $stmt->get_result();
            $resultados = $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            // Si hay error, usar método alternativo
            $resultados = [];
            error_log("Error en consulta UNION: " . $e->getMessage());
        }
    }
}

// Redireccionar a ImprimirTicket.php si se hace clic en limpiar
if (isset($_GET['limpiar_busqueda'])) {
    header("Location: ImprimirTicket.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Tickets - Ciber Rotsen</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <link rel="stylesheet" href="menuStyleSheet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .container-busqueda {
            margin-left: 250px;
            padding: 20px;
        }

        .panel-busqueda {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .panel-busqueda h2 {
            color: #283785;
            border-bottom: 2px solid #283785;
            padding-bottom: 10px;
            margin-top: 0;
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

        .btn-secondary {
            background-color: #6c757d;
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

        .ticket-link {
            color: #2b542c;
            text-decoration: underline;
            font-weight: bold;
        }

        .ticket-link:hover {
            color: #1e3a1e;
            text-decoration: none;
        }

        .busqueda-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .busqueda-container select {
            width: 150px;
        }

        .busqueda-container input {
            flex: 1;
        }

        .no-resultados {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .ultimos-tickets {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            font-style: italic;
        }

        .botones-busqueda {
            display: flex;
            gap: 10px;
        }

        .tipo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 5px;
        }

        .badge-venta {
            background-color: #4ecdc4;
            color: white;
        }

        .badge-impresion {
            background-color: #ff6b6b;
            color: white;
        }

        .badge-escaner {
            background-color: #45b7d1;
            color: white;
        }

        .detalles-servicio {
            font-size: 12px;
            color: #666;
        }

        .color-info {
            font-size: 11px;
            color: #888;
        }
    </style>
</head>
<body>
    <!-- Menú lateral -->
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Buscar Tickets de Venta y Servicios</h1>
            <h2 id="textoBajoh1" class="titulo">Usuario: <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
    </div>

    <div class="container-busqueda">
        <!-- Panel de búsqueda -->
        <div class="panel-busqueda">
            <h2>Buscar Tickets</h2>
            <form method="GET">
                <div class="form-group">
                    <label for="tipo_busqueda">Buscar por:</label>
                    <div class="busqueda-container">
                        <select id="tipo_busqueda" name="tipo_busqueda" class="form-control">
                            <option value="id" <?php echo (isset($_GET['tipo_busqueda']) && $_GET['tipo_busqueda'] === 'id') ? 'selected' : ''; ?>>ID de Ticket</option>
                            <option value="articulo" <?php echo (isset($_GET['tipo_busqueda']) && $_GET['tipo_busqueda'] === 'articulo') ? 'selected' : ''; ?>>Artículo/Servicio</option>
                            <option value="fecha" <?php echo (isset($_GET['tipo_busqueda']) && $_GET['tipo_busqueda'] === 'fecha') ? 'selected' : ''; ?>>Fecha</option>
                        </select>
                        <input type="text" id="termino_busqueda" name="termino_busqueda" class="form-control" 
                               placeholder="<?php 
                                   echo isset($_GET['tipo_busqueda']) ? 
                                       ($_GET['tipo_busqueda'] === 'id' ? 'Ej: 1, 2, 3' : 
                                       ($_GET['tipo_busqueda'] === 'articulo' ? 'Ej: Mouse, PDF, Word' : 'Ej: 2025-09-30')) : 
                                       'Ej: 1, Mouse, 2025-09-30'; 
                               ?>" 
                               value="<?php echo isset($_GET['termino_busqueda']) ? htmlspecialchars($_GET['termino_busqueda']) : ''; ?>">
                        <div class="botones-busqueda">
                            <button type="submit" name="buscar_tickets" class="btn btn-primary">Buscar</button>
                            <button type="submit" name="limpiar_busqueda" class="btn btn-secondary">Limpiar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Resultados de búsqueda -->
        <div class="panel-busqueda">
            <h2>Resultados</h2>
            <?php if ($tipo_resultados === 'todos'): ?>
                <p class="ultimos-tickets">Mostrando los últimos tickets y servicios registrados (ordenados por fecha más reciente):</p>
            <?php endif; ?>
            
            <?php if (!empty($resultados)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Detalles</th>
                            <th>Precio Total</th>
                            <th>Efectivo Recibido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $ticket): ?>
                        <tr>
                            <td>
                                <?php if ($ticket['tipo'] === 'venta'): ?>
                                    <span class="tipo-badge badge-venta">VENTA</span>
                                <?php elseif ($ticket['tipo'] === 'impresion'): ?>
                                    <span class="tipo-badge badge-impresion">IMPRESIÓN</span>
                                <?php else: ?>
                                    <span class="tipo-badge badge-escaner">ESCANEO</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['fecha']); ?></td>
                            <td>
                                <?php if ($ticket['tipo'] === 'venta'): ?>
                                    <?php echo htmlspecialchars($ticket['detalles']); ?>
                                <?php elseif ($ticket['tipo'] === 'impresion'): ?>
                                    <strong><?php echo htmlspecialchars($ticket['documento']); ?></strong><br>
                                    <span class="detalles-servicio">
                                        <?php echo htmlspecialchars($ticket['detalles']); ?><br>
                                        <span class="color-info">
                                            <?php echo $ticket['color'] ? 'Color' : 'Blanco/Negro'; ?> - 
                                            <?php echo $ticket['copias']; ?> copias
                                        </span>
                                    </span>
                                <?php else: ?>
                                    <strong>Escaneo</strong><br>
                                    <span class="detalles-servicio">
                                        <?php echo htmlspecialchars($ticket['tipo_documento']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>$<?php echo number_format($ticket['monto'], 2); ?></td>
                            <td>$<?php echo number_format($ticket['monto_pagado'], 2); ?></td>
                            <td>
                                <?php if ($ticket['tipo'] === 'venta'): ?>
                                    <a href="ticketventa.php?id=<?php echo $ticket['id']; ?>" target="_blank" class="ticket-link">
                                        Ver Ticket
                                    </a>
                                <?php else: ?>
                                    <span class="detalles-servicio">Servicio</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (isset($_GET['buscar_tickets'])): ?>
                <div class="no-resultados">
                    No se encontraron tickets o servicios que coincidan con la búsqueda.
                </div>
            <?php else: ?>
                <div class="no-resultados">
                    No hay tickets o servicios registrados.
                </div>
            <?php endif; ?>
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

        // Actualizar placeholder según tipo de búsqueda seleccionado
        $('#tipo_busqueda').change(function() {
            const tipo = $(this).val();
            let placeholder = '';
            
            switch(tipo) {
                case 'id':
                    placeholder = 'Ej: 1, 2, 3';
                    break;
                case 'articulo':
                    placeholder = 'Ej: Mouse, PDF, Word';
                    break;
                case 'fecha':
                    placeholder = 'Ej: 2025-09-30';
                    break;
            }
            
            $('#termino_busqueda').attr('placeholder', placeholder);
        });
    </script>
</body>
</html>
<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Establecer zona horaria
date_default_timezone_set('America/Mexico_City');

// Obtener ID de venta desde la URL
$idVenta = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($idVenta)) {
    die("No se proporcionó ID de venta");
}

// Consultar datos de la venta con información del usuario (adaptado para nueva BD)
$sqlVenta = "SELECT v.*, s.Descripcion, s.User_Id, u.Usuario 
             FROM venta v 
             JOIN servicio s ON v.Id_Servicio = s.Id_Servicio 
             JOIN users u ON s.User_Id = u.User_Id
             WHERE v.Id_Venta = ?";
$stmtVenta = $conexion->prepare($sqlVenta);
$stmtVenta->bind_param("s", $idVenta);
$stmtVenta->execute();
$resultVenta = $stmtVenta->get_result();

if ($resultVenta->num_rows === 0) {
    die("No se encontró la venta especificada");
}

$venta = $resultVenta->fetch_assoc();

// Consultar productos vendidos
$sqlProductos = "SELECT va.cantidad, a.Nombre, a.Marca, a.Modelo, a.Precio, a.Presentacion_Producto
                 FROM ventaaccesorios va
                 JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
                 WHERE va.Id_Venta = ?";
$stmtProductos = $conexion->prepare($sqlProductos);
$stmtProductos->bind_param("s", $idVenta);
$stmtProductos->execute();
$resultProductos = $stmtProductos->get_result();

// Formatear fecha usando los campos separados de la nueva BD
$fechaFormateada = $venta['DiaVenta'] . '/' . $venta['MesVenta'] . '/' . $venta['AnioVenta'];

// Usar montoVenta de la tabla venta en lugar de calcular
$totalVenta = $venta['montoVenta'];
$efectivoRecibido = $venta['MontoPago'];
$cambio = $efectivoRecibido - $totalVenta;

// Configurar para impresión
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Venta - Ciber Rotsen</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 5px;
        }
        .ticket {
            border: 1px dashed #ccc;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header img {
            max-width: 150px;
        }
        .info-venta {
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 5px;
        }
        .info-venta p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        th, td {
            padding: 3px 0;
            border-bottom: 1px dashed #ddd;
        }
        th {
            text-align: left;
        }
        .efectivo {
            text-align: right;
            margin-top: 5px;
            font-weight: bold;
        }
        .total {
            font-weight: bold;
            font-size: 14px;
            text-align: right;
            margin-top: 5px;
            border-top: 1px dashed #000;
            padding-top: 5px;
        }
        .cambio {
            text-align: right;
            margin-top: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 10px;
            border-top: 1px dashed #000;
            padding-top: 5px;
        }
        .producto-nombre {
            font-weight: bold;
        }
        .producto-detalles {
            font-size: 10px;
            color: #666;
        }
        @media print {
            body {
                width: 80mm;
            }
            .no-print {
                display: none;
            }
            .ticket {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h2>Ciber Rotsen</h2>
            <p>Tepaxtitlan, Vicente Guerrero</p>
            <p>Tel: +52 733 156 4588</p>
        </div>

        <div class="info-venta">
            <p><strong>ID de venta #:</strong> <?php echo htmlspecialchars($venta['Id_Venta']); ?></p>
            <p><strong>Fecha:</strong> <?php echo $fechaFormateada; ?></p>
            <p><strong>Atendido por:</strong> <?php echo htmlspecialchars($venta['Usuario']); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Descripción</th>
                    <th>P.Unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotalCalculado = 0;
                while ($producto = $resultProductos->fetch_assoc()): 
                    $subtotalProducto = $producto['Precio'] * $producto['cantidad'];
                    $subtotalCalculado += $subtotalProducto;
                ?>
                <tr>
                    <td><?php echo $producto['cantidad']; ?></td>
                    <td>
                        <div class="producto-nombre">
                            <?php echo htmlspecialchars($producto['Nombre']); ?>
                        </div>
                        <div class="producto-detalles">
                            <?php 
                            echo htmlspecialchars($producto['Marca']);
                            if (!empty($producto['Modelo'])) {
                                echo ' ' . htmlspecialchars($producto['Modelo']);
                            }
                            if (!empty($producto['Presentacion_Producto'])) {
                                echo ' - ' . htmlspecialchars($producto['Presentacion_Producto']);
                            }
                            ?>
                        </div>
                    </td>
                    <td>$<?php echo number_format($producto['Precio'], 2); ?></td>
                    <td>$<?php echo number_format($subtotalProducto, 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="total">
            Total: $<?php echo number_format($totalVenta, 2); ?>
        </div>

        <div class="efectivo">
            Efectivo recibido: $<?php echo number_format($efectivoRecibido, 2); ?>
        </div>

        <?php if ($cambio > 0): ?>
        <div class="cambio">
            Cambio: $<?php echo number_format($cambio, 2); ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p><strong>¡Gracias por su compra!</strong></p>
            <p>Venta de accesorios</p>
            <p>Este ticket no es válido como factura fiscal</p>
            <button onclick="window.print()" class="no-print">Imprimir Ticket</button>
        </div>
    </div>

    <script>
        // Auto-impresión al cargar (opcional)
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>
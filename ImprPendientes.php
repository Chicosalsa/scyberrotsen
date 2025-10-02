<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}
date_default_timezone_set('America/Mexico_City');

// Procesar actualización de estado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $idServicio = $_POST['id_servicio'];
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Actualizar el estado en la tabla impresion a Entregado = 1
        $sqlUpdateImpresion = "UPDATE impresion SET Entregado = 1 WHERE Id_Servicio = ?";
        $stmtImpresion = $conexion->prepare($sqlUpdateImpresion);
        $stmtImpresion->bind_param("i", $idServicio);
        
        if (!$stmtImpresion->execute()) {
            throw new Exception("Error al actualizar el estado de impresión: " . $stmtImpresion->error);
        }
        
        // Redirigir para generar ticket
        $conexion->commit();
        header("Location: TicketServicioImpr.php?id=" . $idServicio);
        exit;
        
    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: ImprPendientes.php");
        exit;
    }
}

// Obtener pedidos de impresión pendientes (Entregado = 0) - ADAPTADO para nueva BD
$sqlPendientes = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, 
                 i.NomDoc, i.Color, i.tipoDocumento, i.numCopias, i.PrecioTotal, 
                 CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as FechaEntrega
                 FROM servicio s
                 JOIN impresion i ON s.Id_Servicio = i.Id_Servicio
                 WHERE i.Entregado = 0
                 ORDER BY i.AnioEntrega ASC, i.MesEntrega ASC, i.DiaEntrega ASC";

$resultPendientes = $conexion->query($sqlPendientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Impresiones Pendientes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">
    <style>
        .container {
            margin-left: 250px;
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #283785;
            color: white;
        }
        
        .status-pending {
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .status-urgent {
            color: #dc3545;
            font-weight: bold;
            background-color: #ffe6e6;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .status-today {
            color: #ff9800;
            font-weight: bold;
            background-color: #fff3cd;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .btn {
            background-color: #283785;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background-color: #1a2657;
        }
        
        .btn-urgent {
            background-color: #dc3545;
        }
        
        .btn-urgent:hover {
            background-color: #c82333;
        }
        
        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
        }
        
        .no-pendientes {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        
        .color-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .color-true {
            background-color: #ff6b6b;
            color: white;
        }
        
        .color-false {
            background-color: #4ecdc4;
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .urgent-row {
            background-color: #ffe6e6 !important;
            border-left: 4px solid #dc3545;
        }
        
        .today-row {
            background-color: #fff3cd !important;
            border-left: 4px solid #ff9800;
        }
        
        .urgent-row:hover {
            background-color: #ffd6d6 !important;
        }
        
        .today-row:hover {
            background-color: #ffe8a3 !important;
        }
    </style>
</head>
<body>

   <?php include 'sidebar.php'; ?>
   
    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <h2 id="textoBajoh1" class="titulo">Pedidos de Impresión Pendientes</h2> 
        </div>

        <?php if ($resultPendientes->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Servicio</th>
                        <th>Descripción</th>
                        <th>Documento</th>
                        <th>Tipo</th>
                        <th>Copias</th>
                        <th>Color</th>
                        <th>Precio</th>
                        <th>Fecha Entrega</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $fechaHoy = date('Y-m-d');
                    $fechaManana = date('Y-m-d', strtotime('+1 day'));
                    
                    while ($pedido = $resultPendientes->fetch_assoc()): 
                        $fechaEntrega = $pedido['FechaEntrega'];
                        $claseFila = '';
                        $estadoTexto = 'Pendiente';
                        $claseEstado = 'status-pending';
                        $claseBoton = 'btn';
                        
                        // Determinar si es urgente (hoy) o próximo (mañana)
                        if ($fechaEntrega == $fechaHoy) {
                            $claseFila = 'urgent-row';
                            $estadoTexto = 'URGENTE - Hoy';
                            $claseEstado = 'status-urgent';
                            $claseBoton = 'btn btn-urgent';
                        } elseif ($fechaEntrega == $fechaManana) {
                            $claseFila = 'today-row';
                            $estadoTexto = 'Mañana';
                            $claseEstado = 'status-today';
                        }
                    ?>
                        <tr class="<?php echo $claseFila; ?>">
                            <td><?php echo $pedido['Id_Servicio']; ?></td>
                            <td><?php echo $pedido['nombreServicio']; ?></td>
                            <td><?php echo $pedido['Descripcion']; ?></td>
                            <td><?php echo htmlspecialchars($pedido['NomDoc']); ?></td>
                            <td><?php echo $pedido['tipoDocumento']; ?></td>
                            <td><?php echo $pedido['numCopias']; ?></td>
                            <td>
                                <?php echo $pedido['Color'] ? 'Color' : 'Blanco/Negro'; ?>
                                <span class="color-badge <?php echo $pedido['Color'] ? 'color-true' : 'color-false'; ?>">
                                    <?php echo $pedido['Color'] ? 'C' : 'B/N'; ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($pedido['PrecioTotal'], 2); ?></td>
                            <td><?php echo $fechaEntrega; ?></td>
                            <td class="<?php echo $claseEstado; ?>"><?php echo $estadoTexto; ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_servicio" value="<?php echo $pedido['Id_Servicio']; ?>">
                                    <button type="submit" name="update_status" class="<?php echo $claseBoton; ?>">
                                        Marcar como entregado
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-pendientes">
                <p>No hay pedidos de impresión pendientes en este momento.</p>
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

        // Confirmación antes de marcar como entregado
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form[method="POST"]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('¿Está seguro de marcar este pedido como entregado?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
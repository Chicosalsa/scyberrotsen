<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Procesar actualización de estado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $idServicio = $_POST['id_servicio'];
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Actualizar el estado en la tabla escaner a Entregado = 1
        $sqlUpdateEscaneo = "UPDATE escaner SET Entregado = 1 WHERE Id_Servicio = ?";
        $stmtEscaneo = $conexion->prepare($sqlUpdateEscaneo);
        $stmtEscaneo->bind_param("i", $idServicio);
        
        if (!$stmtEscaneo->execute()) {
            throw new Exception("Error al actualizar el estado de escaneo: " . $stmtEscaneo->error);
        }
        
        // Redirigir para generar ticket
        $conexion->commit();
        header("Location: TicketServicioEsc.php?id=" . $idServicio);
        exit;
        
    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: EscanPendientes.php");
        exit;
    }
}

// Obtener pedidos de escaneo pendientes (Entregado = 0) - CORREGIDO
$sqlPendientes = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, 
                 e.TipoDocumento, e.PrecioTotal, e.DiaEntrega, e.MesEntrega, e.AnioEntrega
                 FROM servicio s
                 JOIN escaner e ON s.Id_Servicio = e.Id_Servicio
                 WHERE e.Entregado = 0
                 ORDER BY e.AnioEntrega ASC, e.MesEntrega ASC, e.DiaEntrega ASC";

$resultPendientes = $conexion->query($sqlPendientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Escaneos Pendientes</title>
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
        
        .date-cell {
            white-space: nowrap;
        }
        
        .urgent {
            background-color: #fff3cd;
        }
        
        .overdue {
            background-color: #f8d7da;
        }
    </style>
</head>
<body>

   <?php include 'sidebar.php'; ?>
   
    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <h2 id="textoBajoh1" class="titulo">Pedidos de Escaneo Pendientes</h2> 
        </div>

        <?php if ($resultPendientes && $resultPendientes->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Servicio</th>
                        <th>Descripción</th>
                        <th>Tipo de Documento</th>
                        <th>Precio</th>
                        <th>Fecha de Entrega</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($pedido = $resultPendientes->fetch_assoc()): 
                        // Formatear la fecha a partir de los campos separados
                        $fechaEntrega = sprintf("%02d/%02d/%04d", 
                            $pedido['DiaEntrega'], 
                            $pedido['MesEntrega'], 
                            $pedido['AnioEntrega']
                        );
                        
                        // Determinar si está vencido o es urgente
                        $fechaHoy = date('Y-m-d');
                        $fechaEntregaComparar = sprintf("%04d-%02d-%02d", 
                            $pedido['AnioEntrega'], 
                            $pedido['MesEntrega'], 
                            $pedido['DiaEntrega']
                        );
                        
                        $rowClass = '';
                        if ($fechaEntregaComparar < $fechaHoy) {
                            $rowClass = 'overdue'; // Vencido
                        } elseif ($fechaEntregaComparar == $fechaHoy) {
                            $rowClass = 'urgent'; // Para hoy - urgente
                        }
                    ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo $pedido['Id_Servicio']; ?></td>
                            <td><?php echo $pedido['nombreServicio']; ?></td>
                            <td><?php echo $pedido['Descripcion']; ?></td>
                            <td><?php echo $pedido['TipoDocumento']; ?></td>
                            <td>$<?php echo number_format($pedido['PrecioTotal'], 2); ?></td>
                            <td class="date-cell"><?php echo $fechaEntrega; ?></td>
                            <td class="status-pending">Pendiente</td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_servicio" value="<?php echo $pedido['Id_Servicio']; ?>">
                                    <button type="submit" name="update_status" class="btn">
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
                <p>No hay pedidos de escaneo pendientes en este momento.</p>
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
    </script>
</body>
</html>
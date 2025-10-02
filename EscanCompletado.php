<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Procesar devolución a pendiente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['return_to_pending'])) {
    $idServicio = $_POST['id_servicio'];
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Actualizar el estado en la tabla escaner a Entregado = 0
        $sqlUpdateEscaneo = "UPDATE escaner SET Entregado = 0 WHERE Id_Servicio = ?";
        $stmtEscaneo = $conexion->prepare($sqlUpdateEscaneo);
        $stmtEscaneo->bind_param("i", $idServicio);
        
        if (!$stmtEscaneo->execute()) {
            throw new Exception("Error al actualizar el estado de escaneo: " . $stmtEscaneo->error);
        }
        
        $conexion->commit();
        $_SESSION['success_message'] = "El pedido ha sido devuelto a pendiente correctamente.";
        header("Location: EscanCompletado.php");
        exit;
        
    } catch (Exception $e) {
        $conexion->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: EscanCompletado.php");
        exit;
    }
}

// Obtener pedidos de escaneo completados (Entregado = 1) - CORREGIDO
$sqlCompletados = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, 
                  e.TipoDocumento, e.PrecioTotal, e.DiaEntrega, e.MesEntrega, e.AnioEntrega
                  FROM servicio s
                  JOIN escaner e ON s.Id_Servicio = e.Id_Servicio
                  WHERE e.Entregado = 1
                  ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";

$resultCompletados = $conexion->query($sqlCompletados);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Escaneos Completados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">
    <style>
        .container {
            margin-left: 250px;
            padding: 20px;
        }
        
        table {
            width: calc(100% - 300px);
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-left: 300px;
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
        
        .status-completed {
            color: #28a745;
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
            margin: 2px;
        }
        
        .btn:hover {
            background-color: #1a2657;
        }
        
        .btn-return {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-return:hover {
            background-color: #e0a800;
        }
        
        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
            margin-left: 300px;
            width: calc(100% - 350px);
        }
        
        .no-completados {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
            margin-left: 300px;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .alert-message {
            padding: 15px;
            margin: 20px 300px;
            border-radius: 5px;
            text-align: center;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .date-cell {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen - Servicios</h1>
            <h2 id="textoBajoh1" class="titulo">Pedidos de Escaneo Completados</h2> 
        </div>
    </div>

    <!-- Mostrar mensajes de éxito o error -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert-message alert-success">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert-message alert-error">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
        
    <div> 
        <?php if ($resultCompletados && $resultCompletados->num_rows > 0): ?>
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
                    <?php while ($pedido = $resultCompletados->fetch_assoc()): 
                        // Formatear la fecha a partir de los campos separados
                        $fechaEntrega = sprintf("%02d/%02d/%04d", 
                            $pedido['DiaEntrega'], 
                            $pedido['MesEntrega'], 
                            $pedido['AnioEntrega']
                        );
                    ?>
                        <tr>
                            <td><?php echo $pedido['Id_Servicio']; ?></td>
                            <td><?php echo $pedido['nombreServicio']; ?></td>
                            <td><?php echo $pedido['Descripcion']; ?></td>
                            <td><?php echo $pedido['TipoDocumento']; ?></td>
                            <td>$<?php echo number_format($pedido['PrecioTotal'], 2); ?></td>
                            <td class="date-cell"><?php echo $fechaEntrega; ?></td>
                            <td class="status-completed">Completado</td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirmReturnToPending()">
                                    <input type="hidden" name="id_servicio" value="<?php echo $pedido['Id_Servicio']; ?>">
                                    <button type="submit" name="return_to_pending" class="btn btn-return">
                                        Devolver a Pendiente
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-completados">
                <p>No hay pedidos de escaneo completados para mostrar.</p>
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

        // Función para confirmar la devolución a pendiente
        function confirmReturnToPending() {
            return confirm("¿Estás seguro de que deseas devolver este pedido a pendiente?\n\nEsta acción cambiará el estado del pedido de 'Completado' a 'Pendiente'.");
        }
    </script>
</body>
</html>
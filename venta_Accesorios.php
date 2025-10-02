<?php
session_start();
require_once __DIR__ . '/conexion.php';
date_default_timezone_set('America/Mexico_City');

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener el ID del usuario actual
$user_id = $_SESSION['User_Id'] ?? null;

// Si aún no tenemos el user_id, intentemos obtenerlo de la base de datos usando el username
if (!$user_id && isset($_SESSION["username"])) {
    $username = $_SESSION["username"];
    $sqlUser = "SELECT User_Id FROM users WHERE usuario = ?";
    $stmt = $conexion->prepare($sqlUser);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            $user_id = $userData['User_Id'];
            $_SESSION['User_Id'] = $user_id;
        }
    }
}

// Inicializar carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Agregar producto por código/nombre/marca/modelo
    if (isset($_POST['buscar_agregar'])) {
        $termino = trim($_POST['termino_busqueda']);

        if (!empty($termino)) {
            $sql = "SELECT * FROM accesorio WHERE 
                   Codigo LIKE ? OR
                   Nombre LIKE ? OR 
                   Marca LIKE ? OR 
                   Modelo LIKE ? 
                   LIMIT 10";
            $stmt = $conexion->prepare($sql);
            $param = "%$termino%";
            $stmt->bind_param("ssss", $param, $param, $param, $param);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $resultados = [];
                while ($row = $result->fetch_assoc()) {
                    $resultados[] = $row;
                }
                
                // Si hay más de un resultado, guardar en sesión para mostrar selección
                if (count($resultados) > 1) {
                    $_SESSION['resultados_busqueda'] = $resultados;
                    $_SESSION['termino_busqueda'] = $termino;
                } else {
                    // Si solo hay un resultado, agregarlo directamente
                    $accesorio = $resultados[0];
                    agregarAlCarrito($accesorio);
                }
            } else {
                $_SESSION['error'] = "No se encontró el producto";
            }
        }
    }
    // Seleccionar producto específico de múltiples resultados
    elseif (isset($_POST['seleccionar_producto'])) {
        // Verificar si se seleccionó un producto
        if (isset($_POST['id_accesorio']) && !empty($_POST['id_accesorio'])) {
            $idSeleccionado = $_POST['id_accesorio'];
            
            // Buscar el producto seleccionado en los resultados guardados
            if (isset($_SESSION['resultados_busqueda'])) {
                foreach ($_SESSION['resultados_busqueda'] as $accesorio) {
                    if ($accesorio['Id_Accesorios'] == $idSeleccionado) {
                        agregarAlCarrito($accesorio);
                        break;
                    }
                }
            }
        } else {
            $_SESSION['error'] = "Por favor selecciona un producto para agregar al carrito";
        }
        
        // Limpiar resultados de búsqueda (seleccione o no un producto)
        unset($_SESSION['resultados_busqueda']);
        unset($_SESSION['termino_busqueda']);
    }
    // Actualizar cantidades
    elseif (isset($_POST['actualizar_cantidades'])) {
        foreach ($_POST['cantidades'] as $id => $cantidad) {
            $cantidad = intval($cantidad);
            if ($cantidad > 0 && isset($_SESSION['carrito'][$id])) {
                // Verificar que la cantidad no exceda el stock disponible
                $stockDisponible = $_SESSION['carrito'][$id]['stock_disponible'];
                if ($cantidad > $stockDisponible) {
                    $_SESSION['error'] = "La cantidad solicitada de " . $_SESSION['carrito'][$id]['nombre'] . " excede el stock disponible (" . $stockDisponible . ")";
                } else {
                    $_SESSION['carrito'][$id]['cantidad'] = $cantidad;
                }
            }
        }
        if (!isset($_SESSION['error'])) {
            $_SESSION['success'] = "Cantidades actualizadas";
        }
    }
    // Eliminar producto
    elseif (isset($_POST['eliminar'])) {
        $idAccesorio = $_POST['eliminar'];
        if (isset($_SESSION['carrito'][$idAccesorio])) {
            unset($_SESSION['carrito'][$idAccesorio]);
            $_SESSION['success'] = "Producto eliminado del carrito";
        }
    }
    // Continuar venta (mostrar resumen de pago)
    elseif (isset($_POST['continuar_venta'])) {
        if (empty($_SESSION['carrito'])) {
            $_SESSION['error'] = "El carrito está vacío";
        } else {
            $_SESSION['mostrar_resumen_pago'] = true;
        }
    }
    // Confirmar pago (procesar la venta)
    elseif (isset($_POST['confirmar_pago'])) {
        if (empty($_SESSION['carrito'])) {
            $_SESSION['error'] = "El carrito está vacío";
        } elseif (!$user_id) {
            $_SESSION['error'] = "No se pudo identificar al usuario. Por favor, inicie sesión nuevamente.";
        } else {
            // Verificar stock antes de finalizar
            $errorStock = false;
            $productoSinStock = "";
    
            foreach ($_SESSION['carrito'] as $idAccesorio => $item) {
                $sqlStock = "SELECT stockDisponible FROM accesorio WHERE Id_Accesorios = ?";
                $stmtStock = $conexion->prepare($sqlStock);
                if (!$stmtStock) {
                    $_SESSION['error'] = "Error al preparar consulta de stock: " . $conexion->error;
                    $errorStock = true;
                    break;
                }
                $stmtStock->bind_param("s", $idAccesorio);
                $stmtStock->execute();
                $resultStock = $stmtStock->get_result();
                $accesorioStock = $resultStock->fetch_assoc();
    
                if ($accesorioStock['stockDisponible'] < $item['cantidad']) {
                    $errorStock = true;
                    $productoSinStock = $item['nombre'];
                    break;
                }
            }
    
            if ($errorStock) {
                $_SESSION['error'] = "No hay suficiente stock para: " . $productoSinStock;
            } else {
                // INICIAR TRANSACCIÓN
                $conexion->begin_transaction();
    
                try {
                    // 1. CALCULAR TOTAL Y PEDIR IMPORTE
                    $totalVenta = array_reduce($_SESSION['carrito'], function ($carry, $item) {
                        return $carry + ($item['precio'] * $item['cantidad']);
                    }, 0);
                    
                    // Obtener el importe pagado (dinero que el cliente dio al cajero)
                    $importePagado = isset($_POST['importe_pagado']) ? floatval($_POST['importe_pagado']) : $totalVenta;
                    
                    if ($importePagado < $totalVenta) {
                        throw new Exception("El importe pagado ($" . number_format($importePagado, 2) . ") es menor al total de la venta ($" . number_format($totalVenta, 2) . ")");
                    }

                    // 2. REGISTRAR SERVICIO (Descripción simple como solicitaste)
                    $descripcionServicio = "Venta Accesorio, Total: $" . number_format($totalVenta, 2);
                    $sqlServicio = "INSERT INTO servicio (nombreServicio, User_Id, Descripcion) VALUES (?, ?, ?)";
                    $stmtServicio = $conexion->prepare($sqlServicio);
                    if (!$stmtServicio) {
                        throw new Exception("Error al preparar servicio: " . $conexion->error);
                    }
                    
                    $nombreServicio = "Venta de Accesorios";
                    $stmtServicio->bind_param("sis", $nombreServicio, $user_id, $descripcionServicio);
                    if (!$stmtServicio->execute()) {
                        throw new Exception("Error al registrar servicio: " . $stmtServicio->error);
                    }
                    
                    $idServicio = $conexion->insert_id;

                    // 3. REGISTRAR VENTA (adaptado para nueva estructura)
                    $diaVenta = date('d');
                    $mesVenta = date('m');
                    $anioVenta = date('Y');

                    $sqlVenta = "INSERT INTO venta (montoVenta, MontoPago, Id_Servicio, DiaVenta, MesVenta, AnioVenta) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmtVenta = $conexion->prepare($sqlVenta);
                    if (!$stmtVenta) {
                        throw new Exception("Error al preparar venta: " . $conexion->error);
                    }

                    $stmtVenta->bind_param("ddiiii", $totalVenta, $importePagado, $idServicio, $diaVenta, $mesVenta, $anioVenta);
                    if (!$stmtVenta->execute()) {
                        throw new Exception("Error al registrar venta: " . $stmtVenta->error);
                    }
                    
                    $idVenta = $conexion->insert_id;
    
                    // 4. REGISTRAR PRODUCTOS VENDIDOS EN ventaaccesorios
                    foreach ($_SESSION['carrito'] as $idAccesorio => $item) {
                        // Insertar en ventaaccesorios
                        $sqlVentaAcc = "INSERT INTO ventaaccesorios (Id_Venta, Id_Accesorios, cantidad) VALUES (?, ?, ?)";
                        $stmtVentaAcc = $conexion->prepare($sqlVentaAcc);
                        if (!$stmtVentaAcc) {
                            throw new Exception("Error al preparar ventaaccesorios: " . $conexion->error);
                        }
                        $stmtVentaAcc->bind_param("iii", $idVenta, $idAccesorio, $item['cantidad']);
                        if (!$stmtVentaAcc->execute()) {
                            throw new Exception("Error al registrar producto: " . $stmtVentaAcc->error);
                        }
    
                        // Actualizar stock
                        $sqlUpdateStock = "UPDATE accesorio SET stockDisponible = stockDisponible - ? WHERE Id_Accesorios = ?";
                        $stmtUpdateStock = $conexion->prepare($sqlUpdateStock);
                        if (!$stmtUpdateStock) {
                            throw new Exception("Error al preparar actualización de stock: " . $conexion->error);
                        }
                        $stmtUpdateStock->bind_param("ii", $item['cantidad'], $idAccesorio);
                        if (!$stmtUpdateStock->execute()) {
                            throw new Exception("Error al actualizar stock: " . $stmtUpdateStock->error);
                        }
                    }
    
                    // CONFIRMAR TRANSACCIÓN
                    $conexion->commit();
    
                    // Calcular cambio si es necesario
                    $cambio = $importePagado - $totalVenta;
                    $mensajeExito = "Venta registrada exitosamente (ID: $idVenta).";
                    if ($cambio > 0) {
                        $mensajeExito .= " Cambio: $" . number_format($cambio, 2);
                    }
                    
                    $mensajeExito .= " <a href='ticketventa.php?id=$idVenta' target='_blank' class='ticket-link'>Ver Ticket</a>";
                    
                    // Limpiar carrito y mostrar éxito
                    unset($_SESSION['carrito']);
                    unset($_SESSION['mostrar_resumen_pago']);
                    $_SESSION['success'] = $mensajeExito;
    
                } catch (Exception $e) {
                    // REVERTIR EN CASO DE ERROR
                    $conexion->rollback();
                    $_SESSION['error'] = "Error al procesar la venta: " . $e->getMessage();
                    error_log("Error en venta: " . $e->getMessage());
                }
            }
        }
    }    
    // Expandir carrito
    elseif (isset($_POST['expandir_carrito'])) {
        unset($_SESSION['mostrar_resumen_pago']);
    }
    // Cancelar selección múltiple
    elseif (isset($_POST['cancelar_seleccion'])) {
        unset($_SESSION['resultados_busqueda']);
        unset($_SESSION['termino_busqueda']);
    }
}

// Función para agregar producto al carrito
function agregarAlCarrito($accesorio) {
    $idAccesorio = $accesorio['Id_Accesorios'];
    $stockDisponible = $accesorio['stockDisponible'];
    $cantidadActual = isset($_SESSION['carrito'][$idAccesorio]) ? $_SESSION['carrito'][$idAccesorio]['cantidad'] : 0;

    if ($stockDisponible <= 0) {
        $_SESSION['error'] = "No hay stock disponible para el producto: " . $accesorio['Nombre'];
    } elseif ($cantidadActual >= $stockDisponible) {
        $_SESSION['error'] = "Ya has agregado el máximo de stock disponible (" . $stockDisponible . ") para: " . $accesorio['Nombre'];
    } else {
        // Agregar al carrito o incrementar cantidad
        if (isset($_SESSION['carrito'][$idAccesorio])) {
            $_SESSION['carrito'][$idAccesorio]['cantidad'] += 1;
        } else {
            $_SESSION['carrito'][$idAccesorio] = [
                'nombre' => $accesorio['Nombre'],
                'marca' => $accesorio['Marca'],
                'modelo' => $accesorio['Modelo'],
                'presentacion' => isset($accesorio['Presentacion_Producto']) ? $accesorio['Presentacion_Producto'] : '',
                'precio' => $accesorio['Precio'],
                'cantidad' => 1,
                'stock_disponible' => $stockDisponible,
                'codigo' => $accesorio['Codigo']
            ];
        }
        $_SESSION['success'] = "Producto agregado al carrito";
    }
}

// Buscar producto en carrito
$resultadosBusqueda = [];
if (isset($_GET['buscar_en_carrito'])) {
    $termino = trim($_GET['termino_busqueda_carrito']);

    if (!empty($termino) && isset($_SESSION['carrito']) && !empty($_SESSION['carrito'])) {
        foreach ($_SESSION['carrito'] as $id => $item) {
            if (stripos($id, $termino) !== false || 
                stripos($item['nombre'], $termino) !== false ||
                stripos($item['marca'], $termino) !== false ||
                stripos($item['modelo'], $termino) !== false ||
                (isset($item['codigo']) && stripos($item['codigo'], $termino) !== false)) {
                $resultadosBusqueda[$id] = $item;
            }
        }
    }
}

// Asegurarse de que $_SESSION['carrito'] existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venta - Ciber Rotsen</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <link rel="stylesheet" href="menuStyleSheet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .container-venta {
            margin-left: 250px;
            padding: 20px;
        }

        .panel-venta {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .panel-venta h2 {
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

        .btn-success {
            background-color: #4CAF50;
            color: white;
        }

        .btn-danger {
            background-color: #f44336;
            color: white;
        }

        .btn-warning {
            background-color: #ff9800;
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

        .input-cantidad {
            width: 60px;
            padding: 5px;
            text-align: center;
        }

        .total-venta {
            font-weight: bold;
            font-size: 18px;
            margin-top: 15px;
            text-align: right;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }

        .alert-error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }

        .alert-info {
            background-color: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }

        .busqueda-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .busqueda-container input {
            flex: 1;
        }

        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Estilos para el resumen de pago */
        .resumen-pago {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            display: <?php echo isset($_SESSION['mostrar_resumen_pago']) ? 'block' : 'none'; ?>;
        }

        .resumen-pago h2 {
            color: #283785;
            border-bottom: 2px solid #283785;
            padding-bottom: 10px;
            margin-top: 0;
        }

        .resumen-monto {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            color: #283785;
        }

        .importe-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid #283785;
        }

        .importe-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .importe-input {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border: 2px solid #283785;
            border-radius: 5px;
            width: 200px;
        }

        .cambio-info {
            font-size: 16px;
            color: #28a745;
            font-weight: bold;
            margin-top: 10px;
        }

        .botones-resumen {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        /* Estilo para el carrito contraído */
        .carrito-contraido {
            display: <?php echo isset($_SESSION['mostrar_resumen_pago']) ? 'none' : 'block'; ?>;
        }

        /* Estilo para el enlace del ticket */
        .ticket-link {
            color: #2b542c;
            text-decoration: underline;
            font-weight: bold;
            margin-left: 5px;
        }

        .ticket-link:hover {
            color: #1e3a1e;
            text-decoration: none;
        }

        /* Estilos para la selección múltiple */
        .seleccion-multiple {
            background-color: #f8f9fa;
            border: 2px solid #283785;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .seleccion-multiple h3 {
            color: #283785;
            margin-top: 0;
        }

        .producto-opcion {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .producto-opcion:hover {
            background-color: #f0f6fe;
        }

        .producto-opcion.seleccionado {
            background-color: #e3f2fd;
            border-color: #283785;
        }

        .info-producto {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detalles-producto {
            flex: 1;
        }

        .precio-stock {
            text-align: right;
        }

        .precio {
            font-weight: bold;
            color: #283785;
            font-size: 16px;
        }

        .stock {
            font-size: 12px;
            color: #666;
        }

        .botones-seleccion {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        /* Estilo para el botón cancelar */
        .btn-cancelar {
            background-color: #6c757d;
            color: white;
        }

        .btn-cancelar:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Venta</h1>
            <h2 id="textoBajoh1" class="titulo">Usuario: <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
    </div>

    <div class="container-venta">
        <!-- Mensajes -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Panel para selección múltiple -->
        <?php if (isset($_SESSION['resultados_busqueda']) && count($_SESSION['resultados_busqueda']) > 1): ?>
            <div class="seleccion-multiple">
                <h3>Se encontraron múltiples productos para "<?php echo htmlspecialchars($_SESSION['termino_busqueda']); ?>"</h3>
                <p>Selecciona el producto que deseas agregar al carrito:</p>
                
                <form method="POST" id="form-seleccion">
                    <?php foreach ($_SESSION['resultados_busqueda'] as $accesorio): ?>
                        <div class="producto-opcion">
                            <label style="display: block; cursor: pointer;">
                                <input type="radio" name="id_accesorio" value="<?php echo $accesorio['Id_Accesorios']; ?>">
                                <div class="info-producto">
                                    <div class="detalles-producto">
                                        <strong><?php echo htmlspecialchars($accesorio['Nombre']); ?></strong><br>
                                        <small>
                                            Código: <?php echo htmlspecialchars($accesorio['Codigo']); ?> | 
                                            ID: <?php echo htmlspecialchars($accesorio['Id_Accesorios']); ?> | 
                                            Marca: <?php echo htmlspecialchars($accesorio['Marca']); ?> | 
                                            Modelo: <?php echo htmlspecialchars($accessorio['Modelo']); ?>
                                            <?php if (!empty($accesorio['Presentacion_Producto'])): ?>
                                                | Presentación: <?php echo htmlspecialchars($accesorio['Presentacion_Producto']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="precio-stock">
                                        <div class="precio">$<?php echo number_format($accesorio['Precio'], 2); ?></div>
                                        <div class="stock">Stock: <?php echo $accesorio['stockDisponible']; ?></div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="botones-seleccion">
                        <button type="submit" name="cancelar_seleccion" class="btn btn-cancelar">
                            ← Cancelar y Volver
                        </button>
                        <button type="submit" name="seleccionar_producto" class="btn btn-success">
                            Agregar Producto Seleccionado
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Panel para agregar productos -->
        <div class="panel-venta">
            <h2>Agregar Producto</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="termino_busqueda">Buscar por código, nombre, marca o modelo:</label>
                    <div class="busqueda-container">
                        <input type="text" id="termino_busqueda" name="termino_busqueda" class="form-control" 
                               placeholder="Ej: COD123, Mouse, Logitech, M185"
                               value="<?php echo isset($_SESSION['termino_busqueda']) ? htmlspecialchars($_SESSION['termino_busqueda']) : ''; ?>">
                        <button type="submit" name="buscar_agregar" class="btn btn-primary">Buscar</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Panel para buscar en carrito -->
        <div class="panel-venta">
            <h2>Buscar en Carrito</h2>
            <form method="GET">
                <div class="form-group">
                    <label for="termino_busqueda_carrito">Buscar producto en carrito:</label>
                    <div class="busqueda-container">
                        <input type="text" id="termino_busqueda_carrito" name="termino_busqueda_carrito" class="form-control" placeholder="Ej: COD123, Mouse, Logitech">
                        <button type="submit" name="buscar_en_carrito" class="btn btn-primary">Buscar</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Resumen de pago (nueva ventana) -->
        <?php if (isset($_SESSION['mostrar_resumen_pago']) && !empty($_SESSION['carrito'])): 
            $total = array_reduce($_SESSION['carrito'], function($carry, $item) {
                return $carry + ($item['precio'] * $item['cantidad']);
            }, 0);
        ?>
            <div class="resumen-pago">
                <h2>Confirmar Pago</h2>
                <div class="resumen-monto">
                    Total a cobrar: $<?php echo number_format($total, 2); ?>
                </div>
                
                <div class="importe-container">
                    <form method="POST" id="form-pago">
                        <div class="importe-group">
                            <label for="importe_pagado">Importe recibido:</label>
                            <input type="number" id="importe_pagado" name="importe_pagado" 
                                   class="importe-input" step="0.01" min="<?php echo $total; ?>" 
                                   value="<?php echo $total; ?>" required>
                            <div id="cambio-info" class="cambio-info"></div>
                        </div>
                        
                        <div class="botones-resumen">
                            <button type="submit" name="expandir_carrito" class="btn btn-primary">
                                Editar Carrito
                            </button>
                            <button type="submit" name="confirmar_pago" class="btn btn-success">
                                Confirmar Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Panel del carrito de venta -->
        <div class="panel-venta carrito-contraido">
            <h2>Carrito de Venta</h2>
            <form method="POST">
                <?php 
                $productosMostrar = !empty($resultadosBusqueda) ? $resultadosBusqueda : $_SESSION['carrito'];

                if (!empty($productosMostrar)): 
                    $total = 0;
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Presentación</th>
                                <th>Precio Unit.</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productosMostrar as $id => $item): 
                                $subtotal = $item['precio'] * $item['cantidad'];
                                $total += $subtotal;
                                $stockDisponible = $item['stock_disponible'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(isset($item['codigo']) ? $item['codigo'] : ''); ?></td>
                                <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($item['marca']); ?></td>
                                <td><?php echo htmlspecialchars($item['modelo']); ?></td>
                                <td><?php echo htmlspecialchars(isset($item['presentacion']) ? $item['presentacion'] : ''); ?></td>
                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                <td>
                                    <input type="number" name="cantidades[<?php echo $id; ?>]" 
                                           class="input-cantidad" 
                                           value="<?php echo $item['cantidad']; ?>" 
                                           min="1" max="<?php echo $stockDisponible; ?>">
                                    <div class="stock-info">Stock disponible: <?php echo $stockDisponible; ?></div>
                                </td>
                                <td>$<?php echo number_format($subtotal, 2); ?></td>
                                <td>
                                    <button type="submit" name="eliminar" value="<?php echo $id; ?>" 
                                            class="btn btn-danger">Eliminar</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="total-venta">
                        Total: $<?php echo number_format($total, 2); ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                        <button type="submit" name="actualizar_cantidades" class="btn btn-primary">
                            Actualizar Cantidades
                        </button>
                        <button type="submit" name="continuar_venta" class="btn btn-success">
                            Continuar la venta
                        </button>
                    </div>
                <?php else: ?>
                    <p>No hay productos en el carrito</p>
                <?php endif; ?>
            </form>
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

        // Mejorar la experiencia de selección de productos
        $(document).ready(function() {
            $('.producto-opcion').click(function() {
                $('.producto-opcion').removeClass('seleccionado');
                $(this).addClass('seleccionado');
                $(this).find('input[type="radio"]').prop('checked', true);
            });

            // Permitir cancelar sin seleccionar producto
            $('button[name="cancelar_seleccion"]').click(function(e) {
                e.preventDefault();
                $('form#form-seleccion').append('<input type="hidden" name="cancelar_seleccion" value="1">');
                $('form#form-seleccion').submit();
            });

            // Calcular y mostrar cambio en tiempo real
            $('#importe_pagado').on('input', function() {
                const total = <?php echo $total ?? 0; ?>;
                const importe = parseFloat($(this).val()) || 0;
                const cambio = importe - total;
                
                if (cambio > 0) {
                    $('#cambio-info').text('Cambio: $' + cambio.toFixed(2));
                } else {
                    $('#cambio-info').text('');
                }
            });
        });
    </script>
</body>
</html>
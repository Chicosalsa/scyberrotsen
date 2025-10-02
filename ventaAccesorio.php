<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
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
                   Id_Accesorios LIKE ? OR 
                   Nombre LIKE ? OR 
                   Marca LIKE ? OR 
                   Modelo LIKE ? 
                   LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $param = "%$termino%";
            $stmt->bind_param("ssss", $param, $param, $param, $param);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $accesorio = $result->fetch_assoc();
                $idAccesorio = $accesorio['Id_Accesorios'];

                // Verificar stock disponible
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
                            'precio' => $accesorio['Precio'],
                            'cantidad' => 1,
                            'stock_disponible' => $stockDisponible // Guardar el stock disponible en el carrito
                        ];
                    }
                    $_SESSION['success'] = "Producto agregado al carrito";
                }
            } else {
                $_SESSION['error'] = "No se encontró el producto";
            }
        }
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
        $idAccesorio = $_POST['eliminar']; // Changed to match the value in the button
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
        } else {
            // Verificar stock actual antes de finalizar la venta
            $errorStock = false;
            $productoSinStock = "";

            foreach ($_SESSION['carrito'] as $idAccesorio => $item) {
                // Consultar stock actual en la base de datos
                $sqlStock = "SELECT stockDisponible FROM accesorio WHERE Id_Accesorios = ?";
                $stmtStock = $conexion->prepare($sqlStock);
                $stmtStock->bind_param("s", $idAccesorio);
                $stmtStock->execute();
                $resultStock = $stmtStock->get_result();
                $accesorioStock = $resultStock->fetch_assoc();

                // Verificar si hay suficiente stock
                if ($accesorioStock['stockDisponible'] < $item['cantidad']) {
                    $errorStock = true;
                    $productoSinStock = $item['nombre'];
                    break;
                }
            }

            if ($errorStock) {
                $_SESSION['error'] = "No hay suficiente stock para el producto: " . $productoSinStock . ". Por favor verifique el inventario.";
                unset($_SESSION['mostrar_resumen_pago']);
            } else {
                $conexion->begin_transaction();

                try {
                    // 1. Crear registro de venta
                    $idVenta = uniqid('V');
                    $montoTotal = array_reduce($_SESSION['carrito'], function($carry, $item) {
                        return $carry + ($item['precio'] * $item['cantidad']);
                    }, 0);
                    $fechaVenta = date('Y-m-d');

                    $sqlVenta = "INSERT INTO venta (Id_Venta, montoVenta, fechaVenta) VALUES (?, ?, ?)";
                    $stmtVenta = $conexion->prepare($sqlVenta);
                    $stmtVenta->bind_param("sds", $idVenta, $montoTotal, $fechaVenta);
                    $stmtVenta->execute();

                    // 2. Registrar items y actualizar stock
                    foreach ($_SESSION['carrito'] as $idAccesorio => $item) {
                        // Insertar en ventaaccesorios
                        $sqlVentaAcc = "INSERT INTO ventaaccesorios (Id_Venta, Id_Accesorios, precioUnitario, Categoria, cantidad, nombreAccesorio) 
                                       VALUES (?, ?, ?, ?, ?, ?)";
                        $stmtVentaAcc = $conexion->prepare($sqlVentaAcc);
                        $categoria = "General";
                        $stmtVentaAcc->bind_param("ssdsis", $idVenta, $idAccesorio, $item['precio'], $categoria, $item['cantidad'], $item['nombre']);
                        $stmtVentaAcc->execute();

                        // Actualizar stock
                        $sqlUpdateStock = "UPDATE accesorio SET stockDisponible = stockDisponible - ? WHERE Id_Accesorios = ?";
                        $stmtUpdateStock = $conexion->prepare($sqlUpdateStock);
                        $stmtUpdateStock->bind_param("is", $item['cantidad'], $idAccesorio);
                        $stmtUpdateStock->execute();
                    }

                    $conexion->commit();
                    unset($_SESSION['carrito']);
                    unset($_SESSION['mostrar_resumen_pago']);
                    $_SESSION['success'] = "Venta registrada exitosamente (ID: $idVenta)";

                } catch (Exception $e) {
                    $conexion->rollback();
                    $_SESSION['error'] = "Error al procesar la venta: " . $e->getMessage();
                    unset($_SESSION['mostrar_resumen_pago']);
                }
            }
        }
    }
    // Expandir carrito
    elseif (isset($_POST['expandir_carrito'])) {
        unset($_SESSION['mostrar_resumen_pago']);
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
                stripos($item['modelo'], $termino) !== false) {
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
        }

        .alert-error {
            background-color: #f2dede;
            color: #a94442;
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
    </style>
</head>
<body>
    <!-- Menú lateral -->
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

        <!-- Panel para agregar productos -->
        <div class="panel-venta">
            <h2>Agregar Producto</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="termino_busqueda">Buscar por código, nombre, marca o modelo:</label>
                    <div class="busqueda-container">
                        <input type="text" id="termino_busqueda" name="termino_busqueda" class="form-control" placeholder="Ej: A001, Mouse, Logitech, M185">
                        <button type="submit" name="buscar_agregar" class="btn btn-primary">Agregar</button>
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
                        <input type="text" id="termino_busqueda_carrito" name="termino_busqueda_carrito" class="form-control" placeholder="Ej: A001, Mouse, Logitech">
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
                <form method="POST">
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
        <?php endif; ?>

        <!-- Panel del carrito de venta -->
        <div class="panel-venta carrito-contraido">
            <h2>Carrito de Venta</h2>
            <form method="POST">
                <?php 
                // Asegurarse de que $_SESSION['carrito'] existe
                if (!isset($_SESSION['carrito'])) {
                    $_SESSION['carrito'] = [];
                }

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
                                <td><?php echo htmlspecialchars($id); ?></td>
                                <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($item['marca']); ?></td>
                                <td><?php echo htmlspecialchars($item['modelo']); ?></td>
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
    </script>
</body>
</html>
<?php
//cuadro nuevo donde aparezca un mensaje de advertencia para cuando haya 10 productos existentes o menos
//ver witherboard para cambios dentro de actualizacionStock
//cuando se de clic a la advertencia se vaya a actualizacionstock o en un servicio, se vaya al serviciopendiente segun sea correspondido

session_start();
require_once __DIR__ . '/conexion.php'; // Incluir la conexión a la base de datos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}
date_default_timezone_set('America/Mexico_City');

// Cerrar sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Obtener fecha actual en formato separado (día, mes, año)
$diaHoy = (int)date('d');
$mesHoy = (int)date('m');
$anioHoy = (int)date('Y');

// Obtener fecha de mañana
$fechaManana = date('Y-m-d', strtotime('+1 day'));
$diaManana = (int)date('d', strtotime('+1 day'));
$mesManana = (int)date('m', strtotime('+1 day'));
$anioManana = (int)date('Y', strtotime('+1 day'));

// Obtener fecha límite (6 días desde hoy)
$fechaLimite = date('Y-m-d', strtotime('+6 days'));
$diaLimite = (int)date('d', strtotime('+6 days'));
$mesLimite = (int)date('m', strtotime('+6 days'));
$anioLimite = (int)date('Y', strtotime('+6 days'));

// Simplificar las consultas para evitar el error de bind_param
// Obtener escáneres pendientes próximos a entregar (6 días o menos)
$sqlEscaneres = "SELECT s.Id_Servicio, s.Descripcion, e.TipoDocumento, e.DiaEntrega, e.MesEntrega, e.AnioEntrega 
                 FROM servicio s 
                 JOIN escaner e ON s.Id_Servicio = e.Id_Servicio 
                 WHERE e.Entregado = 0 
                 AND (
                     (e.AnioEntrega = $anioHoy AND e.MesEntrega = $mesHoy AND e.DiaEntrega BETWEEN $diaHoy AND 31) OR
                     (e.AnioEntrega = $anioHoy AND e.MesEntrega = $mesManana AND e.DiaEntrega BETWEEN 1 AND $diaLimite) OR
                     (e.AnioEntrega = $anioManana AND e.MesEntrega = $mesLimite AND e.DiaEntrega BETWEEN 1 AND $diaLimite)
                 )
                 ORDER BY e.AnioEntrega ASC, e.MesEntrega ASC, e.DiaEntrega ASC";

$resultEscaneres = $conexion->query($sqlEscaneres);
$escaneresProximos = $resultEscaneres ? $resultEscaneres->fetch_all(MYSQLI_ASSOC) : [];

// Obtener impresiones pendientes próximas a entregar (6 días o menos)
$sqlImpresiones = "SELECT s.Id_Servicio, s.Descripcion, i.NomDoc, i.tipoDocumentp, i.numCopias, i.DiaEntrega, i.MesEntrega, i.AnioEntrega 
                   FROM servicio s 
                   JOIN impresion i ON s.Id_Servicio = i.Id_Servicio 
                   WHERE i.Entregado = 0 
                   AND (
                       (i.AnioEntrega = $anioHoy AND i.MesEntrega = $mesHoy AND i.DiaEntrega BETWEEN $diaHoy AND 31) OR
                       (i.AnioEntrega = $anioHoy AND i.MesEntrega = $mesManana AND i.DiaEntrega BETWEEN 1 AND $diaLimite) OR
                       (i.AnioEntrega = $anioManana AND i.MesEntrega = $mesLimite AND i.DiaEntrega BETWEEN 1 AND $diaLimite)
                   )
                   ORDER BY i.AnioEntrega ASC, i.MesEntrega ASC, i.DiaEntrega ASC";

$resultImpresiones = $conexion->query($sqlImpresiones);
$impresionesProximas = $resultImpresiones ? $resultImpresiones->fetch_all(MYSQLI_ASSOC) : [];

// Calcular total de pedidos próximos
$totalProximos = count($escaneresProximos) + count($impresionesProximas);

// Obtener productos con stock bajo (10 o menos)
$sqlStockBajo = "SELECT Id_Accesorios, Nombre, stockDisponible 
                 FROM accesorio 
                 WHERE stockDisponible <= 10 
                 ORDER BY stockDisponible ASC";
$resultStockBajo = $conexion->query($sqlStockBajo);
$productosStockBajo = $resultStockBajo ? $resultStockBajo->fetch_all(MYSQLI_ASSOC) : [];
$totalStockBajo = count($productosStockBajo);

// Función para comparar fechas
function esHoy($dia, $mes, $anio) {
    global $diaHoy, $mesHoy, $anioHoy;
    return $dia == $diaHoy && $mes == $mesHoy && $anio == $anioHoy;
}

function esManana($dia, $mes, $anio) {
    global $diaManana, $mesManana, $anioManana;
    return $dia == $diaManana && $mes == $mesManana && $anio == $anioManana;
}

?>

<!DOCTYPE html>
<html lang="es">
    <!--No tocar -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <link rel="stylesheet" href="menuStyleSheet.css" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /*
        Revisar menuStyleSheet.css para ver los estilos
        */
        
        .quick-access {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px;
        }
        
        .box {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .box h3 {
            margin-top: 0;
            color: #283785;
            border-bottom: 2px solid #283785;
            padding-bottom: 10px;
        }
        
        .proximos-entregar {
            margin-top: 10px;
            padding: 15px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
        }
        
        .stock-bajo {
            margin-top: 10px;
            padding: 15px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }
        
        .proximos-entregar h4, .stock-bajo h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .proximos-entregar h4 {
            color: #856404;
        }
        
        .stock-bajo h4 {
            color: #721c24;
        }
        
        .lista-pedidos, .lista-stock {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .pedido-item, .stock-item {
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 3px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .pedido-item {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .stock-item {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .pedido-item:hover, .stock-item:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
        
        .pedido-item.urgente {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .pedido-item.manana {
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .stock-item.critico {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            font-weight: bold;
        }
        
        .stock-item.bajo {
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .tipo-servicio, .producto-nombre {
            font-weight: bold;
            color: #283785;
        }
        
        .fecha-entrega, .stock-cantidad {
            float: right;
            font-size: 12px;
        }
        
        .urgente .fecha-entrega {
            color: #721c24;
            font-weight: bold;
        }
        
        .manana .fecha-entrega {
            color: #856404;
            font-weight: bold;
        }
        
        .critico .stock-cantidad {
            color: #721c24;
            font-weight: bold;
        }
        
        .bajo .stock-cantidad {
            color: #856404;
            font-weight: bold;
        }
        
        .sin-pedidos, .sin-stock-bajo {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 10px;
        }
        
        .descripcion-documento {
            font-size: 12px;
            color: #555;
            margin-top: 2px;
        }
        
        .info-copias {
            font-size: 11px;
            color: #777;
            margin-left: 5px;
        }
        
        .alert-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Menú lateral -->
    <?php include 'sidebar.php'; ?>
    <!--  -->

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <!-- agregar variables de usuario logueado -->
            <h2 id="textoBajoh1" class="titulo">Bienvenido <?php echo isset($_SESSION["username"]) ? htmlspecialchars($_SESSION["username"]) : ""; ?></h2> 
        </div>
    </div>

    <!-- Cuadro de acceso rápido -->
    <div class="quick-access">
        <div class="box">
            <h3>Servicios de Impresion / Escaner</h3>
            <div class="proximos-entregar">
                <h4>📦 Próximos a entregar (<?php echo $totalProximos; ?>)</h4>
                <div class="lista-pedidos">
                    <?php if ($totalProximos > 0): ?>
                        
                        <!-- Mostrar escáneres próximos -->
                        <?php foreach ($escaneresProximos as $escaner): 
                            $clase = '';
                            if (esHoy($escaner['DiaEntrega'], $escaner['MesEntrega'], $escaner['AnioEntrega'])) {
                                $clase = 'urgente';
                            } elseif (esManana($escaner['DiaEntrega'], $escaner['MesEntrega'], $escaner['AnioEntrega'])) {
                                $clase = 'manana';
                            }
                        ?>
                            <div class="pedido-item <?php echo $clase; ?>" onclick="window.location.href='serviciopendiente.php?id=<?php echo $escaner['Id_Servicio']; ?>&tipo=escaner'">
                                <span class="tipo-servicio">Escáner</span> - 
                                ID: <?php echo $escaner['Id_Servicio']; ?> - 
                                <?php echo htmlspecialchars($escaner['TipoDocumento']); ?>
                                <div class="descripcion-documento">
                                    <?php echo htmlspecialchars($escaner['Descripcion']); ?>
                                </div>
                                <span class="fecha-entrega">
                                    <?php 
                                    if (esHoy($escaner['DiaEntrega'], $escaner['MesEntrega'], $escaner['AnioEntrega'])) {
                                        echo '🔴 Hoy';
                                    } elseif (esManana($escaner['DiaEntrega'], $escaner['MesEntrega'], $escaner['AnioEntrega'])) {
                                        echo '🟡 Mañana';
                                    } else {
                                        echo '🟢 ' . $escaner['DiaEntrega'] . '/' . $escaner['MesEntrega'];
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Mostrar impresiones próximas -->
                        <?php foreach ($impresionesProximas as $impresion): 
                            $clase = '';
                            if (esHoy($impresion['DiaEntrega'], $impresion['MesEntrega'], $impresion['AnioEntrega'])) {
                                $clase = 'urgente';
                            } elseif (esManana($impresion['DiaEntrega'], $impresion['MesEntrega'], $impresion['AnioEntrega'])) {
                                $clase = 'manana';
                            }
                        ?>
                            <div class="pedido-item <?php echo $clase; ?>" onclick="window.location.href='serviciopendiente.php?id=<?php echo $impresion['Id_Servicio']; ?>&tipo=impresion'">
                                <span class="tipo-servicio">Impresión</span> - 
                                ID: <?php echo $impresion['Id_Servicio']; ?> - 
                                <?php echo htmlspecialchars($impresion['NomDoc']); ?>
                                <span class="info-copias">
                                    (<?php echo $impresion['numCopias']; ?> copias - <?php echo htmlspecialchars($impresion['tipoDocumentp']); ?>)
                                </span>
                                <div class="descripcion-documento">
                                    <?php echo htmlspecialchars($impresion['Descripcion']); ?>
                                </div>
                                <span class="fecha-entrega">
                                    <?php 
                                    if (esHoy($impresion['DiaEntrega'], $impresion['MesEntrega'], $impresion['AnioEntrega'])) {
                                        echo '🔴 Hoy';
                                    } elseif (esManana($impresion['DiaEntrega'], $impresion['MesEntrega'], $impresion['AnioEntrega'])) {
                                        echo '🟡 Mañana';
                                    } else {
                                        echo '🟢 ' . $impresion['DiaEntrega'] . '/' . $impresion['MesEntrega'];
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                    <?php else: ?>
                        <div class="sin-pedidos">No hay pedidos próximos a entregar</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Nuevo cuadro para alerta de stock bajo -->
        <div class="box">
            <h3>Inventario de Productos 
                <?php if ($totalStockBajo > 0): ?>
                    <span class="alert-badge"><?php echo $totalStockBajo; ?></span>
                <?php endif; ?>
            </h3>
            <div class="stock-bajo">
                <h4>⚠️ Stock Bajo (10 o menos unidades)</h4>
                <div class="lista-stock">
                    <?php if ($totalStockBajo > 0): ?>
                        
                        <!-- Mostrar productos con stock bajo -->
                        <?php foreach ($productosStockBajo as $producto): 
                            $clase = $producto['stockDisponible'] <= 5 ? 'critico' : 'bajo';
                        ?>
                            <div class="stock-item <?php echo $clase; ?>" onclick="window.location.href='ActualizacionStock.php?search=<?php echo urlencode($producto['Nombre']); ?>'">
                                <span class="producto-nombre"><?php echo htmlspecialchars($producto['Nombre']); ?></span>
                                <span class="stock-cantidad">
                                    <?php 
                                    if ($producto['stockDisponible'] <= 5) {
                                        echo '🔴 ' . $producto['stockDisponible'] . ' unidades';
                                    } else {
                                        echo '🟡 ' . $producto['stockDisponible'] . ' unidades';
                                    }
                                    ?>
                                </span>
                                <div class="descripcion-documento">
                                    ID: <?php echo $producto['Id_Accesorios']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                    <?php else: ?>
                        <div class="sin-stock-bajo">No hay productos con stock bajo</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Script para manejar la apertura y cierre de submenús
        $("#leftside-navigation .sub-menu > a").click(function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado del enlace
            const subMenu = $(this).next("ul");
            const arrow = $(this).find(".arrow");

            // Cierra todos los submenús excepto el actual
            $("#leftside-navigation ul ul").not(subMenu).slideUp();
            $("#leftside-navigation .arrow").not(arrow).removeClass("fa-angle-up").addClass("fa-angle-down");

            // Abre o cierra el submenú actual
            subMenu.slideToggle();

            // Cambia la flecha
            if (subMenu.is(":visible")) {
                arrow.removeClass("fa-angle-down").addClass("fa-angle-up");
            } else {
                arrow.removeClass("fa-angle-up").addClass("fa-angle-down");
            }
        });
    </script>
</body>
</html>
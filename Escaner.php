<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener información del usuario
$username = "";
$isAdmin = false;
if (isset($_SESSION["username"])) {
    $username = $_SESSION["username"];
    
    $sqlUser = "SELECT Id_Rol FROM users WHERE Usuario = ?";
    $stmt = $conexion->prepare($sqlUser);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $resultUser = $stmt->get_result();
        
        if ($resultUser && $resultUser->num_rows > 0) {
            $userData = $resultUser->fetch_assoc();
            $isAdmin = ($userData['Id_Rol'] == 1); // 1 = Admin
        }
        $stmt->close();
    }
}

// Para depuración - Guardar errores en un archivo de log
function logError($message) {
    $logFile = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Procesar nuevo pedido de escaneo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_scan_order'])) {
    // Obtener datos del formulario
    $tipoDocumento = isset($_POST['tipo_documento']) ? $_POST['tipo_documento'] : '';
    $descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
    $fechaEntrega = isset($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : '';
    $costoPorServicio = isset($_POST['costo_por_servicio']) ? floatval($_POST['costo_por_servicio']) : 0;

    // Validar datos
    if (empty($tipoDocumento) || empty($descripcion) || empty($fechaEntrega) || $costoPorServicio <= 0) {
        echo "<script>alert('Por favor complete todos los campos correctamente. El costo debe ser mayor que cero.');</script>";
    } else {
        // Validar que la fecha de entrega no sea anterior a hoy
        $fechaHoy = date('Y-m-d');
        if ($fechaEntrega < $fechaHoy) {
            echo "<script>alert('La fecha de entrega no puede ser anterior a la fecha actual.');</script>";
        } else {
            // Usar transacción para asegurar consistencia
            $conexion->begin_transaction();
            
            try {
                // Obtener el User_Id del usuario actual
                $userId = 0;
                $sqlUserId = "SELECT User_Id FROM users WHERE Usuario = ?";
                $stmtUserId = $conexion->prepare($sqlUserId);
                if ($stmtUserId) {
                    $stmtUserId->bind_param("s", $username);
                    $stmtUserId->execute();
                    $resultUserId = $stmtUserId->get_result();
                    if ($resultUserId && $resultUserId->num_rows > 0) {
                        $userRow = $resultUserId->fetch_assoc();
                        $userId = $userRow['User_Id'];
                    }
                    $stmtUserId->close();
                }

                // Insertar en la tabla servicio primero
                $nombreServicio = "Escaneo - " . $tipoDocumento;
                $descripcionServicio = $descripcion;
                
                $sqlInsertService = "INSERT INTO servicio (nombreServicio, User_Id, Descripcion) 
                                    VALUES (?, ?, ?)";
                $stmtService = $conexion->prepare($sqlInsertService);
                if (!$stmtService) {
                    throw new Exception("Error preparando declaración para servicio: " . $conexion->error);
                }
                
                $stmtService->bind_param("sis", $nombreServicio, $userId, $descripcionServicio);
                
                if (!$stmtService->execute()) {
                    throw new Exception("Error al ejecutar inserción en servicio: " . $stmtService->error);
                }
                
                // Obtener el ID del servicio recién insertado
                $idServicio = $conexion->insert_id;

                // Parsear la fecha para obtener día, mes y año por separado
                $fechaParts = explode('-', $fechaEntrega);
                $anioEntrega = intval($fechaParts[0]);
                $mesEntrega = intval($fechaParts[1]);
                $diaEntrega = intval($fechaParts[2]);

                // Insertar en la tabla escaner con la estructura CORRECTA
                $sqlInsertScan = "INSERT INTO escaner (Id_Servicio, PrecioTotal, TipoDocumento, Entregado, MontoPago, DiaEntrega, MesEntrega, AnioEntrega) 
                                VALUES (?, ?, ?, 0, ?, ?, ?, ?)";
                $stmtScan = $conexion->prepare($sqlInsertScan);
                if (!$stmtScan) {
                    throw new Exception("Error preparando declaración para escaner: " . $conexion->error);
                }
                
                // Entregado = 0 (falso) por defecto, MontoPago = 0 inicialmente
                $stmtScan->bind_param("idsiiii", $idServicio, $costoPorServicio, $tipoDocumento, $costoPorServicio, $diaEntrega, $mesEntrega, $anioEntrega);
                
                if (!$stmtScan->execute()) {
                    throw new Exception("Error al ejecutar inserción en escaner: " . $stmtScan->error);
                }
                
                // Todo salió bien, confirmar la transacción
                if (!$conexion->commit()) {
                    throw new Exception("Error al confirmar la transacción: " . $conexion->error);
                }
                
                echo "<script>alert('Pedido de escaneo registrado correctamente (ID: $idServicio)');</script>";
                echo "<script>window.location.href = 'EscanPendientes.php';</script>"; // Redirigir a pendientes
                
                $stmtScan->close();
                $stmtService->close();
                
            } catch (Exception $e) {
                // Si algo falla, revertir la transacción
                $conexion->rollback();
                logError("Error en escaner.php: " . $e->getMessage());
                echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Escaner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">

    <style>
        /* Botón de subir */
        .botonSubir {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 10vh;
        }

        /* Contenedor principal desplazado por el sidebar */
        .container {
            margin-left: 240px; /* Ajuste para el menú lateral */
            padding: 20px;
        }

        /* Estilos para centrar la tabla */
        .table-container {
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 20px 0;
        }

        table {
            background-color: #f0f7ff;
            border-collapse: collapse;
            margin: 0 auto;
            width: auto;
            max-width: 95%;
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

        /* Estilos para inputs y textareas */
        input[type="text"], 
        input[type="number"],
        input[type="date"],
        textarea {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        textarea {
            min-height: 80px;
            resize: vertical;
        }

        /* Barra de búsqueda */
        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 300px;
            padding: 10px;
            font-size: 16px;
        }

        /* Botones */
        .btn {
            padding: 5px 10px;
            background-color: #283785;
            color: white;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #1a2657;
        }

        /* Mensajes de error */
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error-message a {
            color: #283785;
            text-decoration: underline;
            cursor: pointer;
        }
        
        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-width: 600px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        
        .submit-btn {
            background-color: #283785;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background-color: #1a2657;
        }
        
        #wa {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            margin-left: 550px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <h2 id="textoBajoh1" class="titulo">Registrar Pedido de Escaneo</h2> 
        </div>
    </div>
    
    <div class="container" id="wa">
        <div class="form-container">
            <h2>Nuevo Pedido de Escaneo</h2>
            <form method="POST" action="" id="scanForm">
                <div class="form-group">
                    <label for="tipo_documento">Tipo de documento a entregar:</label>
                    <select id="tipo_documento" name="tipo_documento" required>
                        <option value="">Seleccione...</option>
                        <option value="Word">Documento Word</option>
                        <option value="PDF">PDF</option>
                        <option value="Imagen">Imagen</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción del documento:</label>
                    <textarea id="descripcion" name="descripcion" placeholder="Ingrese la descripción detallada del documento que será escaneado..." required></textarea>
                    <small style="color: #666;">Esta descripción se guardará en el sistema</small>
                </div>

                <div class="form-group">
                    <label for="fecha_entrega">Fecha de entrega:</label>
                    <input type="date" id="fecha_entrega" name="fecha_entrega" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="costo_por_servicio">Costo del servicio:</label>
                    <input type="number" id="costo_por_servicio" name="costo_por_servicio" step="0.01" min="0.01" required>
                </div>
                
                <button type="submit" name="add_scan_order" class="submit-btn">Registrar Pedido</button>
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
        
        // Validación del formulario
        document.getElementById('scanForm').addEventListener('submit', function(event) {
            const tipoDocumento = document.getElementById('tipo_documento').value;
            const descripcion = document.getElementById('descripcion').value;
            const fechaEntrega = document.getElementById('fecha_entrega').value;
            const costo = parseFloat(document.getElementById('costo_por_servicio').value);
            const fechaHoy = new Date().toISOString().split('T')[0];
            
            if (!tipoDocumento || !descripcion || !fechaEntrega || isNaN(costo) || costo <= 0) {
                alert('Por favor complete todos los campos correctamente. El costo debe ser mayor que cero.');
                event.preventDefault();
            } else if (fechaEntrega < fechaHoy) {
                alert('La fecha de entrega no puede ser anterior a la fecha actual.');
                event.preventDefault();
            }
        });

        // Establecer fecha mínima como hoy
        document.getElementById('fecha_entrega').min = new Date().toISOString().split("T")[0];
    </script>
</body>
</html>
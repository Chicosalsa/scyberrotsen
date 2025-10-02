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

// Procesar nuevo pedido de impresión
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_print_order'])) {
    // Obtener datos del formulario
    $tipoDocumento = isset($_POST['tipo_documento']) ? $_POST['tipo_documento'] : '';
    $nomDoc = isset($_POST['nom_doc']) ? $_POST['nom_doc'] : '';
    $numCopias = isset($_POST['num_copias']) ? intval($_POST['num_copias']) : 1;
    $color = isset($_POST['color']) ? 1 : 0;
    $costoPorServicio = isset($_POST['costo_por_servicio']) ? floatval($_POST['costo_por_servicio']) : 0;
    $descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
    $fechaEntrega = isset($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : '';

    // Validar datos
    if (empty($tipoDocumento) || empty($nomDoc) || $numCopias <= 0 || $costoPorServicio <= 0 || empty($descripcion) || empty($fechaEntrega)) {
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

                // Insertar en la tabla servicio primero - usando la descripción del documento
                $nombreServicio = "Impresión - " . $tipoDocumento;
                $descripcionServicio = $descripcion; // Usar la descripción del documento
                
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

                // Insertar en la tabla impresion con la estructura correcta - ADAPTADO para nueva BD
                $sqlInsertPrint = "INSERT INTO impresion (Id_Servicio, NomDoc, Color, tipoDocumento, numCopias, PrecioTotal, Entregado, MontoPago, DiaEntrega, MesEntrega, AnioEntrega) 
                                 VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)";
                $stmtPrint = $conexion->prepare($sqlInsertPrint);
                if (!$stmtPrint) {
                    throw new Exception("Error preparando declaración para impresion: " . $conexion->error);
                }
                
                // Extraer día, mes y año de la fecha de entrega
                $diaEntrega = date('d', strtotime($fechaEntrega));
                $mesEntrega = date('m', strtotime($fechaEntrega));
                $anioEntrega = date('Y', strtotime($fechaEntrega));
                
                // Entregado = 0 (falso) por defecto, MontoPago = costo inicialmente
                $stmtPrint->bind_param("isisiidiii", $idServicio, $nomDoc, $color, $tipoDocumento, $numCopias, $costoPorServicio, $costoPorServicio, $diaEntrega, $mesEntrega, $anioEntrega);
                
                if (!$stmtPrint->execute()) {
                    throw new Exception("Error al ejecutar inserción en impresion: " . $stmtPrint->error);
                }
                
                // Todo salió bien, confirmar la transacción
                if (!$conexion->commit()) {
                    throw new Exception("Error al confirmar la transacción: " . $conexion->error);
                }
                
                echo "<script>alert('Pedido de impresión registrado correctamente (ID: $idServicio)');</script>";
                echo "<script>window.location.href = 'ImprPendientes.php';</script>"; // Redirigir a pendientes
                
                $stmtPrint->close();
                $stmtService->close();
                
            } catch (Exception $e) {
                // Si algo falla, revertir la transacción
                $conexion->rollback();
                echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Impresion</title>
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
            margin-left: auto;
            margin-right: auto;
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
            width: 100%;
        }
        
        .submit-btn:hover {
            background-color: #1a2657;
        }
        
        .color-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Centrar el formulario */
        .form-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="column" id="columnaTitulo">
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <h2 id="textoBajoh1" class="titulo">Registrar Pedido de Impresión</h2> 
        </div>
    </div>
    
    <div class="container">
        <div class="form-wrapper">
            <div class="form-container">
                <h2>Nuevo Pedido de Impresión</h2>
                <form method="POST" action="" id="printForm">
                    <div class="form-group">
                        <label for="tipo_documento">Tipo de documento:</label>
                        <select id="tipo_documento" name="tipo_documento" required>
                            <option value="">Seleccione...</option>
                            <option value="Word">Documento Word</option>
                            <option value="PDF">PDF</option>
                            <option value="Excel">Hoja de Cálculo</option>
                            <option value="Imagen">Imagen</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nom_doc">Nombre del documento:</label>
                        <input type="text" id="nom_doc" name="nom_doc" placeholder="Ej: Tarea Matemáticas, Contrato Laboral, etc." required>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción del documento:</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Ingrese la descripción detallada del documento a imprimir..." required></textarea>
                        <small style="color: #666;">Esta descripción se guardará en el sistema</small>
                    </div>

                    <div class="form-group">
                        <label for="fecha_entrega">Fecha de entrega:</label>
                        <input type="date" id="fecha_entrega" name="fecha_entrega" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="num_copias">Número de Copias:</label>
                        <input type="number" id="num_copias" name="num_copias" min="1" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="color" name="color" value="1">
                            <label for="color">Impresión a Color</label>
                        </div>
                        <div class="color-info">
                            (Marcado = Color, Sin marcar = Blanco y Negro)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="costo_por_servicio">Costo del servicio:</label>
                        <input type="number" id="costo_por_servicio" name="costo_por_servicio" step="0.01" min="0.01" required>
                    </div>
                    
                    <button type="submit" name="add_print_order" class="submit-btn">Registrar Pedido</button>
                </form>
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
        
        // Validación del formulario
        document.getElementById('printForm').addEventListener('submit', function(event) {
            const tipoDocumento = document.getElementById('tipo_documento').value;
            const nomDoc = document.getElementById('nom_doc').value;
            const descripcion = document.getElementById('descripcion').value;
            const fechaEntrega = document.getElementById('fecha_entrega').value;
            const numCopias = parseInt(document.getElementById('num_copias').value);
            const costo = parseFloat(document.getElementById('costo_por_servicio').value);
            const fechaHoy = new Date().toISOString().split('T')[0];
            
            if (!tipoDocumento || !nomDoc || !descripcion || !fechaEntrega || isNaN(numCopias) || numCopias <= 0 || isNaN(costo) || costo <= 0) {
                alert('Por favor complete todos los campos correctamente. El costo y número de copias deben ser mayores que cero.');
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
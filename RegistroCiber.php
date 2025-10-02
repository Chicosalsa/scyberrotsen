<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Función para encontrar el siguiente ID disponible
function getNextAvailableId($conexion) {
    $sqlLastService = "SELECT MIN(t1.Id_Servicio + 1) AS next_id
                       FROM servicio t1
                       LEFT JOIN servicio t2 ON t1.Id_Servicio + 1 = t2.Id_Servicio
                       WHERE t2.Id_Servicio IS NULL";
    
    $result = $conexion->query($sqlLastService);
    
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['next_id'] !== null) {
            return $row['next_id'];
        }
    }
    
    // Si la consulta falla o no hay huecos, obtenemos el máximo + 1
    $sqlMaxId = "SELECT COALESCE(MAX(Id_Servicio), 0) + 1 AS next_id FROM servicio";
    $result = $conexion->query($sqlMaxId);
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row['next_id'];
    }
    
    return 1; // Si todo falla, empezamos en 1
}

// Procesar nuevo servicio de ciber
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_cyber_service'])) {
    // Obtener datos del formulario
    $nombreCliente = mysqli_real_escape_string($conexion, $_POST['nombre_cliente']);
    $tipoServicio = mysqli_real_escape_string($conexion, $_POST['tipo_servicio']);
    $horaInicio = mysqli_real_escape_string($conexion, $_POST['hora_inicio']);
    $tiempoMinutos = intval($_POST['tiempo_minutos']);
    $costoPorHora = floatval($_POST['costo_por_hora']);
    $descripcion = mysqli_real_escape_string($conexion, $_POST['descripcion']);

    // Validar datos
    if (empty($nombreCliente) || empty($tipoServicio) || empty($horaInicio) || $tiempoMinutos <= 0 || $costoPorHora < 0) {
        echo "<script>alert('Por favor complete todos los campos correctamente.');</script>";
    } else {
        try {
            // Establecer la zona horaria correcta
            date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria

            // Calcular hora final y costo total
            $fechaHoy = date('Y-m-d');
            $horaInicioObj = new DateTime($fechaHoy . ' ' . $horaInicio);
            $horaFinalObj = clone $horaInicioObj;
            $horaFinalObj->add(new DateInterval('PT'.$tiempoMinutos.'M'));
            
            $horas = $tiempoMinutos / 60;
            $costoTotal = round($horas * $costoPorHora, 2);
            $horaFinal = $horaFinalObj->format('Y-m-d H:i:s');
            $horaInicio = $horaInicioObj->format('Y-m-d H:i:s');

            // Iniciar transacción
            $conexion->begin_transaction();

            // Obtener un ID que no esté en uso
            $newServiceId = getNextAvailableId($conexion);

            // Insertar en la tabla servicio primero
            $nombreServicio = "Servicio Ciber - " . $tipoServicio;
            
            $sqlInsertService = "INSERT INTO servicio (Id_Servicio, nombreServicio, Descripcion, Precio) 
                                VALUES (?, ?, ?, ?)";
            
            $stmt = $conexion->prepare($sqlInsertService);
            if (!$stmt) {
                throw new Exception("Error en la preparación del statement para servicio: " . $conexion->error);
            }
            
            $stmt->bind_param("issd", $newServiceId, $nombreServicio, $descripcion, $costoTotal);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar servicio: " . $stmt->error);
            }
            
            $stmt->close();

            // Insertar en la tabla serviciointernet
            $sqlInsertCyber = "INSERT INTO serviciointernet (Id_Servicio, nombreCliente, HoraInicio, HoraFinal, costoPorHora, TipoServicio) 
                              VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conexion->prepare($sqlInsertCyber);
            if (!$stmt) {
                throw new Exception("Error en la preparación del statement para serviciointernet: " . $conexion->error);
            }
            
            $stmt->bind_param("isssds", $newServiceId, $nombreCliente, $horaInicio, $horaFinal, $costoPorHora, $tipoServicio);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar servicio de internet: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Confirmar transacción
            $conexion->commit();
            
            echo "<script>alert('Servicio de ciber registrado correctamente (ID: $newServiceId)\\nDuración: $tiempoMinutos minutos ($horas horas)\\nCosto total: $$costoTotal');</script>";
            echo "<script>window.location.href = 'RegistroCiber.php';</script>";
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conexion->rollback();
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
    <!--No tocar -->
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="iconSet.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ciber Rotsen - Ciber</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="menuStyleSheet.css">

</head>
<body>
    <style>
            /* Botón de subir (nuevo) */
    .botonSubir {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 10vh;
    }

    /* Contenedor principal desplazado por el sidebar (nuevo) */
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
        margin: 0 auto; /* Esto ayuda a centrar la tabla */
        width: auto; /* Cambiado de 100% a auto para que no ocupe todo el ancho */
        max-width: 95%; /* Asegura que la tabla no sea más ancha que el contenedor */
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

    /* Estilos para inputs (nuevo) */
    input[type="text"], 
    input[type="number"] {
        width: 100%;
        padding: 5px;
        box-sizing: border-box;
    }

    /* Barra de búsqueda (nuevo) */
    .search-box {
        margin-bottom: 20px;
    }

    .search-box input {
        width: 300px;
        padding: 10px;
        font-size: 16px;
    }

    /* Botones (nuevo) */
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

    /* Mensajes de error (nuevo) */
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
        #wa{
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            margin-left: 550px; /* Added margin-left to move it to the right */
            flex-direction: column;
            justify-content: center;
            align-items: center;

        }
        .form-container {
            background-color: #f0f7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-width: 800px; /* Increased from 600px to 800px (added 200px) */
            margin-left: auto;
            margin-right: auto; /* Added these two lines to center the container */
        }
        table {
            background-color: #f0f7ff;
            border-collapse: collapse;
            margin: 0 auto;
            width: auto;
            max-width: 95%;
            margin-left: 450px; /* This will move the table 200px to the right */
        }
    </Style>

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
            <h1 id="titulo1" class="titulo">Ciber Rotsen</h1>
            <h2 id="textoBajoh1" class="titulo">Registro de servicios de ciber</h2> 
        </div>
    </div>
        
    <div class="container" id="wa">
        <div class="form-container">
            <h2>Nuevo Servicio de Ciber</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nombre_cliente">Nombre del Cliente:</label>
                    <input type="text" id="nombre_cliente" name="nombre_cliente" required>
                </div>
                
                <div class="form-group">
                    <label for="tipo_servicio">Tipo de Servicio:</label>
                    <select id="tipo_servicio" name="tipo_servicio" required>
                        <option value="">Seleccione...</option>
                        <option value="Internet">Internet</option>
                        <option value="Juegos">Juegos</option>
                        <option value="Ofimatica">Ofimática</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Horario:</label>
                    <div class="time-input-group">
                        <div>
                            <label for="hora_inicio">Hora de Inicio:</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" required>
                        </div>
                        <div>
                            <label for="tiempo_minutos">Duración (minutos):</label>
                            <input type="number" id="tiempo_minutos" name="tiempo_minutos" min="1" value="60" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="costo_por_hora">Costo por Hora:</label>
                    <input type="number" id="costo_por_hora" name="costo_por_hora" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción (Opcional):</label>
                    <textarea id="descripcion" name="descripcion"></textarea>
                </div>
                
                <button type="submit" name="add_cyber_service" class="submit-btn">Registrar Servicio</button>
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

        // Establecer hora actual en el campo de hora de inicio
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('hora_inicio').value = `${hours}:${minutes}`;
            
            // Establecer costo por hora por defecto
            document.getElementById('costo_por_hora').value = '15.00';
        });
    </script>
</body>
</html>
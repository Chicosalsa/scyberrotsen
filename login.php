<?php
session_start();
require_once 'conexion.php'; // Incluir la conexión a la base de datos

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Si ya ha iniciado sesión, redirigir a menu.php
    header("Location: menu.php");
    exit;
}

// Segundos que se bloquea el sistema
$segundosBloqueo = 30;

// Inicializar las variables de sesión si no existen
if (!isset($_SESSION['intento_login']) || !is_array($_SESSION['intento_login'])) {
    $_SESSION['intento_login'] = [];
}

if (!isset($_SESSION['tiempo_bloqueo']) || !is_array($_SESSION['tiempo_bloqueo'])) {
    $_SESSION['tiempo_bloqueo'] = [];
}

$error_login = '';

// Inicio de sesión
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST["Usuario"]) && !empty($_POST["Contrasena"])) {
        $Usuario = $_POST["Usuario"];
        $Contrasena = $_POST["Contrasena"];

        // Verificar si el usuario está bloqueado antes de intentar iniciar sesión
        if (isset($_SESSION['tiempo_bloqueo'][$Usuario]) && time() < $_SESSION['tiempo_bloqueo'][$Usuario]) {
            $error_login = "Has excedido el número de intentos. El usuario será desbloqueado dentro de un minuto.";
        } else {
            $mysqli = mysqli_connect("localhost", "root", "", "scyberrotsen");
            if ($mysqli === false) {
                die("ERROR: No se pudo conectar. " . mysqli_connect_error());
            }

            // Consulta SQL para obtener el usuario, contraseña, correo y rol (Admin)
            $sql = "SELECT Usuario, Contrasena, Correo, Id_Rol FROM users WHERE Usuario = ?";

            if ($stmt = mysqli_prepare($mysqli, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $Usuario);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);

                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $UsuarioResultado, $ContrasenaResultado, $CorreoResultado, $AdminResultado);

                        if (mysqli_stmt_fetch($stmt)) {
                            // Verificar contraseña usando password_verify para hash o comparación directa para contraseñas sin hash
                            $contrasena_valida = false;
                            if (password_verify($Contrasena, $ContrasenaResultado)) {
                                // La contraseña está hasheada
                                $contrasena_valida = true;
                            } elseif ($Contrasena == $ContrasenaResultado) {
                                // La contraseña está en texto plano (para compatibilidad con datos existentes)
                                $contrasena_valida = true;
                                
                                // Actualizar la contraseña a hash en la base de datos
                                $nueva_contrasena_hash = password_hash($Contrasena, PASSWORD_DEFAULT);
                                $sql_update = "UPDATE users SET Contrasena = ? WHERE Usuario = ?";
                                if ($stmt_update = mysqli_prepare($mysqli, $sql_update)) {
                                    mysqli_stmt_bind_param($stmt_update, "ss", $nueva_contrasena_hash, $Usuario);
                                    mysqli_stmt_execute($stmt_update);
                                    mysqli_stmt_close($stmt_update);
                                }
                            }

                            if ($contrasena_valida) {
                                $_SESSION["loggedin"] = true;
                                $_SESSION["username"] = $UsuarioResultado; // Cambiado de "usuario" a "username"
                                $_SESSION["Admin"] = $AdminResultado; // Almacenar el rol del usuario

                                echo "<!-- Debug: Sesión iniciada correctamente -->";
                                echo "<!-- Debug: username=" . $_SESSION["username"] . ", Admin=" . $_SESSION["Admin"] . " -->";

                                unset($_SESSION['intento_login'][$Usuario]);
                                unset($_SESSION['tiempo_bloqueo'][$Usuario]);

                                header("Location: menu.php");
                                exit;
                            } else {
                                $_SESSION['intento_login'][$Usuario] = ($_SESSION['intento_login'][$Usuario] ?? 0) + 1;
                                $intentos_restantes = 3 - $_SESSION['intento_login'][$Usuario];

                                $error_login = "Contraseña incorrecta. Te quedan $intentos_restantes intento(s).";

                                if ($intentos_restantes <= 0) {
                                    $_SESSION['tiempo_bloqueo'][$Usuario] = time() + $segundosBloqueo;
                                    $error_login = "Has excedido el número de intentos. Espera a un momento.";
                                }
                            }
                        }
                    } else {
                        $_SESSION['intento_login'][$Usuario] = ($_SESSION['intento_login'][$Usuario] ?? 0) + 1;
                        $intentos_restantes = 3 - $_SESSION['intento_login'][$Usuario];

                        $error_login = "Usuario no encontrado. Te quedan $intentos_restantes intento(s).";

                        if ($intentos_restantes <= 0) {
                            $_SESSION['tiempo_bloqueo'][$Usuario] = time() + $segundosBloqueo;
                            $error_login = "Has excedido el número de intentos. Espera un momento.";
                        }
                    }
                } else {
                    echo "Oops! Algo salió mal. Por favor, intenta de nuevo más tarde.";
                }

                mysqli_stmt_close($stmt);
            }

            mysqli_close($mysqli);
        }
    } else {
        $error_login = "Por favor, completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title> <!-- titulo en la pestaña -->
    <link rel="icon" href="iconSet.png" type="image/png"> <!-- icono de la pestaña (no cambiar pq si no tengo que crear otra imagen de nuevo(ni idea de pq sea eso)) -->
    <link rel="stylesheet" href="loginStyleSheet.css">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script><!-- importa una libreria de iconos -->
<!--Revisar loginStyleSheet para ver los estilos -->
    <style>
        
    </style>
</head>
<body>
<div class="screen-1">
    <form method="POST">
        <img class='logo' src='logo.png' alt='Logo'> <!-- Logo de la empresa -->

        <div class="email"><!-- formulario para el inicio de sesion -->
            <div class="sec-2">
                <ion-icon name="person-outline"></ion-icon> <!--Icono de usuario en el field de usuario-->
                <input type="text" name="Usuario" placeholder="Nombre de usuario" required> <!-- field de usuario de tipo texto, tiene grabado la frase nombre usuario y debe de ser llenado a fuerzas -->
            </div>
        </div>

        <div class="password">
            <div class="sec-2">
                <ion-icon name="lock-closed-outline"></ion-icon> <!-- icono de un ojo cerrado para la funcion de mostrar contrasena (Aun no implementado) -->
                <input type="password" name="Contrasena" placeholder="Contraseña" required> <!-- field de contrasenade tipo password y es requerida a fuerza -->
                <ion-icon class="show-hide" name="eye-outline"></ion-icon> <!-- icono de un ojo abierto para la funcion de mostrar contrasena (Aun no implementado) -->
            </div>
        </div>

        <div class="error-message" id="errorMessage"><?php echo $error_login; ?></div><!-- muestra el mensaje de error al equivocarse de contrasena o usuario -->

        <button type="submit" class="login" id="loginButton">Entrar</button> <!-- boton para mandar la informacion ingresada en los field para arovar o no el login -->
    </form>

    <div class="footer">
        <span onclick="window.location.href='Registro.php'">Registrar Usuario</span><!-- redirecciona a crear usuario -->
    </div>
</div>

<script>
    //contantes para el mensaje de error
    const tiempoBloqueo = <?php echo isset($_SESSION['tiempo_bloqueo'][$Usuario]) ? $_SESSION['tiempo_bloqueo'][$Usuario] : 0; ?>; 
    const segundosBloqueo = <?php echo $segundosBloqueo; ?>; // Inporta los Segundos bloqueados (se cambia en la constante $segundosBloqueo que esta en la linea 4) a js
    const intentosRestantes = <?php echo $intentos_restantes ?? 3; ?>; // importa el numero de intentos restantes.

    //Desabilita el botón de login y muestra mensaje de error por n segundos
    const loginButton = document.getElementById('loginButton');
    const errorMessage = document.getElementById('errorMessage');

    //Muestra los intentos restantes si se equivoca en el primer input
    if (intentosRestantes < 3 && tiempoBloqueo === 0) {
        errorMessage.textContent = `Usuario o Contraseña incorrecta \nTe quedan ${intentosRestantes} intento(s).`;
    }


    //logica para que el boton muestre el mensaje junto a los segundos restantes
    if (tiempoBloqueo > 0) {//si hay un tiempo de bloqueo se ejecuta el codigo
        const tiempoActual = Math.floor(Date.now() / 1000);
        let tiempoRestante = tiempoBloqueo - tiempoActual;

        if (tiempoRestante > 0) {//si el tiempo que resta es mayor a 0 o aun no acaba se ejecuta el codigo
            loginButton.disabled = true; //Se desactiva el boton para entrar 
            loginButton.textContent = `Espera ${tiempoRestante} segundos`;//Se muestra el mensaje junto a los segundos a esperar

            const intervalo = setInterval(() => {//se crea una constante llamada intervalo como Interval 
                tiempoRestante--;//le resta 1 al tiempo restante

                if (tiempoRestante > 0) {
                    loginButton.textContent = `Espera ${tiempoRestante} segundos`;//Actualiza la variable ${tiempoRestante} para que muestre los segundos
                } else {
                    //limpia el intervalo
                    clearInterval(intervalo);
                    loginButton.disabled = false; //Habilita el boton
                    loginButton.textContent = 'Entrar';//cambia el texto del boton a entrar
                    errorMessage.textContent = '';//limpia el mensaje de error
                }
            }, 1000);//establece un delay de 1000 ms o 1 segundo
        }
    }

    //constantes para que pueda muestrar la contrasena
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('passwordField');

    togglePassword.addEventListener('click', function () {
        // Cambiar el tipo de input entre 'password' y 'text'
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);

        // Cambiar el ícono entre 'eye-outline' y 'eye-off-outline'
        this.setAttribute('name', type === 'password' ? 'eye-outline' : 'eye-off-outline');
    });
</script>
</body>
</html>
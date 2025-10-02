<?php
session_start();

$error_registro = '';

//registra a un usuario (no mover)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["admin_correo"]) && isset($_POST["admin_contrasena"]) && isset($_POST["nuevo_usuario"]) && isset($_POST["nuevo_correo"]) && isset($_POST["nueva_contrasena"])) {
        $admin_correo = $_POST["admin_correo"];
        $admin_contrasena = $_POST["admin_contrasena"];
        $nuevo_usuario = $_POST["nuevo_usuario"];
        $nuevo_correo = $_POST["nuevo_correo"];
        $nueva_contrasena = $_POST["nueva_contrasena"];

        $mysqli = mysqli_connect("localhost", "root", "", "scyberrotsen");
        if ($mysqli === false) {
            die("ERROR: No se pudo conectar. " . mysqli_connect_error());
        }

        // Verificar credenciales de administrador
        $sql = "SELECT Correo, Contrasena FROM users WHERE Correo = ? AND Id_Rol = 1";
        if ($stmt = mysqli_prepare($mysqli, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $admin_correo);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $correoResultado, $contrasenaResultado);
                    mysqli_stmt_fetch($stmt);

                    // Verificar contraseña usando password_verify para hash o comparación directa para contraseñas sin hash
                    $contrasena_valida = false;
                    if (password_verify($admin_contrasena, $contrasenaResultado)) {
                        // La contraseña está hasheada
                        $contrasena_valida = true;
                    } elseif ($admin_contrasena == $contrasenaResultado) {
                        // La contraseña está en texto plano (para compatibilidad con datos existentes)
                        $contrasena_valida = true;
                    }

                    if ($contrasena_valida) {
                        // Verificar que el usuario no exista ya
                        $sql_check = "SELECT COUNT(*) FROM users WHERE Usuario = ? OR Correo = ?";
                        if ($stmt_check = mysqli_prepare($mysqli, $sql_check)) {
                            mysqli_stmt_bind_param($stmt_check, "ss", $nuevo_usuario, $nuevo_correo);
                            mysqli_stmt_execute($stmt_check);
                            mysqli_stmt_bind_result($stmt_check, $count);
                            mysqli_stmt_fetch($stmt_check);
                            mysqli_stmt_close($stmt_check);

                            if ($count > 0) {
                                $error_registro = "El usuario o correo ya existe.";
                            } else {
                                // Hashear la nueva contraseña
                                $nueva_contrasena_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

                                // Insertar nuevo usuario
                                $sql = "INSERT INTO users (Usuario, Correo, Contrasena, Id_Rol) VALUES (?, ?, ?, 0)";
                                if ($stmt = mysqli_prepare($mysqli, $sql)) {
                                    mysqli_stmt_bind_param($stmt, "sss", $nuevo_usuario, $nuevo_correo, $nueva_contrasena_hash);

                                    if (mysqli_stmt_execute($stmt)) {
                                        $error_registro = "Usuario registrado exitosamente.";
                                    } else {
                                        $error_registro = "Error al registrar el usuario.";
                                    }
                                }
                            }
                        }
                    } else {
                        $error_registro = "Contraseña de administrador incorrecta.";
                    }
                } else {
                    $error_registro = "Correo de administrador no válido.";
                }
            } else {
                $error_registro = "Oops! Algo salió mal. Por favor, intenta de nuevo más tarde.";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_close($mysqli);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link rel="icon" href="iconSet.png" type="image/png">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <!-- css -->
    <style>
        
                :root {
            --primary: hsl(213deg 85% 97%);
            --secondary: hsl(233deg 36% 38%);
            --background: hsl(218deg 50% 91%);
            --white: hsl(0deg 0% 100%);
            --shadow: hsl(231deg 62% 94%);
        }

        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--background);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            user-select: none;
        }

        .screen-1 {
            background: var(--primary);
            padding: 2em;
            display: flex;
            flex-direction: column;
            border-radius: 30px;
            box-shadow: 0 0 2em var(--shadow);
            gap: 2em;
            max-width: 400px;
            width: 90%;
        }

        .logo {
            margin: -3em auto 0;
            width: 300px;  /* Increased from 100px to 150px */
            height: 300px; /* Increased from 100px to 150px */
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.5em; /* Added gap between form elements */
        }

        .email, .password {
            background: var(--white);
            box-shadow: 0 0 2em var(--shadow);
            padding: 1em;
            display: flex;
            flex-direction: column;
            gap: 0.5em;
            border-radius: 20px;
            color: hsl(0deg 0% 30%);
        }

        input {
            outline: none;
            border: none;
            width: 100%;
        }

        input::placeholder {
            color: hsl(0deg 0% 0%);
            font-size: 0.9em;
        }

        .sec-2 {
            display: flex;
            align-items: center;
            gap: 0.5em;
        }

        ion-icon {
            color: hsl(0deg 0% 30%);
            font-size: 1.2em;
        }

        .login {
            margin-top: 1em; /* Added space above the login button */
            padding: 1em;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .login:hover {
            opacity: 0.9;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            font-size: 0.8em;
            color: hsl(0deg 0% 37%);
        }

        .footer span {
            cursor: pointer;
            transition: color 0.3s;
        }

        .footer span:hover {
            color: var(--secondary);
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 1em;
        }
        ul {
  list-style-type: none;
  margin: 0;
  padding: 0;
}
    </style>
</head>
<body>
    <div class="screen-1">
        <form method="POST">
            <img class='logo' src='logo.png' alt='Logo'> <!-- logo del negocio -->

            <?php if (!empty($error_registro)): ?>
                <div class="error-message"><?= $error_registro ?></div>
            <?php endif; ?>

            <div class="email">
                <div class="sec-2">
                    <ion-icon name="mail-outline"></ion-icon>
                    <input type="email" name="admin_correo" placeholder="Correo de administrador" required>
                </div>
            </div>

            <div class="password">
                <div class="sec-2">
                    <ion-icon name="lock-closed-outline"></ion-icon>
                    <input type="password" name="admin_contrasena" placeholder="Contraseña de administrador" required>
                </div>
            </div>

            <div class="email">
                <div class="sec-2">
                    <ion-icon name="person-outline"></ion-icon>
                    <input type="text" name="nuevo_usuario" placeholder="Nombre de usuario" required>
                </div>
            </div>

            <div class="email">
                <div class="sec-2">
                    <ion-icon name="mail-outline"></ion-icon>
                    <input type="email" name="nuevo_correo" placeholder="Correo del nuevo usuario" required>
                </div>
            </div>

            <div class="password">
                <div class="sec-2">
                    <ion-icon name="lock-closed-outline"></ion-icon>
                    <input type="password" name="nueva_contrasena" placeholder="Contraseña del nuevo usuario" required>
                </div>
            </div>

            <button type="submit" class="login">Registrar</button>
        </form>

        <div class="footer">
            <span onclick="window.location.href='login.php'" >Iniciar Sesión</span>
        </div>
    </div>
</body>
</html>
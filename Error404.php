<?php
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 404 - Página no encontrada</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .error-container {
            text-align: center;
            background-color: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 600px;
        }
        h1 {
            font-size: 72px;
            margin: 0;
            color: #e74c3c;
        }
        h2 {
            margin-top: 0;
            color: #333;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .back-button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        .back-button:hover {
            background-color: #2980b9;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>Página no encontrada</h2>
        <p>Lo sentimos, la página que estás buscando no existe o ha sido movida.</p>
        <p>Puedes intentar volver a la página de inicio o contactarnos si crees que esto es un error.</p>
        <a href="/" class="back-button">Volver al inicio</a>
    </div>
</body>
</html>
<?php exit; ?>
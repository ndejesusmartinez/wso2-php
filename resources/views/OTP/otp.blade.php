<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu Código OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 10px 0;
        }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            background-color: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #888;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Código OTP</h1>
        </div>
        <p>Hola,</p>
        <p>Tu código OTP es el siguiente:</p>
        <div class="otp-code">
            {{ $otp }}
        </div>
        <p>Por favor, ingresa este código en la página de verificación para completar el proceso.</p>
        <p>Si no solicitaste este código, por favor ignora este mensaje.</p>
        <div class="footer">
            <p>Este código es válido por 5 minutos.</p>
            <p>&copy; {{ date('Y') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>

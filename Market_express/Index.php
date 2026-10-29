<?php
// Configuración básica del sistema
$pageTitle = "Market Express - Punto de Venta";
$copyrightText = "© 2026 Market express - todos los derechos son reservados a estudiantes de 2B del Instituto Nacional de Apopa (INA)";
$logoPath = "logo.jpg";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Fuentes e Íconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">
</head>
<body class="bg-welcome">

    <main class="welcome-container">
        <!-- LOGO -->
        <div class="logo-box">
            <img src="<?php echo $logoPath; ?>" alt="Market Express Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/95/29314a/decab0?text=LOGO';">
        </div>

        <!-- TÍTULOS DE BIENVENIDA -->
        <h1 class="brand-title">Market <span>Express</span></h1>
        <p class="brand-subtitle">
            Ahorra tiempo, vive mejor.
        </p>

        <!-- BOTONES DE NAVEGACIÓN -->
        <div class="button-group">
            <a href="login.php" class="btn btn-primary">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar Sesión
            </a>
            <a href="registro.php" class="btn btn-secondary">
                <i class="fa-solid fa-user-plus"></i>
                Registrarse
            </a>
        </div>
    </main>

    <!-- PIE DE PÁGINA -->
    <footer>
        <?php echo $copyrightText; ?>
    </footer>

</body>
</html>
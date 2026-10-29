<?php
session_start();
require_once 'conexion.php';

$pageTitle = "Iniciar Sesión - Market Express";
$copyrightText = "© 2026 Market express - todos los derechos son reservados a estudiantes de 2B del Instituto Nacional de Apopa (INA)";
$logoPath = "logo.jpg";
$error = '';
$step2FA = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===================================================
    // CASO 1: VERIFICAR PIN DE 2FA (PASO 2)
    // ===================================================
    if (isset($_POST['action']) && $_POST['action'] === 'verify_pin') {
        $pinIngresado = trim($_POST['pin_2fa'] ?? '');
        $tempAdminId = $_SESSION['temp_admin_id'] ?? null;

        if ($tempAdminId) {
            $stmt = $pdo->prepare("SELECT id, nombre_completo, pin_2fa FROM administradores WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $tempAdminId]);
            $admin = $stmt->fetch();

            // Verificación flexible (Soporta Hash y Texto Plano)
            if ($admin && !empty($admin['pin_2fa']) && (password_verify($pinIngresado, $admin['pin_2fa']) || $pinIngresado === $admin['pin_2fa'])) {
                // Autenticación exitosa
                unset($_SESSION['temp_admin_id']);
                $_SESSION['usuario_id'] = $admin['id'];
                $_SESSION['nombre'] = $admin['nombre_completo'];
                $_SESSION['rol'] = 'admin';

                header("Location: market_express.php");
                exit;
            } else {
                $error = "PIN de seguridad incorrecto.";
                $step2FA = true; // Mantener formulario de PIN
            }
        } else {
            header("Location: login.php");
            exit;
        }

    // ===================================================
    // CASO 2: LOGIN PRINCIPAL (CORREO Y CONTRASEÑA)
    // ===================================================
    } else {
        $correo = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($correo) && !empty($password)) {
            
            // 1. Buscar en ADMINISTRADORES
            $stmtAdmin = $pdo->prepare("SELECT id, nombre_completo, correo, password, estado_2fa, pin_2fa, 'admin' AS rol FROM administradores WHERE correo = :correo LIMIT 1");
            $stmtAdmin->execute([':correo' => $correo]);
            $usuario = $stmtAdmin->fetch();

            // 2. Si no coincide en administradores, buscar en USUARIOS (clientes)
            if (!$usuario) {
                $stmtUser = $pdo->prepare("SELECT id, nombre_completo, correo, password, 'cliente' AS rol FROM usuarios WHERE correo = :correo LIMIT 1");
                $stmtUser->execute([':correo' => $correo]);
                $usuario = $stmtUser->fetch();
            }

            // 3. Verificación flexible (Hash y Texto Plano)
            if ($usuario && (password_verify($password, $usuario['password']) || $password === $usuario['password'])) {
                
                // ¿Es Administrador y tiene 2FA activado explícitamente?
                if ($usuario['rol'] === 'admin' && isset($usuario['estado_2fa']) && (int)$usuario['estado_2fa'] === 1 && !empty($usuario['pin_2fa'])) {
                    $_SESSION['temp_admin_id'] = $usuario['id'];
                    $step2FA = true; // Activar vista de PIN
                } else {
                    // Acceso directo
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre'] = $usuario['nombre_completo'];
                    $_SESSION['rol'] = $usuario['rol'];

                    header("Location: market_express.php");
                    exit;
                }
            } else {
                $error = "Correo o contraseña incorrectos.";
            }
        } else {
            $error = "Por favor, completa todos los campos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">
</head>
<body class="bg-auth">

    <main class="auth-card-horizontal" style="max-width: 650px;">
        
        <!-- PANEL IZQUIERDO -->
        <div class="auth-brand-side">
            <div class="logo-box" style="width: 80px; height: 80px; margin-bottom: 10px;">
                <img src="<?php echo $logoPath; ?>" alt="Market Express Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/80/29314a/decab0?text=LOGO';">
            </div>
            <h2>Market Express</h2>
            <p><?php echo $step2FA ? 'Verificación de Seguridad' : 'Accede a tu cuenta para continuar'; ?></p>
        </div>

        <!-- PANEL DERECHO -->
        <div class="auth-form-side">
            
            <?php if (!empty($error)): ?>
                <div style="background: rgba(217, 83, 79, 0.2); border: 1px solid #d9534f; color: #ff8b88; padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 15px;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($step2FA): ?>
                <!-- VISTA 2: INGRESO DE PIN 2FA -->
                <h3 style="font-size: 1.2rem; margin-bottom: 5px; color: var(--color-light);">Ingresa tu PIN 2FA</h3>
                <p style="font-size: 0.8rem; color: var(--color-beige); margin-bottom: 15px;">Introduce el código numérico de 6 a 10 dígitos configurado en tu perfil.</p>

                <form action="login.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="verify_pin">

                    <div class="form-group-compact">
                        <label for="pin_2fa">PIN de Seguridad</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key input-icon" style="left: 12px;"></i>
                            <input type="password" id="pin_2fa" name="pin_2fa" value="" minlength="6" maxlength="10" pattern="[0-9]{6,10}" class="form-control-compact" placeholder="" autofocus required autocomplete="new-password" style="text-align: center; letter-spacing: 4px;">
                            <button type="button" class="toggle-password" style="right: 12px;" onclick="togglePasswordVisibility('pin_2fa', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-compact w-100" style="margin-top: 12px;">
                        <i class="fa-solid fa-shield-check"></i>
                        Validar PIN e Ingresar
                    </button>
                </form>

                <div class="auth-footer-link" style="margin-top: 15px; text-align: left;">
                    <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Volver al inicio de sesión</a>
                </div>

            <?php else: ?>
                <!-- VISTA 1: INICIO DE SESIÓN NORMAL -->
                <h3 style="font-size: 1.3rem; margin-bottom: 15px; color: var(--color-light);">Iniciar Sesión</h3>

                <form action="login.php" method="POST">
                    
                    <!-- CORREO ELECTRÓNICO -->
                    <div class="form-group-compact">
                        <label for="email">Correo Electrónico</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon" style="left: 12px;"></i>
                            <input type="text" id="email" name="email" class="form-control-compact" placeholder="tu@correo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                        </div>
                    </div>

                    <!-- CONTRASEÑA -->
                    <div class="form-group-compact">
                        <label for="password">Contraseña</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon" style="left: 12px;"></i>
                            <input type="password" id="password" name="password" class="form-control-compact" placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" class="toggle-password" style="right: 12px;" onclick="togglePasswordVisibility('password', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-compact w-100" style="margin-top: 8px;">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Iniciar Sesión
                    </button>
                </form>

                <div class="auth-footer-link" style="margin-top: 15px; text-align: left;">
                    ¿No tienes cuenta? <a href="registro.php">Regístrate</a>
                    <span style="float: right;"><a href="index.php"><i class="fa-solid fa-arrow-left"></i> Inicio</a></span>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <footer>
        <?php echo $copyrightText; ?>
    </footer>

    <script>
        function togglePasswordVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
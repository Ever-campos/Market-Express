<?php
session_start();
require_once 'conexion.php';

$pageTitle = "Registro - Market Express";
$copyrightText = "© 2026 Market express - todos los derechos son reservados a estudiantes de 2B del Instituto Nacional de Apopa (INA)";
$logoPath = "logo.jpg";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($nombre) || empty($correo) || empty($password) || empty($confirm_password)) {
        $error = "Por favor, completa todos los campos del formulario.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas ingresadas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->execute([':correo' => $correo]);

        if ($stmt->fetch()) {
            $error = "Este correo electrónico ya está registrado.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO usuarios (nombre_completo, correo, password) VALUES (:nombre, :correo, :password)");
            
            if ($insert->execute([':nombre' => $nombre, ':correo' => $correo, ':password' => $passwordHash])) {
                $success = "¡Cuenta creada! Redirigiendo al login...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Ocurrió un error al procesar el registro.";
            }
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

    <main class="auth-card-horizontal">
        
        <!-- PANEL IZQUIERDO (BRANDING) -->
        <div class="auth-brand-side">
            <div class="logo-box" style="width: 85px; height: 85px; margin-bottom: 12px;">
                <img src="<?php echo $logoPath; ?>" alt="Market Express Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/85/29314a/decab0?text=LOGO';">
            </div>
            <h2>Crear Cuenta</h2>
            <p>Únete a Market Express para realizar tus compras rápidamente.</p>
        </div>

        <!-- PANEL DERECHO (FORMULARIO EN 2 COLUMNAS) -->
        <div class="auth-form-side">
            
            <?php if (!empty($error)): ?>
                <div style="background: rgba(217, 83, 79, 0.2); border: 1px solid #d9534f; color: #ff8b88; padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 12px;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div style="background: rgba(92, 184, 92, 0.2); border: 1px solid #5cb85c; color: #81d181; padding: 8px 12px; border-radius: 8px; font-size: 0.8rem; margin-bottom: 12px;">
                    <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="registro.php" method="POST" onsubmit="return validarRegistro()">
                
                <div class="form-grid-2col">
                    <!-- NOMBRE COMPLETO -->
                    <div class="form-group-compact">
                        <label for="nombre">Nombre Completo</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user input-icon" style="left: 12px;"></i>
                            <input type="text" id="nombre" name="nombre" class="form-control-compact" placeholder="Juan Pérez" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required autocomplete="name">
                        </div>
                    </div>

                    <!-- CORREO ELECTRÓNICO -->
                    <div class="form-group-compact">
                        <label for="email">Correo Electrónico</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon" style="left: 12px;"></i>
                            <input type="email" id="email" name="email" class="form-control-compact" placeholder="tu@correo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email">
                        </div>
                    </div>

                    <!-- CONTRASEÑA -->
                    <div class="form-group-compact">
                        <label for="password">Contraseña</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon" style="left: 12px;"></i>
                            <input type="password" id="password" name="password" class="form-control-compact" placeholder="Mínimo 6 carc." required autocomplete="new-password">
                            <button type="button" class="toggle-password" style="right: 12px;" onclick="togglePasswordVisibility('password', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- CONFIRMAR CONTRASEÑA -->
                    <div class="form-group-compact">
                        <label for="confirm_password">Confirmar Contraseña</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-shield-halved input-icon" style="left: 12px;"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control-compact" placeholder="Repite contraseña" required autocomplete="new-password">
                            <button type="button" class="toggle-password" style="right: 12px;" onclick="togglePasswordVisibility('confirm_password', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <small id="pass-match-msg" style="display:none; font-size: 0.75rem; margin-bottom: 8px;"></small>

                <button type="submit" class="btn btn-primary btn-compact w-100" style="margin-top: 8px;">
                    <i class="fa-solid fa-user-plus"></i>
                    Completar Registro
                </button>
            </form>

            <div class="auth-footer-link" style="margin-top: 15px; text-align: left;">
                ¿Ya tienes cuenta? <a href="login.php">Inicia Sesión</a>
                <span style="float: right;"><a href="index.php"><i class="fa-solid fa-arrow-left"></i> Inicio</a></span>
            </div>

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

        function validarRegistro() {
            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            const msg = document.getElementById('pass-match-msg');

            if (pass !== confirmPass) {
                msg.style.display = 'block';
                msg.style.color = '#ff8b88';
                msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Las contraseñas no coinciden';
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
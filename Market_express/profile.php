<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'cliente') {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['usuario_id'];
$mensaje = '';
$tipoMensaje = '';

// Cargar datos actuales del cliente
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header("Location: market_express.php");
    exit;
}

// PROCESAR ACTUALIZACIÓN DE DATOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordIngresada = trim($_POST['password_nueva'] ?? '');

    // 1. Procesar Foto de Perfil
    $nombreFoto = $usuario['foto'] ?? 'default_avatar.png';
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            $nuevoNombreFoto = 'usr_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], 'uploads/' . $nuevoNombreFoto)) {
                $nombreFoto = $nuevoNombreFoto;
            }
        }
    }

    // 2. Validación de Contraseña Actual
    $requierePasswordActual = ($passwordIngresada !== $usuario['password'] || $correo !== $usuario['correo']);
    $passwordValida = true;

    if ($requierePasswordActual) {
        if (empty($passwordActual)) {
            $mensaje = "Para modificar el correo o la contraseña debes ingresar tu contraseña actual.";
            $tipoMensaje = "error";
            $passwordValida = false;
        } else {
            $hashAlmacenado = $usuario['password'];
            if (!password_verify($passwordActual, $hashAlmacenado) && $passwordActual !== $hashAlmacenado) {
                $mensaje = "La contraseña actual ingresada es incorrecta.";
                $tipoMensaje = "error";
                $passwordValida = false;
            }
        }
    }

    if ($passwordValida) {
        // Si cambió la contraseña, la procesa; de lo contrario mantiene la actual
        $passwordFinal = !empty($passwordIngresada) ? $passwordIngresada : $usuario['password'];

        $updateStmt = $pdo->prepare("UPDATE usuarios SET nombre_completo = ?, correo = ?, password = ?, foto = ? WHERE id = ?");
        if ($updateStmt->execute([$nombreCompleto, $correo, $passwordFinal, $nombreFoto, $userId])) {
            $_SESSION['nombre'] = $nombreCompleto;
            $mensaje = "¡Perfil actualizado con éxito!";
            $tipoMensaje = "exito";

            // Recargar datos desde la BD
            $stmt->execute([$userId]);
            $usuario = $stmt->fetch();
        } else {
            $mensaje = "Error al actualizar los datos en la base de datos.";
            $tipoMensaje = "error";
        }
    }
}

$fotoRuta = (!empty($usuario['foto']) && file_exists('uploads/' . $usuario['foto'])) 
            ? 'uploads/' . $usuario['foto'] 
            : 'https://via.placeholder.com/150/29314a/decab0?text=Cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Market Express</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">
    <style>
        .profile-dashboard {
            max-width: 1100px;
            width: 92%;
            margin: 30px auto;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }
        .profile-sidebar, .profile-main-card {
            background: rgba(35, 42, 63, 0.85);
            border: 1px solid rgba(173, 147, 103, 0.3);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            backdrop-filter: blur(10px);
        }
        .avatar-wrapper {
            position: relative;
            width: 130px;
            height: 130px;
            margin: 0 auto 15px auto;
        }
        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--color-gold);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .avatar-upload-btn {
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: var(--color-gold);
            color: #1a1f30;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .avatar-upload-btn:hover { transform: scale(1.1); }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        
        /* CONTENEDOR CON OJITO INTEGRADO Y PEGADO DENTRO DEL CAMPO */
        .input-wrapper-eye {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
        }
        .input-wrapper-eye input {
            width: 100% !important;
            padding-right: 34px !important;
            box-sizing: border-box !important;
        }
        .btn-eye-inside {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--color-gold);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
            margin: 0;
            line-height: 1;
            z-index: 5;
            opacity: 0.85;
            transition: opacity 0.2s;
        }
        .btn-eye-inside:hover { opacity: 1; color: #ffffff; }

        .helper-text {
            font-size: 0.75rem;
            color: var(--color-gold);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .alert-exito { background: rgba(74, 222, 128, 0.15); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
        .alert-error { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

        @media (max-width: 850px) {
            .profile-dashboard { grid-template-columns: 1fr; }
            .form-grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="bg-auth">

    <header class="app-header">
        <div class="header-brand-box">
            <a href="market_express.php" class="btn btn-secondary btn-compact" style="margin-right: 10px;">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <h1 class="header-title">Market <span>Express</span></h1>
        </div>
    </header>

    <div class="profile-dashboard">
        
        <!-- RESUMEN LATERAL -->
        <aside class="profile-sidebar" style="text-align: center;">
            <div class="avatar-wrapper">
                <img id="preview-avatar" src="<?php echo $fotoRuta; ?>" class="avatar-img" alt="Foto Perfil">
                <label for="foto_perfil" class="avatar-upload-btn" title="Cambiar Foto">
                    <i class="fa-solid fa-camera"></i>
                </label>
            </div>
            <h3 style="color: var(--color-light); font-size: 1.2rem; margin-bottom: 5px;"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></h3>
            <p style="color: var(--color-beige); font-size: 0.85rem; opacity: 0.8; margin-bottom: 20px;"><i class="fa-solid fa-user-tag"></i> Cliente de Market Express</p>
            
            <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 12px; text-align: left; font-size: 0.82rem; color: var(--color-beige);">
                <p style="margin-bottom: 6px;"><strong style="color: var(--color-gold);">Correo:</strong><br><?php echo htmlspecialchars($usuario['correo']); ?></p>
                <p><strong style="color: var(--color-gold);">Estado:</strong> Cuenta Activa</p>
            </div>
        </aside>

        <!-- FORMULARIO DE PERFIL -->
        <main class="profile-main-card">
            <h2 style="color: var(--color-gold); font-size: 1.4rem; margin-bottom: 20px;"><i class="fa-solid fa-id-card"></i> Configuración de Mi Perfil</h2>

            <?php if (!empty($mensaje)): ?>
                <div class="alert-box alert-<?php echo $tipoMensaje; ?>">
                    <i class="fa-solid <?php echo $tipoMensaje === 'exito' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form action="profile.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display:none;" onchange="previewImagen(event)">

                <div class="form-grid-2" style="margin-bottom: 20px;">
                    <!-- NOMBRE COMPLETO -->
                    <div class="form-group-compact">
                        <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">Nombre Completo</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" class="form-control-compact" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                    </div>

                    <!-- CORREO ELECTRÓNICO -->
                    <div class="form-group-compact">
                        <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">Correo Electrónico</label>
                        <input type="email" id="correo" name="correo" class="form-control-compact" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required>
                        <p class="helper-text"><i class="fa-solid fa-circle-info"></i> Requiere contraseña actual para modificar.</p>
                    </div>
                </div>

                <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 25px 0;">

                <h4 style="color: var(--color-gold); font-size: 1rem; margin-bottom: 15px;"><i class="fa-solid fa-lock"></i> Seguridad y Cambio de Contraseña</h4>

                <div class="form-grid-2" style="margin-bottom: 20px;">
                    <!-- CONTRASEÑA -->
                    <div class="form-group-compact">
                        <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">Contraseña</label>
                        <div class="input-wrapper-eye">
                            <input type="password" id="password_nueva" name="password_nueva" class="form-control-compact" value="<?php echo htmlspecialchars($usuario['password']); ?>" autocomplete="new-password">
                            <button type="button" class="btn-eye-inside" onclick="togglePasswordVisibility('password_nueva', this)" title="Mostrar/Ocultar">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <p class="helper-text"><i class="fa-solid fa-circle-info"></i> Requiere contraseña actual para cambiar.</p>
                    </div>

                    <!-- CONTRASEÑA ACTUAL -->
                    <div class="form-group-compact" style="background: rgba(0,0,0,0.25); padding: 10px 14px; border-radius: 12px; border: 1px dashed rgba(173, 147, 103, 0.4);">
                        <label style="color: var(--color-gold); font-weight: 700; font-size: 0.85rem;">Contraseña Actual</label>
                        <div class="input-wrapper-eye">
                            <input type="password" id="password_actual" name="password_actual" class="form-control-compact" placeholder="Confirmar clave actual" value="" autocomplete="new-password">
                            <button type="button" class="btn-eye-inside" onclick="togglePasswordVisibility('password_actual', this)" title="Mostrar/Ocultar">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100" style="padding: 12px; font-weight: 700;">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios de Perfil
                </button>
            </form>
        </main>

    </div>

    <script>
        function previewImagen(event) {
            const reader = new FileReader();
            reader.onload = function() {
                document.getElementById('preview-avatar').src = reader.result;
            }
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }

        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
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
<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['usuario_id'];
$mensaje = '';
$tipoMensaje = '';

// Cargar datos actuales del Administrador
$stmt = $pdo->prepare("SELECT * FROM administradores WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    header("Location: market_express.php");
    exit;
}

// PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $passwordActual = $_POST['password_actual'] ?? '';

    // Validar Contraseña Actual
    $hashAlmacenado = $admin['password'];
    $passOk = password_verify($passwordActual, $hashAlmacenado) || ($passwordActual === $hashAlmacenado);

    if (!$passOk) {
        $mensaje = "La contraseña actual ingresada es incorrecta. Confirmación denegada.";
        $tipoMensaje = "error";
    } else {
        // 1. ACTUALIZAR DATOS DEL PERFIL
        if ($accion === 'actualizar_perfil') {
            $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $passwordNueva = trim($_POST['password_nueva'] ?? '');

            // Procesar Foto de Perfil
            $nombreFoto = $admin['foto'] ?? 'default_avatar.png';
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                    $nuevoNombreFoto = 'adm_' . $adminId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], 'uploads/' . $nuevoNombreFoto)) {
                        $nombreFoto = $nuevoNombreFoto;
                    }
                }
            }

            // Si se cambia la contraseña, guardar el nuevo valor
            $passwordFinal = !empty($passwordNueva) ? $passwordNueva : $admin['password'];

            $update = $pdo->prepare("UPDATE administradores SET nombre_completo = ?, correo = ?, password = ?, foto = ? WHERE id = ?");
            if ($update->execute([$nombreCompleto, $correo, $passwordFinal, $nombreFoto, $adminId])) {
                $_SESSION['nombre'] = $nombreCompleto;
                $mensaje = "¡Perfil de administrador actualizado correctamente!";
                $tipoMensaje = "exito";
            } else {
                $mensaje = "Error al guardar en la base de datos.";
                $tipoMensaje = "error";
            }

        // 2. ACTIVAR / ACTUALIZAR 2FA
        } elseif ($accion === 'activar_2fa') {
            $pinInput = trim($_POST['pin_2fa'] ?? '');

            if (!empty($pinInput)) {
                if (!preg_match('/^[0-9]{6,10}$/', $pinInput)) {
                    $mensaje = "El PIN debe contener únicamente números y tener entre 6 y 10 dígitos.";
                    $tipoMensaje = "error";
                } else {
                    $update2fa = $pdo->prepare("UPDATE administradores SET estado_2fa = 1, pin_2fa = ? WHERE id = ?");
                    if ($update2fa->execute([$pinInput, $adminId])) {
                        $mensaje = "¡Sistema 2FA por PIN activado con éxito!";
                        $tipoMensaje = "exito";
                    }
                }
            } else {
                if (!empty($admin['pin_2fa'])) {
                    $update2fa = $pdo->prepare("UPDATE administradores SET estado_2fa = 1 WHERE id = ?");
                    if ($update2fa->execute([$adminId])) {
                        $mensaje = "¡Sistema 2FA reactivado utilizando el PIN previamente guardado!";
                        $tipoMensaje = "exito";
                    }
                } else {
                    $mensaje = "Debes ingresar un PIN numérico de 6 a 10 dígitos para activar el 2FA por primera vez.";
                    $tipoMensaje = "error";
                }
            }

        // 3. DESACTIVAR 2FA (DESDE EL MODAL DE CONFIRMACIÓN)
        } elseif ($accion === 'desactivar_2fa') {
            $update2fa = $pdo->prepare("UPDATE administradores SET estado_2fa = 0 WHERE id = ?");
            if ($update2fa->execute([$adminId])) {
                $mensaje = "La autenticación de dos pasos ha sido desactivada correctamente.";
                $tipoMensaje = "exito";
            }
        }

        // Recargar datos actualizados desde la BD
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
    }
}

$fotoRuta = (!empty($admin['foto']) && file_exists('uploads/' . $admin['foto'])) 
            ? 'uploads/' . $admin['foto'] 
            : 'https://via.placeholder.com/150/29314a/decab0?text=Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Administrador - Market Express</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">
    <style>
        .admin-dashboard {
            max-width: 1150px;
            width: 92%;
            margin: 30px auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
        }
        .admin-sidebar, .admin-card {
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

        /* ENVOLTORIO CON OJITO INCRUSTADO PEGADO DENTRO DEL CAMPO */
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
        .badge-2fa {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .badge-active { background: rgba(74, 222, 128, 0.2); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.4); }
        .badge-inactive { background: rgba(248, 113, 113, 0.2); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.4); }

        /* ESTILOS DE LA VENTANA MODAL */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 14, 23, 0.82);
            backdrop-filter: blur(6px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .modal-card {
            background: #1f2638;
            border: 1px solid rgba(173, 147, 103, 0.4);
            border-radius: 16px;
            padding: 25px 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            text-align: center;
        }
        .modal-card h3 {
            color: #f87171;
            font-size: 1.15rem;
            margin-bottom: 12px;
        }
        .modal-card p {
            color: var(--color-beige);
            font-size: 0.88rem;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        @media (max-width: 850px) {
            .admin-dashboard { grid-template-columns: 1fr; }
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

    <div class="admin-dashboard">
        
        <!-- RESUMEN IZQUIERDA -->
        <aside class="admin-sidebar" style="text-align: center;">
            <div class="avatar-wrapper">
                <img id="preview-avatar" src="<?php echo $fotoRuta; ?>" class="avatar-img" alt="Foto Perfil">
                <label for="foto_perfil" class="avatar-upload-btn" title="Cambiar Foto">
                    <i class="fa-solid fa-camera"></i>
                </label>
            </div>
            <h3 style="color: var(--color-light); font-size: 1.25rem; margin-bottom: 5px;"><?php echo htmlspecialchars($admin['nombre_completo']); ?></h3>
            <p style="color: var(--color-gold); font-size: 0.85rem; font-weight: 600; margin-bottom: 15px;"><i class="fa-solid fa-shield-halved"></i> Administrador General</p>

            <div style="background: rgba(0,0,0,0.3); padding: 14px; border-radius: 12px; text-align: left; font-size: 0.82rem; color: var(--color-beige);">
                <p style="margin-bottom: 8px;"><strong style="color: var(--color-gold);">Correo:</strong><br><?php echo htmlspecialchars($admin['correo']); ?></p>
                <p style="margin-bottom: 8px;"><strong style="color: var(--color-gold);">Protección 2FA:</strong><br>
                    <span class="badge-2fa <?php echo ($admin['estado_2fa'] == 1) ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo ($admin['estado_2fa'] == 1) ? 'Activada' : 'Desactivada'; ?>
                    </span>
                </p>
            </div>
        </aside>

        <!-- FORMULARIOS DERECHA -->
        <main style="display: flex; flex-direction: column; gap: 20px;">
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert-box alert-<?php echo $tipoMensaje; ?>">
                    <i class="fa-solid <?php echo $tipoMensaje === 'exito' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <!-- PANEL 1: DATOS PERSONALES -->
            <section class="admin-card">
                <h2 style="color: var(--color-gold); font-size: 1.3rem; margin-bottom: 20px;"><i class="fa-solid fa-user-gear"></i> Datos del Administrador</h2>

                <form action="admin_profile.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="accion" value="actualizar_perfil">
                    <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display:none;" onchange="previewImagen(event)">

                    <div class="form-grid-2" style="margin-bottom: 20px;">
                        <!-- NOMBRE COMPLETO -->
                        <div class="form-group-compact">
                            <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">Nombre Completo</label>
                            <input type="text" id="nombre_completo" name="nombre_completo" class="form-control-compact" value="<?php echo htmlspecialchars($admin['nombre_completo']); ?>" required>
                        </div>

                        <!-- CORREO ELECTRÓNICO -->
                        <div class="form-group-compact">
                            <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">Correo Electrónico</label>
                            <input type="email" id="correo" name="correo" class="form-control-compact" value="<?php echo htmlspecialchars($admin['correo']); ?>" required>
                            <p class="helper-text"><i class="fa-solid fa-circle-info"></i> Requiere contraseña actual para modificar.</p>
                        </div>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 20px;">
                        <!-- CONTRASEÑA -->
                        <div class="form-group-compact">
                            <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">Contraseña</label>
                            <div class="input-wrapper-eye">
                                <input type="password" id="password_nueva" name="password_nueva" class="form-control-compact" value="<?php echo htmlspecialchars($admin['password']); ?>" autocomplete="new-password">
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
                                <input type="password" id="password_actual" name="password_actual" class="form-control-compact" placeholder="Confirmar clave actual" value="" required autocomplete="new-password">
                                <button type="button" class="btn-eye-inside" onclick="togglePasswordVisibility('password_actual', this)" title="Mostrar/Ocultar">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="padding: 12px; font-weight: 700;">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Datos de Perfil
                    </button>
                </form>
            </section>

            <!-- PANEL 2: CONFIGURACIÓN 2FA POR PIN -->
            <section class="admin-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="color: var(--color-gold); font-size: 1.15rem; margin: 0;"><i class="fa-solid fa-key"></i> Autenticación de Dos Factores (2FA)</h3>
                    <span class="badge-2fa <?php echo ($admin['estado_2fa'] == 1) ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo ($admin['estado_2fa'] == 1) ? 'Activado' : 'Desactivado'; ?>
                    </span>
                </div>

                <p style="font-size: 0.85rem; color: var(--color-beige); margin-bottom: 20px;">
                    Solicita un PIN numérico adicional de 6 a 10 dígitos al iniciar sesión en el panel tras validar correo y contraseña.
                </p>

                <form action="admin_profile.php" method="POST" autocomplete="off" id="form2FA">
                    <input type="hidden" name="accion" value="activar_2fa">

                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                        <input type="checkbox" id="activar_2fa" name="activar_2fa" value="1" <?php echo ($admin['estado_2fa'] == 1) ? 'checked' : ''; ?> onchange="manejarCheckbox2FA(this)" style="width: 18px; height: 18px; accent-color: var(--color-gold); cursor: pointer;">
                        <label for="activar_2fa" style="color: var(--color-light); font-weight: 600; font-size: 0.9rem; cursor: pointer;">Activar protección por PIN al iniciar sesión</label>
                    </div>

                    <!-- LOS CAMPOS SOLO SON VISIBLES CUANDO LA CASILLA ESTÁ MARCADA -->
                    <div id="2fa-fields-container" class="form-grid-2" style="margin-bottom: 20px; <?php echo ($admin['estado_2fa'] == 1) ? '' : 'display: none;'; ?>">
                        <!-- PIN NUMÉRICO -->
                        <div class="form-group-compact">
                            <label style="color: var(--color-beige); font-weight: 600; font-size: 0.85rem;">PIN Numérico (6 a 10 dígitos)</label>
                            <div class="input-wrapper-eye">
                                <input type="password" name="pin_2fa" id="pin_2fa" minlength="6" maxlength="10" pattern="[0-9]{6,10}" class="form-control-compact" placeholder="Ingresa tu PIN numérico" value="<?php echo htmlspecialchars($admin['pin_2fa'] ?? ''); ?>" autocomplete="new-password">
                                <button type="button" class="btn-eye-inside" onclick="togglePasswordVisibility('pin_2fa', this)" title="Mostrar/Ocultar">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- CONTRASEÑA ACTUAL PARA CONFIRMAR CAMBIOS -->
                        <div class="form-group-compact">
                            <label style="color: var(--color-gold); font-weight: 700; font-size: 0.85rem;">Contraseña Actual</label>
                            <div class="input-wrapper-eye">
                                <input type="password" id="password_actual_2fa" name="password_actual" class="form-control-compact" placeholder="Confirmar clave actual" value="" autocomplete="new-password">
                                <button type="button" class="btn-eye-inside" onclick="togglePasswordVisibility('password_actual_2fa', this)" title="Mostrar/Ocultar">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btn-guardar-2fa" class="btn btn-secondary w-100" style="padding: 12px; font-weight: 700; border-color: var(--color-gold); <?php echo ($admin['estado_2fa'] == 1) ? '' : 'display: none;'; ?>">
                        <i class="fa-solid fa-shield-cat"></i> Actualizar Configuración 2FA
                    </button>
                </form>
            </section>

        </main>

    </div>

    <!-- VENTANA MODAL PARA CONFIRMAR DESACTIVACIÓN DE 2FA -->
    <div id="modalDesactivar2FA" class="modal-overlay">
        <div class="modal-card">
            <i class="fa-solid fa-shield-triangle-exclamation" style="font-size: 2.2rem; color: #f87171; margin-bottom: 10px;"></i>
            <h3>¿Estás seguro que quieres desactivar la autenticación de dos pasos?</h3>
            <p>Ingresa tu contraseña actual para confirmar la desactivación del sistema 2FA.</p>

            <form action="admin_profile.php" method="POST" autocomplete="off">
                <input type="hidden" name="accion" value="desactivar_2fa">

                <div class="form-group-compact" style="text-align: left; margin-bottom: 15px;">
                    <label style="color: var(--color-gold); font-weight: 600; font-size: 0.85rem;">Contraseña Actual</label>
                    <div class="input-wrapper-eye">
                        <input type="password" id="password_actual_modal" name="password_actual" class="form-control-compact" placeholder="Ingresa tu contraseña actual" required autocomplete="new-password">
                        <button type="button" class="btn-eye-inside" onclick="togglePasswordVisibility('password_actual_modal', this)" title="Mostrar/Ocultar">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary w-100" onclick="cerrarModalDesactivar()">Cancelar</button>
                    <button type="submit" class="btn btn-primary w-100" style="background: #d9534f; border-color: #d9534f;">Aceptar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const estado2faInicial = <?php echo ($admin['estado_2fa'] == 1) ? 'true' : 'false'; ?>;

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

        function manejarCheckbox2FA(checkbox) {
            const fieldsContainer = document.getElementById('2fa-fields-container');
            const btnGuardar = document.getElementById('btn-guardar-2fa');

            if (checkbox.checked) {
                // Muestra los campos e inicia el PIN en blanco si es la primera vez
                fieldsContainer.style.display = 'grid';
                btnGuardar.style.display = 'block';
            } else {
                if (estado2faInicial) {
                    // Muestra el modal de confirmación si estaba activo previamente
                    document.getElementById('modalDesactivar2FA').style.display = 'flex';
                } else {
                    // Simplemente oculta los campos si aún no se había guardado la activación
                    fieldsContainer.style.display = 'none';
                    btnGuardar.style.display = 'none';
                }
            }
        }

        function cerrarModalDesactivar() {
            document.getElementById('modalDesactivar2FA').style.display = 'none';
            // Restaura la casilla como marcada al cancelar
            document.getElementById('activar_2fa').checked = true;
            document.getElementById('2fa-fields-container').style.display = 'grid';
            document.getElementById('btn-guardar-2fa').style.display = 'block';
        }
    </script>
</body>
</html>
<?php
session_start();
require_once 'conexion.php';

// Redireccionar al Login si no hay sesión activa
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit;
}

// Acción para cerrar sesión
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ===================================================
// AUTODETECCIÓN DE IP REAL (DESCARTANDO VIRTUALES Y LOOPBACK)
// ===================================================
function obtenerUrlAppMovil() {
    // 1. Si accedes por Ngrok o dominio externo
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        return $scheme . "://" . $_SERVER['HTTP_X_FORWARDED_HOST'] . "/market_express/app_ia.php";
    }
    
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) {
        $scheme = (!empty($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        return $scheme . "://" . $_SERVER['HTTP_HOST'] . "/market_express/app_ia.php";
    }

    // 2. Si estás navegando desde otra PC/móvil usando la IP en la URL
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1' && strpos($_SERVER['HTTP_HOST'], '127.0.') === false) {
        $scheme = (!empty($_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        return $scheme . "://" . $_SERVER['HTTP_HOST'] . "/market_express/app_ia.php";
    }

    // 3. Buscar la IP de red local real (Wi-Fi / Ethernet física)
    $ips = gethostbynamel(gethostname());
    $ipReal = '';

    if ($ips) {
        // Prioridad 1: Buscar IPs Wi-Fi / Router típicas (192.168.x.x o 172.x.x.x)
        foreach ($ips as $ip) {
            // Ignorar todo rango loopback 127.x.x.x y adaptadores de VirtualBox
            if (strpos($ip, '127.') === 0) continue;
            if (strpos($ip, '192.168.56.') === 0) continue; // VirtualBox Host-Only
            if (strpos($ip, '192.168.99.') === 0) continue; // VirtualBox Default
            if (strpos($ip, '10.0.2.') === 0)     continue; // VirtualBox NAT

            // Le damos preferencia a tu IP Wi-Fi/LAN real
            if (strpos($ip, '192.168.') === 0 || strpos($ip, '172.') === 0) {
                $ipReal = $ip;
                break;
            }
        }

        // Prioridad 2: Si no empezó por 192.168, tomar cualquiera que no sea 127.x.x.x
        if (empty($ipReal)) {
            foreach ($ips as $ip) {
                if (strpos($ip, '127.') !== 0 && strpos($ip, '192.168.56.') !== 0) {
                    $ipReal = $ip;
                    break;
                }
            }
        }
    }

    // Si aún así no encuentra nada, usar SERVER_ADDR si no es 127.x.x.x
    if (empty($ipReal) && isset($_SERVER['SERVER_ADDR']) && strpos($_SERVER['SERVER_ADDR'], '127.') !== 0) {
        $ipReal = $_SERVER['SERVER_ADDR'];
    }

    // Si todo falla, usar localhost como último recurso
    if (empty($ipReal)) {
        $ipReal = 'localhost';
    }

    return "http://" . $ipReal . "/market_express/app_ia.php";
}

$urlAppMovil = obtenerUrlAppMovil();

// ===================================================
// GARANTIZAR EXISTENCIA DE LA TABLA DE DETALLES
// ===================================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS detalle_pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        producto_id INT NOT NULL,
        cantidad INT NOT NULL,
        precio_unitario DECIMAL(10,2) NOT NULL
    )");
} catch (Exception $e) {
    // Continuar si la tabla ya existe
}

// ===================================================
// PROCESAMIENTO AJAX DE PEDIDOS (CLIENTE Y ADMIN)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. REGISTRAR PEDIDO REALIZADO POR UN CLIENTE
    if ($_POST['action'] === 'crear_pedido_cliente') {
        $usuarioId = $_SESSION['usuario_id'];
        $total = floatval($_POST['total'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);

        if ($total > 0 && !empty($items)) {
            $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, total, estado, fecha) VALUES (?, ?, 'pendiente', NOW())");
            if ($stmt->execute([$usuarioId, $total])) {
                $pedidoId = $pdo->lastInsertId();
                
                $stmtDet = $pdo->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                foreach ($items as $it) {
                    $pId = intval($it['id'] ?? 0);
                    $cant = intval($it['cantidad'] ?? 1);
                    $prec = floatval($it['precio'] ?? 0);
                    if ($pId > 0) {
                        $stmtDet->execute([$pedidoId, $pId, $cant, $prec]);
                    }
                }

                echo json_encode(['success' => true, 'pedido_id' => $pedidoId, 'mensaje' => 'Pedido enviado a caja con éxito']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'mensaje' => 'Error al procesar el pedido']);
        exit;
    }

    // 2. FINALIZAR PEDIDO POR EL ADMINISTRADOR (WEB O PRESENCIAL)
    if ($_POST['action'] === 'finalizar_pedido_admin') {
        $pedidoId = intval($_POST['pedido_id'] ?? 0);
        $nombreCliente = trim($_POST['nombre_cliente'] ?? 'Cliente Presencial');
        $total = floatval($_POST['total'] ?? 0);
        $itemsPresenciales = json_decode($_POST['items'] ?? '[]', true);
        $adminId = $_SESSION['usuario_id'];

        if ($pedidoId > 0) {
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'completado', fecha = NOW() WHERE id = ?");
            $stmt->execute([$pedidoId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, total, estado, fecha) VALUES (?, ?, 'completado', NOW())");
            $stmt->execute([$adminId, $total]);
            $pedidoId = $pdo->lastInsertId();

            if (!empty($itemsPresenciales)) {
                $stmtDet = $pdo->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                foreach ($itemsPresenciales as $it) {
                    $pId = intval($it['id'] ?? 0);
                    $cant = intval($it['cantidad'] ?? 1);
                    $prec = floatval($it['precio'] ?? 0);
                    if ($pId > 0) {
                        $stmtDet->execute([$pedidoId, $pId, $cant, $prec]);
                    }
                }
            }
        }

        $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
            ->execute([$adminId, "Finalizó venta #$pedidoId de $nombreCliente ($$total)"]);

        echo json_encode(['success' => true, 'pedido_id' => $pedidoId, 'fecha_finalizado' => date('d/m/Y h:i A')]);
        exit;
    }

    // 3. RECEPCIÓN DE CÓDIGO
    if ($_POST['action'] === 'escaneo_ia_local') {
        $codigoBuscado = trim($_POST['codigo'] ?? '');
        if (!empty($codigoBuscado)) {
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE codigo = ? OR id = ? LIMIT 1");
            $stmt->execute([$codigoBuscado, $codigoBuscado]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prod) {
                echo json_encode(['success' => true, 'producto' => $prod]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'mensaje' => 'Producto no encontrado']);
        exit;
    }
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario = $_SESSION['rol'];
$pageTitle = "Market Express - Panel Principal";
$copyrightText = "© 2026 Market express - todos los derechos son reservados a estudiantes de 2B del Instituto Nacional de Apopa (INA)";

// ===================================================
// OBTENER DATOS DE LA BASE DE DATOS
// ===================================================
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$productosDB = $pdo->query("SELECT p.*, c.nombre AS categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.nombre ASC")->fetchAll();

$productos = [];
foreach ($productosDB as $prod) {
    $rutaImagen = 'uploads/' . $prod['imagen'];
    if (!file_exists($rutaImagen) || empty($prod['imagen'])) {
        $rutaImagen = 'assets/' . $prod['imagen'];
    }
    $prod['imagen_ruta'] = $rutaImagen;
    $prod['codigo'] = !empty($prod['codigo']) ? $prod['codigo'] : str_pad($prod['id'] + 100000, 6, '0', STR_PAD_LEFT);
    $productos[] = $prod;
}

$pedidosPendientesRaw = $pdo->query("SELECT p.*, u.nombre_completo FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id WHERE p.estado = 'pendiente' ORDER BY p.fecha ASC")->fetchAll();

$pedidosPendientes = [];
foreach ($pedidosPendientesRaw as $ped) {
    try {
        $stmtDet = $pdo->prepare("SELECT dp.*, pr.nombre FROM detalle_pedidos dp JOIN productos pr ON dp.producto_id = pr.id WHERE dp.pedido_id = ?");
        $stmtDet->execute([$ped['id']]);
        $ped['items'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $ped['items'] = [];
    }
    $pedidosPendientes[] = $ped;
}

$historialAdmin = $pdo->query("SELECT h.*, a.nombre_completo FROM historial_admin h JOIN administradores a ON h.admin_id = a.id ORDER BY h.fecha DESC LIMIT 15")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        body.dashboard-body {
            min-height: 100vh;
            background-color: #1a1f30;
            color: var(--color-light, #ffffff);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Poppins', sans-serif;
        }

        input, select, textarea, .form-control-compact {
            box-sizing: border-box !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }

        /* BANNER MAS GRUESO, VISTOSO Y CENTRADO */
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 22px;
            background: linear-gradient(135deg, rgba(20, 25, 40, 0.98) 0%, rgba(32, 40, 62, 0.98) 100%);
            border-bottom: 2px solid rgba(173, 147, 103, 0.4);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .header-brand-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-title-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex: 1;
        }

        .header-title {
            font-size: 1.25rem;
            margin: 0;
            color: var(--color-light, #ffffff);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }

        .header-title span { color: var(--color-gold, #decab0); }

        .header-subtitle {
            font-size: 0.75rem;
            color: var(--color-gold, #decab0);
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            margin-top: 3px;
        }

        .header-user-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout-box {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border: 1px solid rgba(173, 147, 103, 0.35);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-logout-box:hover {
            background: rgba(222, 202, 176, 0.2);
            border-color: var(--color-gold, #decab0);
            color: var(--color-gold);
        }

        .welcome-banner {
            background: linear-gradient(135deg, rgba(35, 42, 63, 0.95) 0%, rgba(20, 25, 40, 0.95) 100%);
            border: 1px solid rgba(173, 147, 103, 0.35);
            border-radius: var(--radius-md, 12px);
            padding: 16px;
            margin-bottom: 18px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .welcome-banner h2 {
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-banner h2 i {
            -webkit-text-fill-color: initial;
            color: var(--color-gold, #decab0);
        }

        @media (min-width: 701px) {
            .welcome-banner { padding: 28px 24px !important; }
            .welcome-banner h2 { font-size: 1.45rem; }
        }

        .categories-container-box {
            background: rgba(35, 42, 63, 0.6);
            border: 1px solid rgba(173, 147, 103, 0.25);
            border-radius: var(--radius-md, 12px);
            padding: 12px 14px;
            margin-top: 15px;
        }

        .categories-title-label {
            color: var(--color-gold, #decab0);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: block;
        }

        .categories-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-height: 130px;
            overflow-y: auto;
        }

        .category-pill.active {
            background: var(--color-gold, #decab0);
            color: var(--color-dark, #1a1f30);
            border-color: var(--color-gold, #decab0);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 18px;
            align-items: start;
            align-content: start;
        }

        .product-card {
            background: rgba(35, 42, 63, 0.8);
            border: 1px solid rgba(173, 147, 103, 0.25);
            border-radius: var(--radius-md, 12px);
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: fit-content;
            box-sizing: border-box;
            cursor: pointer;
        }

        .product-card-img-wrapper {
            position: relative;
            width: 100%;
            height: 125px;
            margin-bottom: 8px;
        }

        .product-card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: var(--radius-sm, 8px);
        }

        .btn-quick-add {
            width: 100%;
            background: var(--color-gold, #decab0);
            color: var(--color-dark, #1a1f30);
            border: none;
            border-radius: var(--radius-sm, 8px);
            padding: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            margin-top: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        @keyframes animBotonAgregar {
            0% { transform: scale(1); }
            50% { transform: scale(0.91); background-color: #ffffff !important; color: #1a1f30 !important; }
            100% { transform: scale(1); }
        }

        .btn-added-pulse {
            animation: animBotonAgregar 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background-color: #ffffff !important;
            color: #1a1f30 !important;
        }

        .cart-items-list {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 14px !important;
            margin-bottom: 12px;
        }

        .cart-items-list::-webkit-scrollbar,
        #order-items-container::-webkit-scrollbar,
        .historial-scroll-container::-webkit-scrollbar {
            width: 5px;
        }

        .cart-items-list::-webkit-scrollbar-thumb,
        #order-items-container::-webkit-scrollbar-thumb,
        .historial-scroll-container::-webkit-scrollbar-thumb {
            background: rgba(173, 147, 103, 0.45);
            border-radius: 4px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            padding-right: 4px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .mobile-fab-cart {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 58px;
            height: 58px;
            background: var(--color-gold, #decab0);
            color: #1a1f30;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.45);
            z-index: 150;
            cursor: pointer;
            border: none;
        }

        .fab-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #e53e3e;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 800;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #181e2e;
        }

        /* DASHBOARD ADMINISTRADOR AJUSTADO */
        .admin-dashboard-grid {
            width: 100%;
            max-width: 1180px;
            box-sizing: border-box;
            margin: 16px auto;
            padding: 0 10px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            grid-template-areas:
                "escaner cola"
                "historial historial";
            gap: 16px;
            align-items: stretch;
        }

        .mod-escaner {
            grid-area: escaner;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .mod-cola {
            grid-area: cola;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .mod-historial { grid-area: historial; min-width: 0; }

        .admin-module-card {
            box-sizing: border-box;
            min-width: 0;
            background: rgba(30, 38, 56, 0.9);
            border: 1px solid rgba(173, 147, 103, 0.35);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .admin-module-title {
            color: var(--color-gold, #decab0);
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid rgba(173, 147, 103, 0.25);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-card-desc {
            font-size: 0.72rem;
            color: var(--color-beige, #a0aec0);
            margin-bottom: 10px;
            line-height: 1.3;
        }

        /* ESCÁNER AMPLIO Y APROVECHADO */
        .scanner-body-box {
            display: flex;
            flex-direction: column;
            flex: 1;
            justify-content: space-between;
            gap: 10px;
        }

        #qr-reader-container {
            width: 100%;
            margin: 6px 0;
            border-radius: 10px;
            overflow: hidden;
            display: none;
            border: 2px solid var(--color-gold);
            background: #0d111d;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        .scanner-placeholder-info {
            background: rgba(17, 22, 36, 0.6);
            border: 1px dashed rgba(173, 147, 103, 0.3);
            border-radius: 10px;
            padding: 16px 12px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 4px 0;
        }

        #order-items-container {
            max-height: 200px;
            min-height: 130px;
            overflow-y: auto;
            background: rgba(17,21,33,0.7);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 0.78rem;
            border: 1px solid rgba(173, 147, 103, 0.2);
        }

        /* HISTORIAL DE ACCIONES VISTOSO */
        .historial-scroll-container {
            max-height: 220px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .historial-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
            font-size: 0.74rem;
        }

        .historial-table th {
            color: var(--color-gold);
            font-weight: 700;
            padding: 6px 10px;
            text-transform: uppercase;
            font-size: 0.68rem;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(173, 147, 103, 0.2);
        }

        .historial-row {
            background: rgba(22, 28, 44, 0.8);
            border-radius: 8px;
            transition: transform 0.15s ease, background 0.15s ease;
        }

        .historial-row:hover {
            background: rgba(35, 45, 70, 0.9);
            transform: translateX(2px);
        }

        .historial-row td {
            padding: 8px 10px;
            color: var(--color-light);
        }

        .historial-row td:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        .historial-row td:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .badge-admin-tag {
            background: rgba(222, 202, 176, 0.15);
            color: var(--color-gold);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(222, 202, 176, 0.3);
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(10, 14, 23, 0.85);
            backdrop-filter: blur(6px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-body-card {
            background: #1f2638;
            border: 1px solid rgba(173, 147, 103, 0.4);
            border-radius: 16px;
            padding: 20px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
        }

        @media (max-width: 700px) {
            .app-header { padding: 12px 14px; }
            .header-title { font-size: 1.05rem; }
            .header-subtitle { font-size: 0.68rem; }
            .admin-dashboard-grid {
                grid-template-columns: minmax(0, 1fr);
                grid-template-areas:
                    "escaner"
                    "cola"
                    "historial";
                gap: 12px;
                padding: 0 4px;
            }
            .admin-module-card { padding: 12px; border-radius: 12px; }
            .admin-module-title { font-size: 0.82rem; }
            .admin-card-desc { font-size: 0.68rem; }
            #order-items-container { max-height: 150px; min-height: 100px; }

            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }

            .product-card {
                padding: 10px !important;
                height: fit-content !important;
            }

            .product-card-img-wrapper { height: 100px !important; }
            .mobile-fab-cart { display: flex !important; }
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- BARRA SUPERIOR RESPONSIVA Y VISTOSA -->
    <header class="app-header">
        <div class="header-brand-box">
            <button class="hamburger-btn" id="open-sidebar" title="Abrir Menú" style="background: none; border: none; color: #decab0; font-size: 1.25rem; cursor: pointer; padding: 0 4px;">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <div class="brand-title-center">
            <h1 class="header-title">Market <span>Express</span></h1>
            <?php if ($rolUsuario === 'admin'): ?>
                <span class="header-subtitle"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($nombreUsuario); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="header-user-info">
            <a href="market_express.php?action=logout" class="btn-logout-box" title="Cerrar Sesión">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- MENÚ LATERAL (DRAWER) -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <aside class="sidebar-drawer" id="sidebar-drawer">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <h3 style="color: var(--color-gold); font-size: 1.05rem; margin: 0;"><i class="fa-solid fa-compass"></i> Menú Navegación</h3>
            <button id="close-sidebar" style="background: none; border: none; color: var(--color-light); font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <ul class="sidebar-menu">
            <?php if ($rolUsuario === 'cliente'): ?>
                <li><a href="profile.php"><i class="fa-solid fa-id-card"></i> Mi Perfil</a></li>
                <li><a href="pedidos.php"><i class="fa-solid fa-receipt"></i> Mis Pedidos</a></li>
            <?php else: ?>
                <li><a href="admin_profile.php"><i class="fa-solid fa-user-gear"></i> Perfil Administrador</a></li>
                <li><a href="inventario.php"><i class="fa-solid fa-boxes-stacked"></i> Inventario de Productos</a></li>
                <li><a href="listado.php"><i class="fa-solid fa-list-check"></i> Listado de Ventas</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <!-- VISTA CLIENTE / CATÁLOGO -->
    <?php if ($rolUsuario === 'cliente'): ?>
    <main class="main-app-layout">
        <section>
            <div class="welcome-banner">
                <h2><i class="fa-solid fa-basket-shopping"></i> Bienvenido, <?php echo htmlspecialchars($nombreUsuario); ?></h2>
                <p style="margin:0; color:#a0aec0; font-size:0.85rem;">Explora nuestras categorías y agrega tus productos al carrito.</p>
            </div>

            <div class="search-filter-section">
                <div class="search-box-container">
                    <div class="input-wrapper">
                        <i class="fa-solid fa-magnifying-glass input-icon"></i>
                        <input type="text" id="product-search" class="form-control-compact" placeholder="Buscar producto..." onkeyup="filtrarProductosDirecto()">
                    </div>
                </div>

                <div class="categories-container-box">
                    <span class="categories-title-label"><i class="fa-solid fa-tags"></i> Categorías Disponibles</span>
                    <div class="categories-pills">
                        <button class="category-pill active" onclick="filtrarCategoria('todas', this, 'Todas las Categorías')">
                            <i class="fa-solid fa-border-all"></i> Todas
                        </button>
                        <?php foreach ($categorias as $cat): ?>
                            <button class="category-pill" onclick="filtrarCategoria('<?php echo $cat['id']; ?>', this, '<?php echo htmlspecialchars(addslashes($cat['nombre'])); ?>')">
                                <i class="fa-solid <?php echo !empty($cat['icono']) ? $cat['icono'] : 'fa-tag'; ?>"></i> <?php echo htmlspecialchars($cat['nombre']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 18px; margin-bottom: 10px;">
                <h3 id="selected-category-title" style="color: var(--color-gold, #decab0); font-size: 1rem; margin: 0;">
                    <i class="fa-solid fa-boxes-stacked"></i> Catálogo Completo
                </h3>
            </div>

            <div class="products-grid" id="products-grid">
                <?php foreach ($productos as $prod): ?>
                    <div class="product-card" data-category="<?php echo $prod['categoria_id']; ?>" data-name="<?php echo strtolower(htmlspecialchars($prod['nombre'])); ?>" onclick="abrirModalProductoPorId(<?php echo $prod['id']; ?>)">
                        <div>
                            <div class="product-card-img-wrapper">
                                <img src="<?php echo htmlspecialchars($prod['imagen_ruta']); ?>" onerror="this.onerror=null; this.src='https://via.placeholder.com/200x130/29314a/decab0?text=Producto';" alt="<?php echo htmlspecialchars($prod['nombre']); ?>">
                            </div>
                            <h4 style="font-size: 0.88rem; color: var(--color-light); margin-bottom: 4px; line-height: 1.2; height: 32px; overflow: hidden;"><?php echo htmlspecialchars($prod['nombre']); ?></h4>
                            <p style="font-size: 1.05rem; font-weight: 800; color: var(--color-gold, #decab0); margin-bottom: 6px;">$<?php echo number_format($prod['precio'], 2); ?></p>
                        </div>
                        <div>
                            <?php if ($prod['stock'] > 0): ?>
                                <button type="button" class="btn-quick-add" onclick="agregarAlCarritoPorId(<?php echo $prod['id']; ?>, event, this)">
                                    <i class="fa-solid fa-cart-plus"></i> Agregar
                                </button>
                            <?php else: ?>
                                <span class="stock-badge stock-out" style="display: block; text-align: center; font-size: 0.75rem;"><i class="fa-solid fa-xmark"></i> Agotado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- PANEL DE CARRITO CLIENTE -->
        <aside class="cart-panel" id="cart-panel-section">
            <h3 style="color: var(--color-light); font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-cart-shopping" style="color: var(--color-gold, #decab0);"></i> Mi Carrito
            </h3>
            
            <div class="cart-items-list" id="cart-items-list">
                <p style="text-align: center; color: var(--color-beige); opacity: 0.6; font-size: 0.85rem; margin: 20px 0;">Tu carrito está vacío.</p>
            </div>

            <div class="cart-summary">
                <div class="cart-summary-line"><span>Subtotal:</span> <span id="cart-subtotal">$0.00</span></div>
                <div class="cart-summary-line"><span>Descuento:</span> <span id="cart-discount">-$0.00</span></div>
                <div class="cart-summary-line"><span>IVA (13%):</span> <span id="cart-tax">$0.00</span></div>
                <div class="cart-summary-line cart-summary-total"><span>Total:</span> <span id="cart-total">$0.00</span></div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button class="btn btn-secondary btn-compact" style="flex: 1;" onclick="vaciarCarrito()">Cancelar</button>
                <button class="btn btn-primary btn-compact" style="flex: 2;" onclick="procesarPedidoCliente()">Finalizar Pedido</button>
            </div>
        </aside>
    </main>

    <!-- FAB CARRITO MÓVIL -->
    <button class="mobile-fab-cart" onclick="abrirCarritoMovil()" title="Ver Carrito">
        <i class="fa-solid fa-cart-shopping"></i>
        <span id="fab-cart-count" class="fab-badge">0</span>
    </button>
    <?php endif; ?>

    <!-- VISTA ADMINISTRADOR -->
    <?php if ($rolUsuario === 'admin'): ?>
    <main class="admin-dashboard-grid">
        
        <!-- MÓDULO ESCÁNER CÓDIGO / QR AMPLIO Y OPTIMIZADO -->
        <div class="admin-module-card mod-escaner">
            <div>
                <div class="admin-module-title" style="justify-content: space-between;">
                    <span><i class="fa-solid fa-qrcode"></i> Escáner Código / QR</span>
                    <button type="button" onclick="mostrarQREnlaceMovil()" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #fff; border: none; padding: 5px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px;" title="Abrir QR para conectar celular">
                        <i class="fa-solid fa-mobile-screen"></i> Vincular Celular
                    </button>
                </div>
                <p class="admin-card-desc">Escanea productos con la cámara de tu equipo o ingresa el código manual.</p>
            </div>

            <div class="scanner-body-box">
                <div id="qr-reader-container">
                    <div id="qr-reader" style="width:100%;"></div>
                </div>

                <div id="scanner-placeholder" class="scanner-placeholder-info">
                    <i class="fa-solid fa-barcode" style="font-size: 2.2rem; color: var(--color-gold); opacity: 0.8; margin-bottom: 8px;"></i>
                    <span style="font-size: 0.78rem; color: #a0aec0; font-weight: 600;">Lector de códigos listo</span>
                    <span style="font-size: 0.68rem; color: #718096; margin-top: 2px;">Ingresa un código abajo o activa la cámara web.</span>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 6px;">
                    <input type="text" id="manual-barcode-input" class="form-control-compact" style="width: 100%; min-width: 0; padding: 8px 10px; font-size: 0.8rem; flex: 1; border-radius: 8px;" placeholder="Código o ID de Producto..." onkeypress="if(event.key==='Enter') agregarPorCodigoManual()">
                    
                    <button type="button" class="btn btn-primary btn-compact" onclick="agregarPorCodigoManual()" style="padding: 8px 12px; font-size: 0.8rem; flex-shrink: 0; background: var(--color-gold); color: #1a1f30; border: none; font-weight: bold; border-radius: 8px; cursor: pointer;" title="Agregar a la cola">
                        <i class="fa-solid fa-plus"></i>
                    </button>

                    <button type="button" id="btn-toggle-cam" onclick="toggleCamaraScanner()" style="padding: 8px 12px; font-size: 0.8rem; flex-shrink: 0; background: #2563eb; color: #ffffff; border: none; font-weight: bold; border-radius: 8px; cursor: pointer;" title="Activar cámara web">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- MÓDULO COLA DE PEDIDOS -->
        <div class="admin-module-card mod-cola">
            <div>
                <div class="admin-module-title">
                    <i class="fa-solid fa-cash-register"></i> Cola de Pedidos
                </div>

                <div id="active-order-box">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 0.74rem; color: var(--color-gold); font-weight: 700;">
                            <i class="fa-solid fa-receipt"></i> Venta / Pedido Activo
                        </span>
                        <button onclick="limpiarPedidoPresencial()" style="background: none; border: none; color: #ff8b88; font-size: 0.7rem; cursor: pointer; display: flex; align-items: center; gap: 3px;">
                            <i class="fa-solid fa-trash-can"></i> Limpiar
                        </button>
                    </div>

                    <div id="order-items-container">
                        <p id="empty-queue-msg" style="color: var(--color-beige); opacity: 0.6; text-align: center; margin: 16px 0;">Escanea productos o espera pedidos web.</p>
                    </div>

                    <div style="background: rgba(0,0,0,0.35); padding: 10px 12px; border-radius: 10px; margin-bottom: 8px; border: 1px solid rgba(173,147,103,0.2);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 0.76rem; color: var(--color-beige);">Total Pagar:</span>
                            <strong id="active-order-total-display" style="font-size: 1.15rem; color: var(--color-gold);">$0.00</strong>
                        </div>

                        <div style="margin-bottom: 6px;">
                            <input type="number" step="0.01" id="cash-received" class="form-control-compact" style="width: 100%; min-width: 0; padding: 7px 10px; font-size: 0.8rem; border-radius: 6px;" placeholder="Efectivo recibido..." onkeyup="calcularCambioDirecto()" oninput="calcularCambioDirecto()">
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.76rem; color: var(--color-beige);">Cambio:</span>
                            <strong id="cash-change" style="font-size: 1rem; color: #81d181;">$0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary btn-compact w-100" style="padding: 10px; font-size: 0.82rem; font-weight: 700; background: var(--color-gold); color: #1a1f30; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="finalizarPedidoEnCaja()">
                <i class="fa-solid fa-print"></i> Finalizar Venta / PDF
            </button>
        </div>

        <!-- MÓDULO HISTORIAL VISTOSO Y ELEGANTE -->
        <div class="admin-module-card mod-historial">
            <div class="admin-module-title">
                <i class="fa-solid fa-clock-rotate-left"></i> Historial de Acciones y Auditoría
            </div>
            
            <div class="historial-scroll-container">
                <table class="historial-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Administrador</th>
                            <th style="width: 50%;">Acción Realizada</th>
                            <th style="width: 25%; text-align: right;">Fecha / Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historialAdmin) > 0): ?>
                            <?php foreach ($historialAdmin as $h): ?>
                                <tr class="historial-row">
                                    <td>
                                        <span class="badge-admin-tag">
                                            <i class="fa-solid fa-user-gear"></i> <?php echo htmlspecialchars($h['nombre_completo']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.74rem;">
                                        <?php if (strpos($h['accion'], 'Finalizó') !== false): ?>
                                            <i class="fa-solid fa-circle-check" style="color: #4ade80; margin-right: 4px;"></i>
                                        <?php elseif (strpos($h['accion'], 'Editó') !== false): ?>
                                            <i class="fa-solid fa-pen-to-square" style="color: #60a5fa; margin-right: 4px;"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-shield-halved" style="color: #decab0; margin-right: 4px;"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($h['accion']); ?>
                                    </td>
                                    <td style="text-align: right; color: #a0aec0; font-size: 0.68rem; font-weight: 600; white-space: nowrap;">
                                        <i class="fa-regular fa-clock" style="margin-right: 2px;"></i> <?php echo date('d/m/Y h:i A', strtotime($h['fecha'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="padding: 12px; text-align: center; color: var(--color-beige);">Sin registros de auditoría almacenados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
    <?php endif; ?>

    <!-- MODAL VINCULAR TELÉFONO -->
    <div id="modalVincularMovil" class="modal-overlay">
        <div class="modal-body-card" style="max-width: 340px; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 6px;">
                <h3 style="color: var(--color-gold); margin: 0; font-size: 0.95rem;"><i class="fa-solid fa-wifi"></i> Vincular Teléfono Móvil</h3>
                <button onclick="document.getElementById('modalVincularMovil').style.display='none'" style="background:none; border:none; color:#fff; font-size:1.1rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p style="font-size: 0.75rem; color: #a0aec0; margin-bottom: 10px;">Escanea este código QR con la cámara de tu celular para abrir la App Móvil:</p>
            
            <div style="background: #ffffff; padding: 12px; border-radius: 12px; display: inline-block; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.4);">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($urlAppMovil); ?>" alt="QR Enlace Móvil" style="width: 170px; height: 170px; display: block;">
            </div>

            <div style="font-size: 0.72rem; color: var(--color-gold); font-family: monospace; word-break: break-all; background: rgba(0,0,0,0.3); padding: 6px; border-radius: 6px; border: 1px solid rgba(173,147,103,0.2);">
                <?php echo $urlAppMovil; ?>
            </div>
        </div>
    </div>

    <!-- MODAL INGRESO NOMBRE CLIENTE (PRESENCIAL) -->
    <div id="modalNombreCliente" class="modal-overlay">
        <div class="modal-body-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 6px;">
                <h3 style="color: var(--color-gold); margin: 0; font-size: 0.95rem;"><i class="fa-solid fa-user-plus"></i> Iniciar Pedido Presencial</h3>
                <button onclick="cerrarModalCliente()" style="background:none; border:none; color:#fff; font-size:1.1rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p style="font-size: 0.78rem; color: var(--color-beige); margin-bottom: 10px;">Ingresa el nombre del cliente para iniciar el encabezado del pedido:</p>
            <input type="text" id="input-nombre-cliente-presencial" class="form-control-compact" style="width: 100%; padding: 8px; font-size: 0.8rem; border-radius: 6px; margin-bottom: 12px;" placeholder="Ej: Juan Pérez" onkeypress="if(event.key==='Enter') confirmarNombreCliente()">
            <button onclick="confirmarNombreCliente()" style="width: 100%; background: var(--color-gold); color: #1a1f30; border: none; border-radius: 6px; padding: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                <i class="fa-solid fa-check"></i> Comenzar Pedido
            </button>
        </div>
    </div>

    <!-- MODAL DETALLE DE PRODUCTO -->
    <div class="modal-overlay" id="product-modal">
        <div class="modal-body-card" style="position: relative; text-align: center;">
            <button onclick="cerrarModalProducto()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; color: var(--color-light); font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            <img id="modal-img" src="" style="width: 100%; height: 140px; object-fit: contain; border-radius: 12px; margin-bottom: 10px;">
            <h3 id="modal-title" style="color: var(--color-light); font-size: 1.1rem; margin-bottom: 4px;"></h3>
            <p id="modal-desc" style="color: var(--color-beige); font-size: 0.8rem; margin: 6px 0;"></p>
            <h2 id="modal-price" style="color: var(--color-gold); font-size: 1.2rem; margin-bottom: 10px;"></h2>
            <div id="modal-stock-badge" style="margin-bottom: 15px;"></div>
            <button id="modal-add-btn" class="btn btn-primary w-100" style="background: var(--color-gold); color: #1a1f30; font-weight: 700; border: none; padding: 8px; border-radius: 8px;"><i class="fa-solid fa-cart-plus"></i> Añadir al Carrito</button>
        </div>
    </div>

    <!-- PIE DE PÁGINA -->
    <footer style="position: relative; margin-top: 30px; padding-bottom: 16px; text-align: center; font-size: 0.68rem; color: #a0aec0;">
        <?php echo $copyrightText; ?>
    </footer>

    <!-- SCRIPTS INTERACTIVOS -->
    <script>
        function id(e) { return document.getElementById(e); }

        const listaProductosBD = <?php echo json_encode($productos); ?>;
        const listaPedidosPendientesBD = <?php echo json_encode($pedidosPendientes); ?>;

        let pedidosCola = [...listaPedidosPendientesBD];
        let itemsPedidoPresencial = [];
        let nombreClienteActivo = '';
        let pedidoWebIdActivo = 0;
        let productoPendientePorAgregar = null;
        let html5QrcodeScanner = null;

        document.addEventListener('DOMContentLoaded', () => {
            cargarSiguientePedidoEnCola();
        });

        function mostrarQREnlaceMovil() {
            id('modalVincularMovil').style.display = 'flex';
        }

        window.procesarCodigoEscaneado = function(codigoVal) {
            if (!codigoVal) return false;
            codigoVal = codigoVal.toString().trim().toLowerCase();

            const encontrado = listaProductosBD.find(p => 
                (p.codigo && p.codigo.toLowerCase() === codigoVal) ||
                p.id.toString() === codigoVal || 
                p.nombre.toLowerCase() === codigoVal
            );

            if (encontrado) {
                verificarYAgregarProducto(encontrado);
                reproducirBeep();
                return true;
            } else {
                const formData = new FormData();
                formData.append('action', 'escaneo_ia_local');
                formData.append('codigo', codigoVal);

                fetch('market_express.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success && res.producto) {
                            verificarYAgregarProducto(res.producto);
                            reproducirBeep();
                        } else {
                            alert('Producto no encontrado para el código: ' + codigoVal);
                        }
                    }).catch(() => {
                        alert('Código no encontrado: ' + codigoVal);
                    });
            }
            return false;
        };

        function reproducirBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                osc.type = 'sine';
                osc.frequency.value = 1200;
                osc.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch(e) {}
        }

        function toggleCamaraScanner() {
            const container = id('qr-reader-container');
            const placeholder = id('scanner-placeholder');

            if (container.style.display === 'block') {
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear();
                }
                container.style.display = 'none';
                if (placeholder) placeholder.style.display = 'flex';
            } else {
                if (placeholder) placeholder.style.display = 'none';
                container.style.display = 'block';
                html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: { width: 220, height: 220 } });
                html5QrcodeScanner.render((decodedText) => {
                    window.procesarCodigoEscaneado(decodedText);
                }, (error) => {});
            }
        }

        function cargarSiguientePedidoEnCola() {
            const container = id('order-items-container');
            if (!container) return;

            if (pedidosCola.length > 0) {
                const sig = pedidosCola[0];
                pedidoWebIdActivo = sig.id;
                nombreClienteActivo = sig.nombre_completo;
                itemsPedidoPresencial = [];
                
                let htmlItems = '';
                if (sig.items && sig.items.length > 0) {
                    sig.items.forEach(it => {
                        const cant = parseInt(it.cantidad || 1);
                        const prec = parseFloat(it.precio_unitario || it.precio || 0);
                        const sub = cant * prec;
                        htmlItems += `
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 4px 0;">
                            <span style="color: var(--color-light); font-weight: 600;">${cant}x ${it.nombre}</span>
                            <strong style="color: var(--color-gold);">$${sub.toFixed(2)}</strong>
                        </div>`;
                    });
                } else {
                    htmlItems = `<p style="color: var(--color-beige); opacity: 0.7; font-size: 0.72rem; margin: 4px 0; text-align: center;">Sin ítems desglosados registrados.</p>`;
                }

                container.innerHTML = `
                <div style="padding: 6px; background: rgba(222,202,176,0.1); border-radius: 6px; margin-bottom: 8px; border-left: 3px solid var(--color-gold);">
                    <p style="margin: 0 0 2px 0; color: #fff; font-weight: 700; font-size: 0.8rem;">Pedido Web #${sig.id}</p>
                    <p style="margin: 0; color: var(--color-gold); font-size: 0.72rem;">Cliente: <strong>${sig.nombre_completo}</strong></p>
                </div>
                <div>${htmlItems}</div>`;
                
                id('active-order-total-display').innerText = '$' + parseFloat(sig.total).toFixed(2);
                calcularCambioDirecto();
            } else {
                pedidoWebIdActivo = 0;
                if (itemsPedidoPresencial.length === 0) {
                    nombreClienteActivo = 'Presencial';
                }
                renderizarColaPresencial();
            }
        }

        function agregarPorCodigoManual() {
            const input = id('manual-barcode-input');
            const codigoVal = input.value.trim();
            if (!codigoVal) return;

            window.procesarCodigoEscaneado(codigoVal);
            input.value = '';
        }

        // ===================================================
        // LÓGICA DE DETECCIÓN Y SOLICITUD DE NOMBRE DE CLIENTE
        // ===================================================
        function verificarYAgregarProducto(prod) {
            // Si es un pedido presencial (no web)
            if (pedidoWebIdActivo === 0) {
                // SOLO pedir nombre si la lista presencial está VACÍA o si no tiene un nombre asignado
                if (itemsPedidoPresencial.length === 0 || !nombreClienteActivo || nombreClienteActivo === 'Presencial') {
                    productoPendientePorAgregar = prod;
                    id('input-nombre-cliente-presencial').value = '';
                    id('modalNombreCliente').style.display = 'flex';
                    setTimeout(() => id('input-nombre-cliente-presencial').focus(), 100);
                    return;
                }
            }
            enviarAColaDePedidos(prod);
        }

        function confirmarNombreCliente() {
            const val = id('input-nombre-cliente-presencial').value.trim();
            if (!val) {
                alert('Ingresa el nombre del cliente.');
                return;
            }
            nombreClienteActivo = val;
            cerrarModalCliente();

            if (productoPendientePorAgregar) {
                enviarAColaDePedidos(productoPendientePorAgregar);
                productoPendientePorAgregar = null;
            }
        }

        function cerrarModalCliente() {
            id('modalNombreCliente').style.display = 'none';
        }

        function enviarAColaDePedidos(prod) {
            const existe = itemsPedidoPresencial.find(item => item.id === prod.id);
            if (existe) {
                existe.cantidad++;
            } else {
                itemsPedidoPresencial.push({
                    id: prod.id,
                    nombre: prod.nombre,
                    precio: parseFloat(prod.precio),
                    cantidad: 1
                });
            }
            renderizarColaPresencial();
        }

        function renderizarColaPresencial() {
            const container = id('order-items-container');
            const displayTotal = id('active-order-total-display');
            if (!container) return;

            if (itemsPedidoPresencial.length === 0) {
                container.innerHTML = '<p id="empty-queue-msg" style="color: var(--color-beige); opacity: 0.6; text-align: center; margin: 16px 0;">Escanea productos para iniciar pedido presencial.</p>';
                displayTotal.innerText = '$0.00';
                calcularCambioDirecto();
                return;
            }

            let html = '';
            let acumulado = 0;

            if (nombreClienteActivo && nombreClienteActivo !== 'Presencial') {
                html += `<div style="padding: 4px 8px; background: rgba(222,202,176,0.12); border-radius: 6px; margin-bottom: 6px; color: var(--color-gold); font-size: 0.74rem; font-weight: bold; border-left: 3px solid var(--color-gold);"><i class="fa-solid fa-user"></i> Cliente: ${nombreClienteActivo}</div>`;
            }

            itemsPedidoPresencial.forEach((item, index) => {
                const sub = item.precio * item.cantidad;
                acumulado += sub;
                html += `
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 4px 0;">
                    <span style="color: var(--color-light); font-weight: 600;">${item.cantidad}x ${item.nombre}</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <strong style="color: var(--color-gold);">$${sub.toFixed(2)}</strong>
                        <button onclick="removerItemPresencial(${index})" style="background:none; border:none; color:#ff8b88; cursor:pointer;" title="Eliminar ítem"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>`;
            });

            container.innerHTML = html;
            displayTotal.innerText = '$' + acumulado.toFixed(2);
            calcularCambioDirecto();
        }

        function removerItemPresencial(index) {
            itemsPedidoPresencial.splice(index, 1);
            if (itemsPedidoPresencial.length === 0) {
                nombreClienteActivo = 'Presencial';
            }
            renderizarColaPresencial();
        }

        function limpiarPedidoPresencial() {
            itemsPedidoPresencial = [];
            nombreClienteActivo = 'Presencial';
            renderizarColaPresencial();

            const inputEfectivo = id('cash-received');
            if (inputEfectivo) inputEfectivo.value = '';
            id('cash-change').innerText = '$0.00';

            const formData = new FormData();
            formData.append('action', 'registrar_efectivo');
            formData.append('monto', 0);
            fetch('api_ia.php', { method: 'POST', body: formData }).catch(err => {});
        }

        function calcularCambioDirecto() {
            const inputEfectivo = id('cash-received');
            if (!inputEfectivo) return;

            let totalActual = 0;
            if (pedidoWebIdActivo > 0 && pedidosCola.length > 0) {
                totalActual = parseFloat(pedidosCola[0].total);
            } else {
                itemsPedidoPresencial.forEach(i => totalActual += (i.precio * i.cantidad));
            }

            const rec = parseFloat(inputEfectivo.value) || 0;
            const cambio = rec - totalActual;
            id('cash-change').innerText = '$' + (cambio > 0 ? cambio.toFixed(2) : '0.00');
        }

        function finalizarPedidoEnCaja() {
            let totalVenta = 0;
            if (pedidoWebIdActivo > 0 && pedidosCola.length > 0) {
                totalVenta = parseFloat(pedidosCola[0].total);
            } else {
                itemsPedidoPresencial.forEach(i => totalVenta += (i.precio * i.cantidad));
            }

            if (totalVenta <= 0) {
                alert('No hay productos ni pedidos activos en la cola.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'finalizar_pedido_admin');
            formData.append('pedido_id', pedidoWebIdActivo);
            formData.append('nombre_cliente', nombreClienteActivo);
            formData.append('total', totalVenta);
            formData.append('items', JSON.stringify(itemsPedidoPresencial));

            fetch('market_express.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('¡Pedido finalizado con éxito a las ' + res.fecha_finalizado + '! Generando factura en PDF...');
                        window.open('factura.php?id=' + res.pedido_id, '_blank');

                        if (pedidoWebIdActivo > 0) {
                            pedidosCola.shift();
                        }
                        
                        // AL FINALIZAR PEDIDO: RESETEAR PARA EL PRÓXIMO CLIENTE
                        itemsPedidoPresencial = [];
                        nombreClienteActivo = 'Presencial';

                        limpiarMontoEfectivoYServidor();
                        cargarSiguientePedidoEnCola();
                    }
                })
                .catch(err => {
                    alert('¡Venta finalizada con éxito! Generando PDF...');
                    if (pedidoWebIdActivo > 0) pedidosCola.shift();
                    
                    itemsPedidoPresencial = [];
                    nombreClienteActivo = 'Presencial';

                    limpiarMontoEfectivoYServidor();
                    cargarSiguientePedidoEnCola();
                });
        }

        function limpiarMontoEfectivoYServidor() {
            const inputEfectivo = id('cash-received');
            if (inputEfectivo) inputEfectivo.value = '';
            id('cash-change').innerText = '$0.00';

            const formReset = new FormData();
            formReset.append('action', 'registrar_efectivo');
            formReset.append('monto', 0);
            fetch('api_ia.php', { method: 'POST', body: formReset }).catch(err => {});
        }

        // CLIENTE: CARRITO E INTERFAZ
        let carrito = [];

        function abrirModalProductoPorId(prodId) {
            const p = listaProductosBD.find(item => item.id == prodId);
            if (!p) return;

            id('modal-img').src = p.imagen_ruta || ('uploads/' + p.imagen);
            id('modal-title').innerText = p.nombre;
            id('modal-desc').innerText = p.descripcion || 'Sin descripción disponible.';
            id('modal-price').innerText = '$' + parseFloat(p.precio).toFixed(2);
            
            const badge = id('modal-stock-badge');
            const addBtn = id('modal-add-btn');

            if (parseInt(p.stock) > 0) {
                badge.innerHTML = '<span style="color:#81d181; font-weight:600; font-size:0.8rem;">Disponible: ' + p.stock + ' unidades</span>';
                addBtn.disabled = false;
                addBtn.onclick = function() { agregarAlCarrito(p, addBtn); cerrarModalProducto(); };
            } else {
                badge.innerHTML = '<span style="color:#ff8b88; font-weight:600; font-size:0.8rem;">Producto Agotado</span>';
                addBtn.disabled = true;
            }
            id('product-modal').style.display = 'flex';
        }

        function agregarAlCarritoPorId(prodId, ev, btn) {
            if (ev) ev.stopPropagation();
            const p = listaProductosBD.find(item => item.id == prodId);
            if (p) agregarAlCarrito(p, btn);
        }

        function cerrarModalProducto() { id('product-modal').style.display = 'none'; }

        function agregarAlCarrito(p, btn) {
            if (btn) {
                if (!btn.dataset.originalHtml) {
                    btn.dataset.originalHtml = btn.innerHTML;
                }
                if (btn.dataset.timerId) {
                    clearTimeout(parseInt(btn.dataset.timerId));
                }

                btn.classList.add('btn-added-pulse');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Añadido!';

                const timerId = setTimeout(() => {
                    btn.classList.remove('btn-added-pulse');
                    if (btn.dataset.originalHtml) {
                        btn.innerHTML = btn.dataset.originalHtml;
                        delete btn.dataset.originalHtml;
                    }
                    delete btn.dataset.timerId;
                }, 600);

                btn.dataset.timerId = timerId.toString();
            }

            const existe = carrito.find(item => item.id === p.id);
            if (existe) {
                existe.cantidad++;
            } else {
                carrito.push({ id: p.id, nombre: p.nombre, precio: parseFloat(p.precio), descuento: parseFloat(p.descuento || 0), cantidad: 1 });
            }
            actualizarInterfazCarrito();
        }

        function removerDelCarrito(index) {
            carrito.splice(index, 1);
            actualizarInterfazCarrito();
        }

        function vaciarCarrito() {
            carrito = [];
            actualizarInterfazCarrito();
        }

        function actualizarInterfazCarrito() {
            const list = id('cart-items-list');
            const fabBadge = id('fab-cart-count');

            let totalItems = 0;
            carrito.forEach(i => totalItems += i.cantidad);

            if (fabBadge) fabBadge.innerText = totalItems;

            if (!list) return;

            if (carrito.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: var(--color-beige); opacity: 0.6; font-size: 0.85rem; margin: 20px 0;">Tu carrito está vacío.</p>';
                id('cart-subtotal').innerText = '$0.00';
                id('cart-discount').innerText = '-$0.00';
                id('cart-tax').innerText = '$0.00';
                id('cart-total').innerText = '$0.00';
                return;
            }

            let html = '';
            let subtotal = 0;
            let descuentoTotal = 0;

            carrito.forEach((item, idx) => {
                const itemSub = item.precio * item.cantidad;
                const itemDesc = itemSub * item.descuento;
                subtotal += itemSub;
                descuentoTotal += itemDesc;

                html += `
                <div class="cart-item">
                    <div>
                        <strong style="font-size: 0.85rem; color: #fff;">${item.nombre}</strong>
                        <div style="font-size: 0.75rem; color: var(--color-beige);">${item.cantidad} x $${item.precio.toFixed(2)}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-weight: 600; color: var(--color-gold, #decab0); font-size: 0.88rem;">$${(itemSub - itemDesc).toFixed(2)}</span>
                        <button onclick="removerDelCarrito(${idx})" style="background: none; border: none; color: #ff8b88; cursor: pointer; padding: 2px 4px;" title="Eliminar producto"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </div>`;
            });

            list.innerHTML = html;

            const baseImpuesto = subtotal - descuentoTotal;
            const iva = baseImpuesto * 0.13;
            const total = baseImpuesto + iva;

            id('cart-subtotal').innerText = '$' + subtotal.toFixed(2);
            id('cart-discount').innerText = '-$' + descuentoTotal.toFixed(2);
            id('cart-tax').innerText = '$' + iva.toFixed(2);
            id('cart-total').innerText = '$' + total.toFixed(2);
        }

        function abrirCarritoMovil() {
            const cartSec = id('cart-panel-section');
            if (cartSec) {
                cartSec.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function procesarPedidoCliente() {
            if (carrito.length === 0) {
                alert('El carrito está vacío.');
                return;
            }

            let subtotal = 0;
            carrito.forEach(i => subtotal += (i.precio * i.cantidad));
            const totalFinal = subtotal * 1.13;

            const formData = new FormData();
            formData.append('action', 'crear_pedido_cliente');
            formData.append('total', totalFinal);
            formData.append('items', JSON.stringify(carrito));

            fetch('market_express.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('¡Tu pedido ha sido finalizado con éxito! Ha sido enviado a la caja.');
                        vaciarCarrito();
                        window.location.href = 'pedidos.php';
                    }
                })
                .catch(err => {
                    alert('¡Pedido registrado! Pasando a caja.');
                    vaciarCarrito();
                });
        }

        function filtrarProductosDirecto() {
            const query = id('product-search').value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                card.style.display = name.includes(query) ? 'flex' : 'none';
            });
        }

        function filtrarCategoria(catId, btn, nombreCat) {
            document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');

            const tituloCat = id('selected-category-title');
            if (tituloCat) {
                tituloCat.innerHTML = '<i class="fa-solid fa-boxes-stacked"></i> Categoría: ' + nombreCat;
            }

            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                if (catId === 'todas' || card.getAttribute('data-category') === catId) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        const openBtn = id('open-sidebar');
        const closeBtn = id('close-sidebar');
        const drawer = id('sidebar-drawer');
        const overlay = id('sidebar-overlay');

        if (openBtn) openBtn.addEventListener('click', () => { drawer.classList.add('active'); overlay.classList.add('active'); });
        if (closeBtn) closeBtn.addEventListener('click', () => { drawer.classList.remove('active'); overlay.classList.remove('active'); });
        if (overlay) overlay.addEventListener('click', () => { drawer.classList.remove('active'); overlay.classList.remove('active'); });

        // ===================================================
        // SINCRONIZACIÓN EN TIEMPO REAL CON LA APP MÓVIL
        // ===================================================
        setInterval(() => {
            fetch('api_ia.php?action=obtener_estado')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const inputEfectivo = id('cash-received');
                        const montoFloat = parseFloat(data.monto_efectivo) || 0;

                        // Solo actualizar desde el teléfono si el usuario NO está escribiendo activamente en la laptop
                        if (inputEfectivo && document.activeElement !== inputEfectivo) {
                            if (montoFloat > 0 && parseFloat(inputEfectivo.value || 0) !== montoFloat) {
                                inputEfectivo.value = montoFloat.toFixed(2);
                                if (typeof calcularCambioDirecto === 'function') {
                                    calcularCambioDirecto();
                                }
                            }
                        }

                        if (data.nuevo_qr) {
                            if (typeof window.procesarCodigoEscaneado === 'function') {
                                window.procesarCodigoEscaneado(data.nuevo_qr);
                            }
                        }
                    }
                })
                .catch(err => {});
        }, 900);
    </script>
</body>
</html>
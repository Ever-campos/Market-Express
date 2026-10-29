<?php
session_start();
require_once 'conexion.php';

// Control de acceso: Solo administradores pueden ingresar
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['nombre'] ?? 'Administrador General';
$mensaje = '';
$tipoMensaje = '';

// Crear carpeta uploads/ si no existe
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

// ===================================================
// VERIFICAR Y MIGRAR COLUMNA CODIGO EN LA BASE DE DATOS
// ===================================================
try {
    $pdo->exec("ALTER TABLE productos ADD COLUMN codigo VARCHAR(50) DEFAULT NULL AFTER categoria_id");
} catch (Exception $e) {
    // La columna ya existe
}

// Asignar código numérico automático a productos que aún no lo tengan
try {
    $pdo->exec("UPDATE productos SET codigo = LPAD(id + 100000, 6, '0') WHERE codigo IS NULL OR codigo = ''");
} catch (Exception $e) {
    // Error silencioso
}

// ===================================================
// PROCESAMIENTO DE ACCIONES (POST Y GET)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. AGREGAR NUEVO PRODUCTO
    if ($action === 'agregar_producto') {
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $categoriaId = intval($_POST['categoria_id'] ?? 0);
        $precio = floatval($_POST['precio'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descuento = floatval($_POST['descuento'] ?? 0);

        // Si no ingresa un código, generar uno numérico único de 6 dígitos
        if (empty($codigo)) {
            $codigo = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        }

        $nombreImagen = 'default_product.png';

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $nombreImagen = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
                move_uploaded_file($_FILES['imagen']['tmp_name'], 'uploads/' . $nombreImagen);
            }
        }

        if (!empty($nombre) && $categoriaId > 0 && $precio >= 0) {
            $stmt = $pdo->prepare("INSERT INTO productos (categoria_id, codigo, nombre, descripcion, precio, descuento, stock, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$categoriaId, $codigo, $nombre, $descripcion, $precio, $descuento, $stock, $nombreImagen])) {
                $mensaje = "¡Producto '$nombre' (Código: $codigo) agregado exitosamente al inventario!";
                $tipoMensaje = "exito";

                $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
                    ->execute([$adminId, "Agregó el producto: $nombre (Cód: $codigo)"]);
            } else {
                $mensaje = "Error al insertar el producto en la base de datos.";
                $tipoMensaje = "error";
            }
        } else {
            $mensaje = "Por favor completa los campos obligatorios del producto.";
            $tipoMensaje = "error";
        }
    }

    // B. EDITAR PRODUCTO EXISTENTE
    elseif ($action === 'editar_producto') {
        $id = intval($_POST['producto_id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $categoriaId = intval($_POST['categoria_id'] ?? 0);
        $precio = floatval($_POST['precio'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descuento = floatval($_POST['descuento'] ?? 0);

        if (empty($codigo)) {
            $codigo = str_pad($id + 100000, 6, '0', STR_PAD_LEFT);
        }

        $stmtImg = $pdo->prepare("SELECT imagen FROM productos WHERE id = ?");
        $stmtImg->execute([$id]);
        $prodActual = $stmtImg->fetch();
        $nombreImagen = $prodActual['imagen'] ?? 'default_product.png';

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $nuevoNombre = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], 'uploads/' . $nuevoNombre)) {
                    $nombreImagen = $nuevoNombre;
                }
            }
        }

        if ($id > 0 && !empty($nombre) && $categoriaId > 0) {
            $stmt = $pdo->prepare("UPDATE productos SET categoria_id = ?, codigo = ?, nombre = ?, descripcion = ?, precio = ?, descuento = ?, stock = ?, imagen = ? WHERE id = ?");
            if ($stmt->execute([$categoriaId, $codigo, $nombre, $descripcion, $precio, $descuento, $stock, $nombreImagen, $id])) {
                $mensaje = "¡Producto '$nombre' (Cód: $codigo) actualizado correctamente!";
                $tipoMensaje = "exito";

                $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
                    ->execute([$adminId, "Editó datos del producto ID: $id ($nombre)"]);
            } else {
                $mensaje = "Error al actualizar el producto.";
                $tipoMensaje = "error";
            }
        }
    }

    // C. AGREGAR O DESCONTAR STOCK RÁPIDO
    elseif ($action === 'actualizar_stock') {
        $id = intval($_POST['producto_id'] ?? 0);
        $operacion = $_POST['operacion'] ?? '';
        $cantidad = intval($_POST['cantidad_stock'] ?? 0);

        if ($id > 0 && $cantidad > 0) {
            if ($operacion === 'sumar') {
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$cantidad, $id]);
                $mensaje = "Se agregaron +$cantidad unidades al stock.";
                $tipoMensaje = "exito";
            } elseif ($operacion === 'restar') {
                $stmt = $pdo->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?");
                $stmt->execute([$cantidad, $id]);
                $mensaje = "Se descontaron -$cantidad unidades del stock.";
                $tipoMensaje = "exito";
            }

            $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
                ->execute([$adminId, "Ajustó stock ($operacion $cantidad) en producto ID: $id"]);
        }
    }

    // D. AGREGAR CATEGORÍA
    elseif ($action === 'agregar_categoria') {
        $nombreCat = trim($_POST['nombre_categoria'] ?? '');
        $iconoCat = trim($_POST['icono_categoria'] ?? 'fa-tag');

        if (!empty($nombreCat)) {
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, icono) VALUES (?, ?)");
            if ($stmt->execute([$nombreCat, $iconoCat])) {
                $mensaje = "¡Categoría '$nombreCat' creada con éxito!";
                $tipoMensaje = "exito";

                $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
                    ->execute([$adminId, "Creó categoría: $nombreCat"]);
            }
        }
    }
}

// E. ELIMINAR PRODUCTO (POR GET)
if (isset($_GET['delete_prod'])) {
    $prodId = intval($_GET['delete_prod']);
    if ($prodId > 0) {
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        if ($stmt->execute([$prodId])) {
            $mensaje = "Producto eliminado correctamente del inventario.";
            $tipoMensaje = "exito";

            $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
                ->execute([$adminId, "Eliminó el producto ID: $prodId"]);
        }
    }
}

// F. ELIMINAR CATEGORÍA (POR GET)
if (isset($_GET['delete_cat'])) {
    $catId = intval($_GET['delete_cat']);
    if ($catId > 0) {
        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
        if ($stmt->execute([$catId])) {
            $mensaje = "Categoría eliminada del sistema.";
            $tipoMensaje = "exito";

            $pdo->prepare("INSERT INTO historial_admin (admin_id, accion) VALUES (?, ?)")
                ->execute([$adminId, "Eliminó la categoría ID: $catId"]);
        }
    }
}

// ===================================================
// CONSULTA DE DATOS PARA LA INTERFAZ
// ===================================================
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$todosProductos = $pdo->query("SELECT p.*, c.nombre AS categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY p.nombre ASC")->fetchAll();

$productosPorCategoria = [];
foreach ($todosProductos as $prod) {
    $catId = $prod['categoria_id'] ?? 0;
    $productosPorCategoria[$catId][] = $prod;
}
$copyrightText = "© 2026 Market express - todos los derechos son reservados a estudiantes de 2B del Instituto Nacional de Apopa (INA)";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Productos - Market Express</title>
    
    <!-- Fuentes e Íconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">

    <style>
        html {
            height: 100%;
        }

        body.dashboard-body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 18px;
            background: rgba(20, 25, 40, 0.95);
            border-bottom: 1px solid rgba(173, 147, 103, 0.3);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title {
            font-size: 1.15rem;
            margin: 0;
            color: var(--color-light, #ffffff);
            font-weight: 700;
            line-height: 1.2;
        }

        .header-title span {
            color: var(--color-gold, #decab0);
        }

        .header-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-greeting {
            font-size: 0.82rem;
            color: var(--color-beige, #a0aec0);
            white-space: nowrap;
        }

        .user-greeting i {
            color: var(--color-gold, #decab0);
        }

        .mobile-only { display: none !important; }
        .pc-only { display: inline-flex !important; align-items: center; gap: 6px; }

        .inventory-container {
            max-width: 1250px;
            width: 95%;
            margin: 20px auto;
            flex: 1 0 auto;
        }

        .inventory-toolbar {
            background: rgba(35, 42, 63, 0.85);
            border: 1px solid rgba(173, 147, 103, 0.3);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar-search {
            flex: 1;
            min-width: 240px;
        }

        .toolbar-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .category-section {
            background: rgba(35, 42, 63, 0.7);
            border: 1px solid rgba(173, 147, 103, 0.25);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 25px;
            backdrop-filter: blur(8px);
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .category-header-title {
            color: var(--color-gold, #decab0);
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .products-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .inventory-card {
            background: rgba(18, 23, 37, 0.85);
            border: 1px solid rgba(173, 147, 103, 0.3);
            border-radius: 12px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .inventory-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            border-color: var(--color-gold, #decab0);
        }

        .inventory-card-img-box {
            position: relative;
            width: 100%;
            height: 130px;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
            background: rgba(0,0,0,0.3);
        }

        .inventory-card-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .code-tag-badge {
            position: absolute;
            top: 6px;
            left: 6px;
            background: rgba(26, 31, 48, 0.9);
            color: var(--color-gold, #decab0);
            font-size: 0.68rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid rgba(173, 147, 103, 0.4);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .inventory-card-title {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .inventory-card-price {
            color: var(--color-gold, #decab0);
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .stock-control-box {
            background: rgba(0,0,0,0.35);
            padding: 6px 10px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 8px 0;
        }

        .stock-badge-num { font-weight: 700; font-size: 0.85rem; color: #81d181; }
        .stock-out-num { color: #ff8b88; }

        .stock-btn-action {
            background: var(--color-gold, #decab0);
            color: #1a1f30;
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        .card-actions-row {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .btn-card-qr {
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            border: 1px solid #2563eb;
            padding: 6px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-card-edit {
            flex: 1;
            background: rgba(173, 147, 103, 0.2);
            color: var(--color-gold, #decab0);
            border: 1px solid var(--color-gold, #decab0);
            padding: 6px;
            border-radius: 6px;
            font-size: 0.78rem;
            cursor: pointer;
            text-align: center;
        }

        .btn-card-delete {
            background: rgba(217, 83, 79, 0.2);
            color: #ff8b88;
            border: 1px solid #d9534f;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: none;
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
            padding: 22px;
            max-width: 520px;
            width: 92%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 8px;
        }

        .alert-box {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .alert-exito { background: rgba(74, 222, 128, 0.15); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
        .alert-error { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3); }

        .app-footer {
            position: relative !important;
            bottom: auto !important;
            left: auto;
            right: auto;
            clear: both;
            flex-shrink: 0;
            text-align: center;
            color: #a0aec0;
            font-size: 0.75rem;
            padding: 20px 10px;
            margin-top: auto;
            width: 100%;
            box-sizing: border-box;
            background: rgba(15, 19, 32, 0.8);
            border-top: 1px solid rgba(173, 147, 103, 0.2);
        }

        @media (max-width: 700px) {
            .pc-only { display: none !important; }
            
            .app-header {
                display: grid;
                grid-template-columns: auto 1fr auto;
                align-items: center;
                padding: 10px 12px;
            }

            .header-left {
                display: contents;
            }

            .brand-title-group {
                grid-column: 2;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 2px;
            }

            .header-title {
                font-size: 1rem;
                text-align: center;
            }

            .mobile-only {
                display: inline-flex !important;
                font-size: 0.7rem;
                opacity: 0.92;
                justify-content: center;
            }

            .header-spacer {
                display: block !important;
                grid-column: 3;
                width: 68px;
            }

            .products-grid-3 { grid-template-columns: repeat(2, 1fr) !important; gap: 10px; }
            .inventory-container { padding: 0 4px; }
            .inventory-card { padding: 10px; }
            .inventory-card-img-box { height: 100px; }
        }

        @media (max-width: 480px) {
            .products-grid-3 { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- BARRA SUPERIOR RESPONSIVA -->
    <header class="app-header">
        <div class="header-left">
            <a href="market_express.php" class="btn btn-secondary btn-compact" style="background: rgba(255,255,255,0.08); color: #ffffff; padding: 5px 10px; border-radius: 6px; text-decoration: none; font-size: 0.78rem; white-space: nowrap;">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>

            <div class="brand-title-group">
                <h1 class="header-title">Market <span>Express</span></h1>
                <span class="user-greeting mobile-only"><i class="fa-solid fa-user-shield"></i> <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong></span>
            </div>
        </div>
        
        <div class="header-user-info pc-only">
            <span class="user-greeting"><i class="fa-solid fa-user-shield"></i> <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong></span>
        </div>

        <div class="header-spacer mobile-only"></div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="inventory-container">

        <?php if (!empty($mensaje)): ?>
            <div class="alert-box alert-<?php echo $tipoMensaje; ?>">
                <i class="fa-solid <?php echo $tipoMensaje === 'exito' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- BARRA DE HERRAMIENTAS -->
        <section class="inventory-toolbar">
            <div class="toolbar-search">
                <div class="input-wrapper" style="position: relative;">
                    <input type="text" id="inventory-search" class="form-control-compact" style="padding: 6px 10px; font-size: 0.8rem; width: 100%; border-radius: 6px; background: #ffffff; color: #1a202c;" placeholder="Buscar producto o código en inventario..." onkeyup="filtrarInventario()">
                </div>
            </div>

            <div class="toolbar-buttons">
                <button class="btn btn-primary btn-compact" onclick="abrirModalNuevoProducto()" style="background: var(--color-gold, #decab0); color: #1a202c; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> Agregar Producto
                </button>
                <button class="btn btn-secondary btn-compact" onclick="abrirModalNuevaCategoria()" style="background: rgba(255,255,255,0.08); color: #ffffff; border: 1px solid rgba(173,147,103,0.4); border-radius: 6px; padding: 6px 12px; font-size: 0.78rem; cursor: pointer;">
                    <i class="fa-solid fa-folder-plus"></i> Nueva Categoría
                </button>
                <button class="btn btn-primary btn-compact" onclick="abrirModalReporte()" style="background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-file-pdf"></i> Generar Reporte
                </button>
            </div>
        </section>

        <!-- SECCIONES DE PRODUCTOS -->
        <?php if (count($categorias) > 0): ?>
            <?php foreach ($categorias as $cat): 
                $prodsCat = $productosPorCategoria[$cat['id']] ?? [];
            ?>
                <section class="category-section" data-cat-name="<?php echo strtolower($cat['nombre']); ?>">
                    <div class="category-header">
                        <h3 class="category-header-title">
                            <i class="fa-solid <?php echo !empty($cat['icono']) ? $cat['icono'] : 'fa-tag'; ?>"></i>
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                            <span style="font-size: 0.78rem; color: #a0aec0; opacity: 0.8;">(<?php echo count($prodsCat); ?>)</span>
                        </h3>
                        <div>
                            <a href="inventario.php?delete_cat=<?php echo $cat['id']; ?>" onclick="return confirm('¿Eliminar la categoría <?php echo htmlspecialchars($cat['nombre']); ?>?');" style="color: #ff8b88; font-size: 0.8rem; text-decoration: none;">
                                <i class="fa-solid fa-trash-can"></i> Eliminar
                            </a>
                        </div>
                    </div>

                    <div class="products-grid-3">
                        <?php if (count($prodsCat) > 0): ?>
                            <?php foreach ($prodsCat as $p): 
                                $rutaImg = 'uploads/' . $p['imagen'];
                                if (!file_exists($rutaImg) || empty($p['imagen'])) {
                                    $rutaImg = 'assets/' . $p['imagen'];
                                }
                                $codigoProd = !empty($p['codigo']) ? $p['codigo'] : str_pad($p['id'] + 100000, 6, '0', STR_PAD_LEFT);
                            ?>
                                <div class="inventory-card" data-prod-name="<?php echo strtolower($p['nombre']); ?>" data-prod-code="<?php echo strtolower($codigoProd); ?>">
                                    <div>
                                        <div class="inventory-card-img-box">
                                            <span class="code-tag-badge"><i class="fa-solid fa-barcode"></i> Cód: <?php echo htmlspecialchars($codigoProd); ?></span>
                                            <img src="<?php echo $rutaImg; ?>" class="inventory-card-img" onerror="this.onerror=null; this.src='https://via.placeholder.com/250x130/29314a/decab0?text=Producto';" alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                                        </div>
                                        <h4 class="inventory-card-title"><?php echo htmlspecialchars($p['nombre']); ?></h4>
                                        <div class="inventory-card-price">$<?php echo number_format($p['precio'], 2); ?></div>
                                    </div>

                                    <div>
                                        <div class="stock-control-box">
                                            <span style="font-size: 0.75rem; color: #a0aec0;">Stock:</span>
                                            <span class="stock-badge-num <?php echo ($p['stock'] <= 0) ? 'stock-out-num' : ''; ?>">
                                                <?php echo $p['stock']; ?> un.
                                            </span>
                                            <div style="display: flex; gap: 4px;">
                                                <button type="button" class="stock-btn-action" onclick="abrirModalStock(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['nombre'])); ?>', 'restar')">-</button>
                                                <button type="button" class="stock-btn-action" onclick="abrirModalStock(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['nombre'])); ?>', 'sumar')">+</button>
                                            </div>
                                        </div>

                                        <div class="card-actions-row">
                                            <button type="button" class="btn-card-qr" onclick="verQRProducto('<?php echo htmlspecialchars(addslashes($p['nombre'])); ?>', '<?php echo $codigoProd; ?>', '<?php echo number_format($p['precio'], 2); ?>')" title="Ver e imprimir QR">
                                                <i class="fa-solid fa-qrcode"></i> QR
                                            </button>
                                            <button type="button" class="btn-card-edit" onclick="abrirModalEditarProducto(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                                <i class="fa-solid fa-pen-to-square"></i> Editar
                                            </button>
                                            <a href="inventario.php?delete_prod=<?php echo $p['id']; ?>" class="btn-card-delete" onclick="return confirm('¿Eliminar <?php echo htmlspecialchars($p['nombre']); ?>?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #a0aec0; opacity: 0.6; font-size: 0.85rem; grid-column: span 3;">Sin productos en esta categoría.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- MODAL: AGREGAR PRODUCTO -->
    <div id="modalNuevoProducto" class="modal-overlay">
        <div class="modal-body-card">
            <div class="modal-header-flex">
                <h3 style="color: var(--color-gold, #decab0); margin: 0; font-size: 1.05rem;"><i class="fa-solid fa-box-open"></i> Agregar Nuevo Producto</h3>
                <button type="button" onclick="cerrarModal('modalNuevoProducto')" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="inventario.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="agregar_producto">

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Nombre del Producto *</label>
                        <input type="text" name="nombre" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                    </div>
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Código Numérico</label>
                        <input type="text" name="codigo" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" placeholder="Auto (Ej: 742109)">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Categoría *</label>
                        <select name="categoria_id" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Precio ($) *</label>
                        <input type="number" step="0.01" name="precio" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Stock Inicial *</label>
                        <input type="number" name="stock" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" value="10" required>
                    </div>

                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Descuento (0 a 1)</label>
                        <input type="number" step="0.01" name="descuento" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" value="0.00">
                    </div>
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Imagen</label>
                    <input type="file" name="imagen" class="form-control-compact" style="width: 100%; font-size: 0.78rem;" accept="image/*">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Descripción</label>
                    <textarea name="descripcion" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" rows="2"></textarea>
                </div>

                <button type="submit" style="width: 100%; background: #decab0; color: #1a202c; border: none; border-radius: 6px; padding: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Producto
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL: EDITAR PRODUCTO -->
    <div id="modalEditarProducto" class="modal-overlay">
        <div class="modal-body-card">
            <div class="modal-header-flex">
                <h3 style="color: var(--color-gold, #decab0); margin: 0; font-size: 1.05rem;"><i class="fa-solid fa-pen-to-square"></i> Editar Producto</h3>
                <button type="button" onclick="cerrarModal('modalEditarProducto')" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="inventario.php" method="POST" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="editar_producto">
                <input type="hidden" name="producto_id" id="edit_producto_id">

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Nombre del Producto</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                    </div>
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Código Numérico</label>
                        <input type="text" name="codigo" id="edit_codigo" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Categoría</label>
                        <select name="categoria_id" id="edit_categoria_id" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Precio ($)</label>
                        <input type="number" step="0.01" name="precio" id="edit_precio" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Stock Actual</label>
                        <input type="number" name="stock" id="edit_stock" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" required>
                    </div>

                    <div>
                        <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Descuento (0 a 1)</label>
                        <input type="number" step="0.01" name="descuento" id="edit_descuento" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;">
                    </div>
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Cambiar Foto</label>
                    <input type="file" name="imagen" class="form-control-compact" style="width: 100%; font-size: 0.78rem;" accept="image/*">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Descripción</label>
                    <textarea name="descripcion" id="edit_descripcion" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" rows="2"></textarea>
                </div>

                <button type="submit" style="width: 100%; background: #decab0; color: #1a202c; border: none; border-radius: 6px; padding: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL: VER E IMPRIMIR QR DEL PRODUCTO -->
    <div id="modalVerQR" class="modal-overlay">
        <div class="modal-body-card" style="max-width: 360px; text-align: center;">
            <div class="modal-header-flex">
                <h3 style="color: #decab0; margin: 0; font-size: 1rem;"><i class="fa-solid fa-qrcode"></i> Código QR de Producto</h3>
                <button type="button" onclick="cerrarModal('modalVerQR')" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div id="printable-qr-box" style="background: #ffffff; padding: 16px; border-radius: 12px; margin-bottom: 14px; color: #1a202c;">
                <h4 id="qr_prod_name" style="margin: 0 0 4px 0; font-size: 1rem; color: #1a1f30; font-weight: 800;"></h4>
                <p id="qr_prod_price" style="margin: 0 0 10px 0; font-size: 0.9rem; color: #059669; font-weight: 700;"></p>
                <img id="qr_img_tag" src="" alt="Código QR" style="width: 180px; height: 180px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px;">
                <div style="margin-top: 8px; font-family: monospace; font-size: 0.9rem; font-weight: 800; color: #1a1f30; letter-spacing: 1px;">
                    CÓD: <span id="qr_prod_code"></span>
                </div>
            </div>

            <button type="button" onclick="imprimirEtiquetaQR()" style="width: 100%; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 8px; font-size: 0.82rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="fa-solid fa-print"></i> Imprimir Etiqueta QR
            </button>
        </div>
    </div>

    <!-- MODAL: AJUSTE DE STOCK -->
    <div id="modalStock" class="modal-overlay">
        <div class="modal-body-card" style="max-width: 380px;">
            <div class="modal-header-flex">
                <h3 style="color: #decab0; margin: 0; font-size: 1rem;" id="stock_modal_title">Ajustar Stock</h3>
                <button type="button" onclick="cerrarModal('modalStock')" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="inventario.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="actualizar_stock">
                <input type="hidden" name="producto_id" id="stock_producto_id">
                <input type="hidden" name="operacion" id="stock_operacion">

                <p id="stock_prod_name" style="color: #ffffff; font-weight: 600; font-size: 0.85rem; margin-bottom: 12px;"></p>

                <div style="margin-bottom: 15px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Cantidad de unidades</label>
                    <input type="number" name="cantidad_stock" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" value="1" min="1" required>
                </div>

                <button type="submit" id="stock_submit_btn" style="width: 100%; border: none; border-radius: 6px; padding: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">Confirmar</button>
            </form>
        </div>
    </div>

    <!-- MODAL: NUEVA CATEGORÍA -->
    <div id="modalNuevaCategoria" class="modal-overlay">
        <div class="modal-body-card" style="max-width: 380px;">
            <div class="modal-header-flex">
                <h3 style="color: #decab0; margin: 0; font-size: 1rem;"><i class="fa-solid fa-folder-plus"></i> Nueva Categoría</h3>
                <button type="button" onclick="cerrarModal('modalNuevaCategoria')" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="inventario.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="agregar_categoria">

                <div style="margin-bottom: 12px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Nombre *</label>
                    <input type="text" name="nombre_categoria" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" placeholder="Ej: Lácteos, Bebidas" required>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="color: #a0aec0; font-size: 0.78rem; display: block; margin-bottom: 3px;">Ícono FontAwesome</label>
                    <input type="text" name="icono_categoria" class="form-control-compact" style="width: 100%; padding: 6px; font-size: 0.8rem; border-radius: 6px;" placeholder="fa-tag, fa-bottle-water" value="fa-tag">
                </div>

                <button type="submit" style="width: 100%; background: #decab0; color: #1a202c; border: none; border-radius: 6px; padding: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> Crear Categoría
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL: REPORTE DIARIO / SEMANAL -->
    <div id="modalReporte" class="modal-overlay">
        <div class="modal-body-card" style="max-width: 400px; text-align: center;">
            <div class="modal-header-flex">
                <h3 style="color: #decab0; margin: 0; font-size: 1.05rem;"><i class="fa-solid fa-file-pdf"></i> Generar Reporte de Inventario</h3>
                <button type="button" onclick="cerrarModal('modalReporte')" style="background: none; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <p style="color: #a0aec0; font-size: 0.8rem; margin-bottom: 18px;">Selecciona la periodicidad del reporte para generar el documento PDF formateado.</p>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="reporte_inventario.php?tipo=diario" target="_blank" style="background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-day"></i> Reporte Diario
                </a>
                <a href="reporte_inventario.php?tipo=semanal" target="_blank" style="background: #059669; color: #ffffff; text-decoration: none; padding: 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-week"></i> Reporte Semanal
                </a>
            </div>
        </div>
    </div>

    <script>
        function id(e) { return document.getElementById(e); }
        function cerrarModal(modalId) { id(modalId).style.display = 'none'; }
        function abrirModalNuevoProducto() { id('modalNuevoProducto').style.display = 'flex'; }
        function abrirModalNuevaCategoria() { id('modalNuevaCategoria').style.display = 'flex'; }
        function abrirModalReporte() { id('modalReporte').style.display = 'flex'; }

        function verQRProducto(nombre, codigo, precio) {
            id('qr_prod_name').innerText = nombre;
            id('qr_prod_code').innerText = codigo;
            id('qr_prod_price').innerText = '$' + precio;
            
            // Generar código QR dinámico seguro
            const urlQR = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(codigo);
            id('qr_img_tag').src = urlQR;

            id('modalVerQR').style.display = 'flex';
        }

        function imprimirEtiquetaQR() {
            const contenido = id('printable-qr-box').outerHTML;
            const ventana = window.open('', '_blank', 'width=400,height=500');
            ventana.document.write('<html><head><title>Imprimir QR</title>');
            ventana.document.write('<style>body{font-family:sans-serif; text-align:center; padding:20px;}</style></head><body>');
            ventana.document.write(contenido);
            ventana.document.write('</body></html>');
            ventana.document.close();
            ventana.focus();
            setTimeout(() => { ventana.print(); ventana.close(); }, 500);
        }

        function abrirModalEditarProducto(prod) {
            id('edit_producto_id').value = prod.id;
            id('edit_nombre').value = prod.nombre;
            id('edit_codigo').value = prod.codigo || (parseInt(prod.id) + 100000);
            id('edit_categoria_id').value = prod.categoria_id;
            id('edit_precio').value = prod.precio;
            id('edit_stock').value = prod.stock;
            id('edit_descuento').value = prod.descuento || 0;
            id('edit_descripcion').value = prod.descripcion || '';
            id('modalEditarProducto').style.display = 'flex';
        }

        function abrirModalStock(prodId, prodNombre, operacion) {
            id('stock_producto_id').value = prodId;
            id('stock_operacion').value = operacion;

            if (operacion === 'sumar') {
                id('stock_modal_title').innerText = '➕ Agregar Stock';
                id('stock_prod_name').innerText = 'Sumar a: ' + prodNombre;
                id('stock_submit_btn').style.background = '#4ade80';
                id('stock_submit_btn').style.color = '#111';
            } else {
                id('stock_modal_title').innerText = '➖ Descontar Stock';
                id('stock_prod_name').innerText = 'Restar a: ' + prodNombre;
                id('stock_submit_btn').style.background = '#f87171';
                id('stock_submit_btn').style.color = '#fff';
            }
            id('modalStock').style.display = 'flex';
        }

        function filtrarInventario() {
            const query = id('inventory-search').value.toLowerCase();
            const tarjetas = document.querySelectorAll('.inventory-card');
            const secciones = document.querySelectorAll('.category-section');

            tarjetas.forEach(card => {
                const nombre = card.getAttribute('data-prod-name');
                const codigo = card.getAttribute('data-prod-code');
                card.style.display = (nombre.includes(query) || codigo.includes(query)) ? 'flex' : 'none';
            });

            secciones.forEach(sec => {
                const catNombre = sec.getAttribute('data-cat-name');
                const tieneVisibles = Array.from(sec.querySelectorAll('.inventory-card')).some(c => c.style.display !== 'none');
                if (catNombre.includes(query) || tieneVisibles) {
                    sec.style.display = 'block';
                    if (catNombre.includes(query)) {
                        sec.querySelectorAll('.inventory-card').forEach(c => c.style.display = 'flex');
                    }
                } else {
                    sec.style.display = 'none';
                }
            });
        }
    </script>

    <!-- PIE DE PÁGINA -->
    <footer class="app-footer">
        <?php echo $copyrightText; ?>
    </footer>
</body>
</html>
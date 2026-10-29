<?php
session_start();
require_once 'conexion.php';

// Control de acceso: Solo usuarios logueados pueden ver sus pedidos
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';
$copyrightText = "© 2026 Market Express - Todos los derechos reservados";

// ===================================================
// OBTENER HISTORIAL DE PEDIDOS DEL USUARIO
// ===================================================
try {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY fecha DESC");
    $stmt->execute([$usuarioId]);
    $misPedidos = $stmt->fetchAll();
} catch (PDOException $e) {
    $misPedidos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - Market Express</title>
    
    <!-- Fuentes e Íconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">

    <style>
        /* Layout Flexbox global para asegurar el footer al fondo de la pantalla */
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

        /* ===================================================
           HEADER RESPONSIVO
           =================================================== */
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

        /* ===================================================
           CONTENEDOR DE PEDIDOS
           =================================================== */
        .orders-container {
            max-width: 1000px;
            width: 95%;
            margin: 24px auto;
            flex: 1 0 auto; /* Empuja el footer al final de la ventana */
        }

        .welcome-banner {
            background: linear-gradient(135deg, rgba(35, 42, 63, 0.9) 0%, rgba(20, 25, 40, 0.9) 100%);
            border: 1px solid rgba(173, 147, 103, 0.35);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .welcome-banner h2 {
            color: var(--color-gold, #decab0);
            font-size: 1.25rem;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-banner p {
            margin: 0;
            color: #a0aec0;
            font-size: 0.88rem;
        }

        /* TARJETAS DE PEDIDO */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .order-card {
            background: rgba(35, 42, 63, 0.7);
            border: 1px solid rgba(173, 147, 103, 0.25);
            border-radius: 14px;
            padding: 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            backdrop-filter: blur(8px);
            transition: transform 0.2s, border-color 0.2s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            border-color: var(--color-gold, #decab0);
        }

        .order-info-main {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .order-id {
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-date {
            font-size: 0.78rem;
            color: #a0aec0;
        }

        .order-status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
        }

        .status-pendiente {
            background: rgba(245, 158, 11, 0.18);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .status-completado, .status-entregado {
            background: rgba(52, 211, 153, 0.18);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.4);
        }

        .status-cancelado {
            background: rgba(248, 113, 113, 0.18);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.4);
        }

        .order-total-box {
            text-align: right;
        }

        .order-total-label {
            font-size: 0.75rem;
            color: #a0aec0;
            display: block;
        }

        .order-total-amount {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--color-gold, #decab0);
        }

        .empty-orders-state {
            text-align: center;
            padding: 40px 20px;
            background: rgba(35, 42, 63, 0.4);
            border: 1px dashed rgba(173, 147, 103, 0.3);
            border-radius: 14px;
        }

        .empty-orders-state i {
            font-size: 2.8rem;
            color: var(--color-gold, #decab0);
            margin-bottom: 12px;
            opacity: 0.8;
        }

        .empty-orders-state h3 {
            margin: 0 0 6px 0;
            color: #ffffff;
            font-size: 1.1rem;
        }

        .empty-orders-state p {
            color: #a0aec0;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        /* PIE DE PÁGINA PEGADITO AL FONDO */
        .app-footer {
            flex-shrink: 0;
            text-align: center;
            color: #a0aec0;
            font-size: 0.75rem;
            padding: 20px 10px;
            margin-top: 30px;
            width: 100%;
            box-sizing: border-box;
            background: rgba(15, 19, 32, 0.8);
            border-top: 1px solid rgba(173, 147, 103, 0.2);
        }

        /* ===================================================
           REGLAS MÓVILES (< 700px): CENTRADO
           =================================================== */
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

            .order-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-total-box {
                text-align: left;
                width: 100%;
                border-top: 1px solid rgba(255,255,255,0.08);
                padding-top: 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
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
                <span class="user-greeting mobile-only"><i class="fa-regular fa-user"></i> <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong></span>
            </div>
        </div>
        
        <div class="header-user-info pc-only">
            <span class="user-greeting"><i class="fa-regular fa-user"></i> <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong></span>
        </div>

        <div class="header-spacer mobile-only"></div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="orders-container">

        <!-- BANNER SOLICITADO -->
        <div class="welcome-banner">
            <h2><i class="fa-solid fa-receipt"></i> Mis Pedidos Realizados</h2>
            <p>Esta sección es donde has hecho tus pedidos. Aquí puedes revisar el historial y estado de cada uno.</p>
        </div>

        <!-- LISTA DE PEDIDOS -->
        <div class="orders-list">
            <?php if (count($misPedidos) > 0): ?>
                <?php foreach ($misPedidos as $pedido): 
                    $estadoClass = 'status-' . strtolower($pedido['estado'] ?? 'pendiente');
                ?>
                    <div class="order-card">
                        <div class="order-info-main">
                            <div class="order-id">
                                <i class="fa-solid fa-bag-shopping" style="color: var(--color-gold, #decab0);"></i>
                                Pedido #<?php echo $pedido['id']; ?>
                            </div>
                            <div class="order-date">
                                <i class="fa-regular fa-clock"></i> 
                                <?php echo date('d/m/Y - h:i A', strtotime($pedido['fecha'])); ?>
                            </div>
                        </div>

                        <div>
                            <span class="order-status-badge <?php echo $estadoClass; ?>">
                                <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i>
                                <?php echo htmlspecialchars(ucfirst($pedido['estado'] ?? 'Pendiente')); ?>
                            </span>
                        </div>

                        <div class="order-total-box">
                            <span class="order-total-label">Total del Pedido:</span>
                            <span class="order-total-amount">$<?php echo number_format($pedido['total'], 2); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-orders-state">
                    <i class="fa-solid fa-cart-flatbed"></i>
                    <h3>Aún no has realizado ningún pedido</h3>
                    <p>Explora nuestro catálogo completo en Market Express y realiza tu primera compra.</p>
                    <a href="market_express.php" class="btn btn-primary" style="background: var(--color-gold, #decab0); color: #1a202c; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; display: inline-block;">
                        <i class="fa-solid fa-store"></i> Ir al Catálogo
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="app-footer">
        <?php echo $copyrightText; ?>
    </footer>

</body>
</html>
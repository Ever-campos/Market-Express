<?php
session_start();
require_once 'conexion.php';

// Verificar permisos de administrador
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminNombre = $_SESSION['nombre'] ?? 'Administrador General';
$tipoReporte = isset($_GET['tipo']) && $_GET['tipo'] === 'semanal' ? 'Semanal' : 'Diario';
$fechaGeneracion = date('d/m/Y h:i A');

// Obtener datos de inventario
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$productos = $pdo->query("SELECT p.*, c.nombre AS categoria_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY c.nombre ASC, p.nombre ASC")->fetchAll();

// Métricas del Inventario
$totalProductos = count($productos);
$totalStockUnidades = 0;
$valorTotalInventario = 0;
$productosStockBajo = 0;

foreach ($productos as $p) {
    $totalStockUnidades += intval($p['stock']);
    $valorTotalInventario += (floatval($p['precio']) * intval($p['stock']));
    if (intval($p['stock']) <= 5) {
        $productosStockBajo++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte <?php echo $tipoReporte; ?> de Inventario - Market Express</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* CONFIGURACIÓN EXACTA PARA TAMAÑO CARTA Y OCULTAR URL/LOCALHOST */
        @page {
            size: letter portrait; /* Tamaño Carta estándar (8.5 x 11 pulgadas) */
            margin: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: #0f172a;
            background: #ffffff;
            margin: 0;
            padding: 12mm 15mm 15mm 15mm;
            font-size: 11px;
            line-height: 1.4;
            box-sizing: border-box;
        }

        /* BARRA FLOTANTE EN PANTALLA (NO SE IMPRIME) */
        .no-print-bar {
            background: #1a1f30;
            color: #ffffff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: -12mm -15mm 15mm -15mm;
            border-bottom: 3px solid #decab0;
        }

        .no-print-bar span {
            font-size: 12px;
            color: #decab0;
            font-weight: 600;
        }

        .btn-print {
            background: #decab0;
            color: #1a1f30;
            border: none;
            padding: 8px 18px;
            font-weight: 700;
            font-size: 12px;
            border-radius: 6px;
            cursor: pointer;
        }

        /* ENCABEZADO DE PÁGINA */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #1a1f30;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .company-title {
            font-size: 22px;
            font-weight: 800;
            color: #1a1f30;
            margin: 0;
            line-height: 1;
        }

        .company-title span {
            color: #b38e53;
        }

        .company-subtitle {
            font-size: 10px;
            color: #64748b;
            margin: 4px 0 0 0;
            font-weight: 600;
        }

        .report-meta {
            text-align: right;
            font-size: 10px;
            color: #334155;
        }

        .report-badge {
            display: inline-block;
            border: 1px solid #1a1f30;
            color: #1a1f30;
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: 800;
            font-size: 10px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        /* TARJETAS DE RESUMEN PROPORCIONADAS */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 12px;
            text-align: center;
        }

        .summary-title {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 16px;
            font-weight: 800;
            color: #1a1f30;
            margin-top: 2px;
        }

        /* TABLA DE PRODUCTOS */
        .table-category-header {
            font-size: 11px;
            font-weight: 800;
            color: #1a1f30;
            border-bottom: 1.5px solid #1a1f30;
            padding-bottom: 4px;
            margin-top: 16px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }

        table.inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 12px;
        }

        table.inventory-table th {
            background: #ffffff;
            color: #475569;
            text-align: left;
            padding: 6px 8px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            border-bottom: 1px solid #94a3b8;
        }

        table.inventory-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        /* NIVELES DE STOCK EN TEXTO DIRECTO (SIN GASTO DE TINTA) */
        .status-out {
            color: #dc2626;
            font-weight: 800;
        }

        .status-low {
            color: #d97706;
            font-weight: 800;
        }

        .status-ok {
            color: #16a34a;
            font-weight: 600;
        }

        /* SECCIÓN DE FIRMA AMPLIADA Y SIN COPYRIGHT */
        .report-footer {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .signature-box {
            text-align: center;
            width: 230px;
            border-top: 1px solid #334155;
            padding-top: 6px;
            margin-top: 60px; /* Espacio amplio de 60px para el sello y la firma */
            color: #0f172a;
            font-size: 9.5px;
        }

        @media print {
            .no-print-bar { display: none !important; }
            body { 
                background: #ffffff; 
                padding: 10mm 15mm 15mm 15mm;
            }
        }
    </style>
</head>
<body>

    <!-- BARRA EN PANTALLA -->
    <div class="no-print-bar">
        <span>Reporte Oficial de Inventario - Market Express</span>
        <button class="btn-print" onclick="window.print()"> Imprimir / Guardar PDF</button>
    </div>

    <!-- ENCABEZADO -->
    <header class="report-header">
        <div>
            <h1 class="company-title">Market <span>Express</span></h1>
            <p class="company-subtitle">Sistema de Control e Inventario de Productos</p>
        </div>

        <div class="report-meta">
            <div class="report-badge">Reporte <?php echo $tipoReporte; ?></div>
            <div><strong>Fecha:</strong> <?php echo $fechaGeneracion; ?></div>
            <div><strong>Generado por:</strong> <?php echo htmlspecialchars($adminNombre); ?></div>
        </div>
    </header>

    <!-- MÉTRICAS -->
    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">Total Productos</div>
            <div class="summary-value"><?php echo $totalProductos; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Unidades en Stock</div>
            <div class="summary-value"><?php echo number_format($totalStockUnidades); ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Valor Estimado</div>
            <div class="summary-value">$<?php echo number_format($valorTotalInventario, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Stock Bajo / Agotado</div>
            <div class="summary-value" style="color: <?php echo $productosStockBajo > 0 ? '#dc2626' : '#16a34a'; ?>;"><?php echo $productosStockBajo; ?></div>
        </div>
    </section>

    <!-- TABLAS DE INVENTARIO -->
    <?php foreach ($categorias as $cat): 
        $prodsCategoria = array_filter($productos, function($item) use ($cat) {
            return $item['categoria_id'] == $cat['id'];
        });
        if (count($prodsCategoria) == 0) continue;
    ?>
        <div class="table-category-header">
            <span>Categoría: <?php echo htmlspecialchars($cat['nombre']); ?></span>
            <span style="font-weight: 600; font-size: 9.5px; color: #475569;"><?php echo count($prodsCategoria); ?> productos</span>
        </div>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 48%;">Nombre del Producto</th>
                    <th style="width: 14%;">Precio Unit.</th>
                    <th style="width: 15%;">Stock Actual</th>
                    <th style="width: 15%;">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prodsCategoria as $p): 
                    $subtotalValor = $p['precio'] * $p['stock'];
                    $stockClass = 'status-ok';
                    if ($p['stock'] <= 0) $stockClass = 'status-out';
                    elseif ($p['stock'] <= 5) $stockClass = 'status-low';
                ?>
                    <tr>
                        <td>#<?php echo $p['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                        <td>$<?php echo number_format($p['precio'], 2); ?></td>
                        <td class="<?php echo $stockClass; ?>"><?php echo $p['stock']; ?> unidades</td>
                        <td><strong>$<?php echo number_format($subtotalValor, 2); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <!-- PIE DE PÁGINA (SIN COPYRIGHT Y CON ESPACIO AMPLIO DE FIRMA) -->
    <footer class="report-footer">
        <div class="signature-box">
            Firma y Sello de conformidad<br>
            <strong><?php echo htmlspecialchars($adminNombre); ?></strong>
        </div>
    </footer>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 400);
        };
    </script>
</body>
</html>
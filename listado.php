<?php
session_start();
require_once 'conexion.php';

// Validar que el usuario sea Administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$nombreUsuario = $_SESSION['nombre'] ?? 'Administrador General';
$pageTitle = "Market Express - Estadísticas y Análisis de Ventas";

// ===================================================
// FILTRO DE PERÍODO (DIARIO, SEMANAL, GENERAL)
// ===================================================
$periodo = $_GET['periodo'] ?? 'general';
$whereCond = "WHERE estado = 'completado'";
$whereCondSimple = "WHERE 1=1";
$textoPeriodo = "Histórico General";

if ($periodo === 'diario') {
    $whereCond .= " AND DATE(fecha) = CURDATE()";
    $whereCondSimple .= " AND DATE(fecha) = CURDATE()";
    $textoPeriodo = "Diario (" . date('d/m/Y') . ")";
} elseif ($periodo === 'semanal') {
    $whereCond .= " AND fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $whereCondSimple .= " AND fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $textoPeriodo = "Últimos 7 Días";
}

// ===================================================
// CONSULTAS DE ESTADÍSTICAS Y MÉTRICAS
// ===================================================

// 1. Resumen General (KPIs)
$stmtResumen = $pdo->query("
    SELECT 
        COUNT(*) as total_pedidos,
        COALESCE(SUM(total), 0) as ingresos_totales,
        COALESCE(AVG(total), 0) as ticket_promedio
    FROM pedidos 
    {$whereCond}
");
$resumen = $stmtResumen->fetch(PDO::FETCH_ASSOC);

// 2. Top 5 Productos Más Vendidos
$stmtTopProd = $pdo->query("
    SELECT 
        p.nombre,
        SUM(dp.cantidad) as unidades_vendidas,
        SUM(dp.cantidad * dp.precio_unitario) as total_generado
    FROM detalle_pedidos dp
    JOIN productos p ON dp.producto_id = p.id
    JOIN pedidos ped ON dp.pedido_id = ped.id
    {$whereCond}
    GROUP BY p.id
    ORDER BY unidades_vendidas DESC
    LIMIT 5
");
$topProductos = $stmtTopProd->fetchAll(PDO::FETCH_ASSOC);

// 3. Ventas Totales por Categoría
$stmtCat = $pdo->query("
    SELECT 
        c.nombre as categoria,
        SUM(dp.cantidad * dp.precio_unitario) as total_categoria
    FROM detalle_pedidos dp
    JOIN productos p ON dp.producto_id = p.id
    JOIN categorias c ON p.categoria_id = c.id
    JOIN pedidos ped ON dp.pedido_id = ped.id
    {$whereCond}
    GROUP BY c.id
    ORDER BY total_categoria DESC
");
$ventasCategorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// 4. Tendencia de Ventas
$stmtTendencia = $pdo->query("
    SELECT 
        DATE(fecha) as fecha_dia,
        COUNT(*) as total_pedidos_dia,
        SUM(total) as total_recaudado
    FROM pedidos
    {$whereCond}
    GROUP BY DATE(fecha)
    ORDER BY fecha_dia ASC
    LIMIT 7
");
$tendenciaVentas = $stmtTendencia->fetchAll(PDO::FETCH_ASSOC);

// 5. Distribución por Estado de Pedidos
$stmtEstados = $pdo->query("
    SELECT 
        estado,
        COUNT(*) as cantidad
    FROM pedidos
    {$whereCondSimple}
    GROUP BY estado
");
$distribucionEstados = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

// Datos JSON para Chart.js
$labelsTopProd = json_encode(array_column($topProductos, 'nombre'));
$cantidadesTopProd = json_encode(array_column($topProductos, 'unidades_vendidas'));

$labelsCat = json_encode(array_column($ventasCategorias, 'categoria'));
$totalesCat = json_encode(array_column($ventasCategorias, 'total_categoria'));

$labelsFechas = json_encode(array_map(function($d) { return date('d/m', strtotime($d['fecha_dia'])); }, $tendenciaVentas));
$ingresosFechas = json_encode(array_column($tendenciaVentas, 'total_recaudado'));

$labelsEstados = json_encode(array_map(function($e) { return ucfirst($e['estado']); }, $distribucionEstados));
$cantidadesEstados = json_encode(array_column($distribucionEstados, 'cantidad'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/global.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body.dashboard-body {
            background-color: #121624;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: rgba(20, 25, 40, 0.95);
            border-bottom: 1px solid rgba(173, 147, 103, 0.3);
            gap: 8px;
        }

        .btn-back-header {
            background: rgba(222, 202, 176, 0.15);
            color: #decab0;
            border: 1px solid rgba(173, 147, 103, 0.35);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .btn-back-header:hover {
            background: #decab0;
            color: #121624;
            border-color: #decab0;
        }

        /* TÍTULO Y SUBTÍTULO CENTRADOS EN CABECERA */
        .brand-title-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex: 1;
        }

        .brand-title-center h1 {
            font-size: 1.05rem;
            margin: 0;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.1;
        }

        .brand-title-center h1 span {
            color: #decab0;
        }

        .brand-title-center .header-subtitle {
            font-size: 0.68rem;
            color: #a0aec0;
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            margin-top: 2px;
        }

        .header-spacer {
            width: 75px;
            flex-shrink: 0;
        }

        .container-analytics {
            max-width: 1200px;
            margin: 16px auto;
            padding: 0 12px;
            box-sizing: border-box;
        }

        .page-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .period-filter-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(30, 38, 56, 0.85);
            padding: 6px 12px;
            border-radius: 10px;
            border: 1px solid rgba(173, 147, 103, 0.3);
        }

        .period-select {
            background: transparent;
            color: #decab0;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .period-select option {
            background: #121624;
            color: #ffffff;
        }

        .btn-export {
            background: linear-gradient(135deg, #decab0 0%, #b89f7d 100%);
            color: #121624;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(222, 202, 176, 0.25);
            transition: transform 0.2s;
        }

        .btn-export:hover {
            transform: translateY(-2px);
        }

        /* GRID DE KPIS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .kpi-card {
            background: rgba(30, 38, 56, 0.85);
            border: 1px solid rgba(173, 147, 103, 0.25);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.3);
        }

        .kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(222, 202, 176, 0.15);
            color: #decab0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .kpi-icon i {
            color: #decab0 !important;
        }

        .kpi-info h4 {
            margin: 0;
            font-size: 0.7rem;
            color: #a0aec0;
            text-transform: uppercase;
            font-weight: 600;
        }

        .kpi-info .val {
            margin: 2px 0 0 0;
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
        }

        /* GRID DE GRÁFICAS (2X2 EN PC) */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (min-width: 900px) {
            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .chart-card {
            background: rgba(30, 38, 56, 0.85);
            border: 1px solid rgba(173, 147, 103, 0.25);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }

        .chart-card h3 {
            font-size: 0.88rem;
            color: #decab0;
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(173, 147, 103, 0.2);
            padding-bottom: 8px;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 240px;
        }

        /* CONFIGURACIÓN PARA IMPRESIÓN LIMPIA */
        #print-report-area {
            display: none;
        }

        @page {
            size: auto;
            margin: 0mm;
        }

        @media print {
            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: Arial, sans-serif !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .app-header, .page-header-actions, .kpi-grid, .charts-grid, footer {
                display: none !important;
            }

            #print-report-area {
                display: block !important;
                padding: 15mm 18mm !important;
                color: #000000;
            }

            .print-header {
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 8px;
                margin-bottom: 12px;
            }

            .print-header h1 {
                font-size: 1.4rem;
                margin: 0;
                color: #000;
            }

            .print-section {
                margin-bottom: 16px;
            }

            .print-section h3 {
                font-size: 0.95rem;
                border-bottom: 1px solid #666;
                padding-bottom: 4px;
                margin-bottom: 6px;
                color: #000;
            }

            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 4px;
                font-size: 0.82rem;
            }

            .print-table th, .print-table td {
                border: 1px solid #333;
                padding: 5px 7px;
                text-align: left;
            }

            .print-table th {
                background: #f0f0f0;
                font-weight: bold;
            }
        }

        @media (max-width: 600px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .chart-wrapper {
                height: 210px;
            }
            .header-spacer {
                display: none;
            }
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- BARRA SUPERIOR CENTRADA -->
    <header class="app-header">
        <a href="market_express.php" class="btn-back-header" title="Volver al inicio / caja">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>

        <div class="brand-title-center">
            <h1>Market <span>Express</span></h1>
            <span class="header-subtitle">
                <i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($nombreUsuario); ?>
            </span>
        </div>

        <div class="header-spacer"></div>
    </header>

    <main class="container-analytics">

        <!-- CABECERA DE LA PÁGINA -->
        <div class="page-header-actions">
            <div>
                <h2 style="margin: 0; font-size: 1.25rem; color: #ffffff;"><i class="fa-solid fa-chart-line" style="color: #decab0;"></i> Estadísticas de Ventas</h2>
                <p style="margin: 2px 0 0 0; font-size: 0.75rem; color: #a0aec0;">Rendimiento comercial del sistema (<?php echo $textoPeriodo; ?>).</p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div class="period-filter-box">
                    <i class="fa-solid fa-calendar-days" style="color: #decab0; font-size: 0.85rem;"></i>
                    <select class="period-select" onchange="location = this.value;">
                        <option value="listado.php?periodo=general" <?php echo $periodo === 'general' ? 'selected' : ''; ?>>Histórico General</option>
                        <option value="listado.php?periodo=diario" <?php echo $periodo === 'diario' ? 'selected' : ''; ?>>Reporte Diario (Hoy)</option>
                        <option value="listado.php?periodo=semanal" <?php echo $periodo === 'semanal' ? 'selected' : ''; ?>>Reporte Semanal (7 días)</option>
                    </select>
                </div>

                <button onclick="imprimirReporteLimpio()" class="btn-export">
                    <i class="fa-solid fa-file-pdf"></i> Descargar Reporte Documento
                </button>
            </div>
        </div>

        <!-- TARJETAS KPIS -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="kpi-info">
                    <h4>Ingresos Totales</h4>
                    <div class="val">$<?php echo number_format($resumen['ingresos_totales'], 2); ?></div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-basket-shopping"></i></div>
                <div class="kpi-info">
                    <h4>Ventas Completadas</h4>
                    <div class="val"><?php echo $resumen['total_pedidos']; ?></div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-receipt"></i></div>
                <div class="kpi-info">
                    <h4>Ticket Promedio</h4>
                    <div class="val">$<?php echo number_format($resumen['ticket_promedio'], 2); ?></div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-star"></i></div>
                <div class="kpi-info">
                    <h4>Producto Estrella</h4>
                    <div class="val" style="font-size: 0.95rem; font-weight: 700; color: #decab0;">
                        <?php echo !empty($topProductos) ? htmlspecialchars($topProductos[0]['nombre']) : 'Sin ventas'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRÁFICAS DEL DASHBOARD (MATRIZ 2X2 EN PC) -->
        <div class="charts-grid">
            
            <!-- GRÁFICA 1: BARRAS HORIZONTALES -->
            <div class="chart-card">
                <h3><i class="fa-solid fa-fire"></i> Top 5 Productos Más Vendidos</h3>
                <div class="chart-wrapper">
                    <canvas id="chartTopProductos"></canvas>
                </div>
            </div>

            <!-- GRÁFICA 2: DONA -->
            <div class="chart-card">
                <h3><i class="fa-solid fa-chart-pie"></i> Participación por Categoría</h3>
                <div class="chart-wrapper">
                    <canvas id="chartCategorias"></canvas>
                </div>
            </div>

            <!-- GRÁFICA 3: LÍNEA DE TENDENCIA DIARIA -->
            <div class="chart-card">
                <h3><i class="fa-solid fa-arrow-trend-up"></i> Tendencia de Recaudación</h3>
                <div class="chart-wrapper">
                    <canvas id="chartTendencia"></canvas>
                </div>
            </div>

            <!-- GRÁFICA 4: POLAR AREA DE ESTADOS DE PEDIDOS -->
            <div class="chart-card">
                <h3><i class="fa-solid fa-list-check"></i> Estado de Pedidos en Sistema</h3>
                <div class="chart-wrapper">
                    <canvas id="chartEstados"></canvas>
                </div>
            </div>

        </div>

    </main>

    <!-- DOCUMENTO EN TEXTO PARA IMPRESIÓN / DESCARGA PDF -->
    <div id="print-report-area">
        <div class="print-header">
            <h1>MARKET EXPRESS - REPORTE OFICIAL DE VENTAS</h1>
            <p style="margin: 4px 0 0 0; font-size: 0.85rem;">
                <strong>Período:</strong> <?php echo $textoPeriodo; ?> | 
                <strong>Generado por:</strong> <?php echo htmlspecialchars($nombreUsuario); ?> | 
                <strong>Fecha:</strong> <?php echo date('d/m/Y h:i A'); ?>
            </p>
        </div>

        <div class="print-section">
            <h3>1. RESUMEN EJECUTIVO (MÉTRICAS CLAVE)</h3>
            <table class="print-table">
                <tr>
                    <th>Ingresos Totales Acumulados</th>
                    <td>$<?php echo number_format($resumen['ingresos_totales'], 2); ?></td>
                </tr>
                <tr>
                    <th>Total de Pedidos Procesados</th>
                    <td><?php echo $resumen['total_pedidos']; ?> ventas</td>
                </tr>
                <tr>
                    <th>Promedio de Compra por Ticket</th>
                    <td>$<?php echo number_format($resumen['ticket_promedio'], 2); ?></td>
                </tr>
                <tr>
                    <th>Producto de Mayor Demanda</th>
                    <td><?php echo !empty($topProductos) ? htmlspecialchars($topProductos[0]['nombre']) : 'N/A'; ?></td>
                </tr>
            </table>
        </div>

        <div class="print-section">
            <h3>2. DESGLOSE EN TEXTO: TOP 5 PRODUCTOS MÁS VENDIDOS</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre del Producto</th>
                        <th>Unidades Vendidas</th>
                        <th>Total Generado ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($topProductos)): ?>
                        <?php foreach ($topProductos as $idx => $tp): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><?php echo htmlspecialchars($tp['nombre']); ?></td>
                                <td><?php echo $tp['unidades_vendidas']; ?> uds.</td>
                                <td>$<?php echo number_format($tp['total_generado'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No hay datos registrados en este período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="print-section">
            <h3>3. DESGLOSE EN TEXTO: VENTAS POR CATEGORÍA</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Total Recaudado ($)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ventasCategorias)): ?>
                        <?php foreach ($ventasCategorias as $vc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vc['categoria']); ?></td>
                                <td>$<?php echo number_format($vc['total_categoria'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No hay datos registrados en este período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="print-section">
            <h3>4. HISTORIAL RECIENTE DE TENDENCIA DE INGRESOS</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Pedidos Realizados</th>
                        <th>Recaudación Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tendenciaVentas)): ?>
                        <?php foreach ($tendenciaVentas as $tv): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($tv['fecha_dia'])); ?></td>
                                <td><?php echo $tv['total_pedidos_dia']; ?> pedidos</td>
                                <td>$<?php echo number_format($tv['total_recaudado'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No hay datos de tendencia en este período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 25px; font-size: 0.75rem; text-align: center; border-top: 1px solid #aaa; padding-top: 8px;">
            Documento de control interno generado automáticamente por el sistema Market Express.
        </div>
    </div>

    <footer style="text-align: center; font-size: 0.68rem; color: #a0aec0; padding: 16px 0;">
        © 2026 Market express - todos los derechos son reservados a estudiantes de 2B del Instituto Nacional de Apopa (INA)
    </footer>

    <!-- INICIALIZACIÓN DE GRÁFICAS -->
    <script>
        function id(e) { return document.getElementById(e); }

        function imprimirReporteLimpio() {
            const tituloOriginal = document.title;
            document.title = "";
            window.print();
            document.title = tituloOriginal;
        }

        Chart.defaults.color = '#a0aec0';
        Chart.defaults.font.family = 'Poppins';

        // Data JSON desde PHP
        const labelsTopProd = <?php echo $labelsTopProd; ?>;
        const cantidadesTopProd = <?php echo $cantidadesTopProd; ?>;

        const labelsCat = <?php echo $labelsCat; ?>;
        const totalesCat = <?php echo $totalesCat; ?>;

        const labelsFechas = <?php echo $labelsFechas; ?>;
        const ingresosFechas = <?php echo $ingresosFechas; ?>;

        const labelsEstados = <?php echo $labelsEstados; ?>;
        const cantidadesEstados = <?php echo $cantidadesEstados; ?>;

        // 1. Gráfica Top Productos (Barras Horizontales)
        new Chart(id('chartTopProductos'), {
            type: 'bar',
            data: {
                labels: labelsTopProd,
                datasets: [{
                    label: 'Unidades',
                    data: cantidadesTopProd,
                    backgroundColor: 'rgba(222, 202, 176, 0.8)',
                    borderColor: '#decab0',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { grid: { display: false } }
                }
            }
        });

        // 2. Gráfica Categorías (Dona)
        new Chart(id('chartCategorias'), {
            type: 'doughnut',
            data: {
                labels: labelsCat,
                datasets: [{
                    data: totalesCat,
                    backgroundColor: ['#decab0', '#2563eb', '#22c55e', '#eab308', '#ec4899', '#8b5cf6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }
            }
        });

        // 3. Gráfica Tendencia de Recaudación (Líneas Suaves)
        new Chart(id('chartTendencia'), {
            type: 'line',
            data: {
                labels: labelsFechas,
                datasets: [{
                    label: 'Ingresos ($)',
                    data: ingresosFechas,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#22c55e',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });

        // 4. Gráfica Estado de Pedidos (Polar Area)
        new Chart(id('chartEstados'), {
            type: 'polarArea',
            data: {
                labels: labelsEstados,
                datasets: [{
                    data: cantidadesEstados,
                    backgroundColor: ['rgba(34, 197, 94, 0.7)', 'rgba(234, 179, 8, 0.7)', 'rgba(239, 68, 68, 0.7)']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                scales: { r: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { display: false } } }
            }
        });
    </script>
</body>
</html>
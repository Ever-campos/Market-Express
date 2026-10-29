<?php
session_start();
require_once 'conexion.php';

// Validar que exista una sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$pedidoId = intval($_GET['id'] ?? 0);

if ($pedidoId <= 0) {
    echo "<h2 style='text-align:center; font-family:sans-serif; color:#e53e3e; margin-top:50px;'>⚠️ ID de Pedido no válido.</h2>";
    exit;
}

// Consultar datos principales del pedido
$stmt = $pdo->prepare("
    SELECT p.*, 
           COALESCE(u.nombre_completo, 'Cliente Presencial') as cliente_nombre
    FROM pedidos p
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    echo "<h2 style='text-align:center; font-family:sans-serif; color:#e53e3e; margin-top:50px;'>⚠️ Pedido #$pedidoId no encontrado.</h2>";
    exit;
}

// Consultar detalle de ítems comprados
$stmtDet = $pdo->prepare("
    SELECT dp.*, pr.nombre as producto_nombre
    FROM detalle_pedidos dp
    JOIN productos pr ON dp.producto_id = pr.id
    WHERE dp.pedido_id = ?
");
$stmtDet->execute([$pedidoId]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Cálculos del ticket (IVA 13%)
$total = floatval($pedido['total']);
$baseImponible = $total / 1.13;
$impuestoIVA = $total - $baseImponible;

$numeroFactura = "ME-" . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT);
$fechaFormateada = date('d/m/Y H:i:s', strtotime($pedido['fecha']));
$nombreAdmin = $_SESSION['nombre'] ?? 'Administrador General';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Compra - <?php echo $numeroFactura; ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f3f4f6;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 20px 10px;
            color: #000000;
        }

        /* FORMATO ANCHO TICKET TÉRMICO (80MM / ~300PX) */
        .ticket-box {
            width: 300px;
            background: #ffffff;
            padding: 18px 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            font-size: 11px;
            line-height: 1.3;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .brand-header {
            margin-bottom: 8px;
            text-align: center;
        }

        .brand-header h1 {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .brand-header p {
            font-size: 10px;
        }

        .divider {
            border-bottom: 1px dashed #000000;
            margin: 8px 0;
        }

        .info-section p {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
        }

        /* TABLA DE PRODUCTOS */
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
            font-size: 10px;
        }

        .ticket-table th {
            border-bottom: 1px dashed #000000;
            padding-bottom: 4px;
            text-align: left;
            font-weight: bold;
        }

        .ticket-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .col-uds { width: 12%; }
        .col-desc { width: 48%; }
        .col-iva { width: 16%; text-align: center; }
        .col-imp { width: 24%; text-align: right; }

        /* SECCIÓN DE TOTALES Y CAMBIO */
        .totals-section {
            margin-top: 6px;
            font-size: 11px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }

        .totals-row.grand-total {
            font-size: 15px;
            font-weight: 900;
            border-top: 1px dashed #000000;
            border-bottom: 1px dashed #000000;
            padding: 6px 0;
            margin: 6px 0;
        }

        .footer-note {
            margin-top: 12px;
            text-align: center;
            font-size: 9px;
            line-height: 1.2;
        }

        /* BOTONES DE ACCIÓN (SÓLO VISIBLES EN PANTALLA) */
        .no-print-actions {
            position: fixed;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
        }

        .btn-action {
            background: #1a1f30;
            color: #decab0;
            border: 1px solid #decab0;
            padding: 8px 14px;
            border-radius: 8px;
            font-family: sans-serif;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .btn-action:hover {
            background: #decab0;
            color: #1a1f30;
        }

        /* CONFIGURACIÓN ESTRICTA DE IMPRESIÓN PARA TICKETERA */
        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print-actions {
                display: none !important;
            }

            .ticket-box {
                box-shadow: none !important;
                width: 100% !important;
                padding: 0 !important;
            }

            @page {
                size: 80mm auto;
                margin: 0mm;
            }
        }
    </style>
</head>
<body>

    <!-- BOTONES MÓVIL / PC -->
    <div class="no-print-actions">
        <button class="btn-action" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir Ticket</button>
        <button class="btn-action" onclick="window.close()">Cerrar</button>
    </div>

    <!-- TICKET DE COMPRA -->
    <div class="ticket-box">
        
        <!-- ENCABEZADO Y DATOS FISCALES -->
        <div class="brand-header">
            <h1>MARKET EXPRESS</h1>
            <p>AHORRA TIEMPO, VIVE MEJOR</p>
            <p>TEL. 2222-0000 | Apopa, San Salvador</p>
            <p class="bold">NIF/NIT: 0614-261026-101-0</p>
        </div>

        <div class="divider"></div>

        <!-- DATOS DE LA FACTURA Y FECHA -->
        <div class="info-section">
            <p><span class="bold">FACTURA SIMPLIFICADA:</span> <span><?php echo $numeroFactura; ?></span></p>
            <p><span>FECHA: <?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></span> <span>HORA: <?php echo date('H:i:s', strtotime($pedido['fecha'])); ?></span></p>
            <p><span>CAJERO: <?php echo htmlspecialchars($nombreAdmin); ?></span></p>
            <p><span class="bold">CLIENTE:</span> <span><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></span></p>
        </div>

        <div class="divider"></div>

        <!-- TABLA DE PRODUCTOS -->
        <table class="ticket-table">
            <thead>
                <tr>
                    <th class="col-uds">UDS</th>
                    <th class="col-desc">DESCRIPCION</th>
                    <th class="col-iva">IVA</th>
                    <th class="col-imp">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($detalles)): ?>
                    <?php foreach ($detalles as $item): 
                        $cantidad = intval($item['cantidad']);
                        $precioUnitario = floatval($item['precio_unitario']);
                        $subtotalItem = $cantidad * $precioUnitario;
                    ?>
                        <tr>
                            <td class="col-uds"><?php echo $cantidad; ?></td>
                            <td class="col-desc uppercase"><?php echo htmlspecialchars($item['producto_nombre']); ?></td>
                            <td class="col-iva">13%</td>
                            <td class="col-imp">$<?php echo number_format($subtotalItem, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">Venta General Presencial</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- RESUMEN FINANCIERO Y TOTALES -->
        <div class="totals-section">
            <div class="totals-row">
                <span>BASE IMPONIBLE:</span>
                <span>$<?php echo number_format($baseImponible, 2); ?></span>
            </div>
            <div class="totals-row">
                <span>IMPUESTO IVA (13%):</span>
                <span>$<?php echo number_format($impuestoIVA, 2); ?></span>
            </div>

            <div class="totals-row grand-total">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($total, 2); ?></span>
            </div>

            <div class="totals-row">
                <span>ENTREGADO (EFECTIVO):</span>
                <span>$<?php echo number_format($total, 2); ?></span>
            </div>
            <div class="totals-row">
                <span>CAMBIO:</span>
                <span>$0.00</span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- PIE DE TICKET Y AGRADECIMIENTO -->
        <div class="footer-note">
            <p class="bold">***********************************</p>
            <p class="bold">*** ¡GRACIAS POR SU COMPRA! ***</p>
            <p>Sistema Market Express</p>
            <p>Apopa, El Salvador</p>
            <p class="bold">***********************************</p>
        </div>

    </div>

    <!-- SCRIPT DE AUTOIMPRESIÓN -->
    <script>
        // Abrir la ventana de impresión automáticamente al cargar
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
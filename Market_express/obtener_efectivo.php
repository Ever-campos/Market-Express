<?php
header('Content-Type: application/json');

$archivo = 'ultimo_efectivo.json';

if (file_exists($archivo)) {
    echo file_get_contents($archivo);
} else {
    echo json_encode(['monto' => 0.00, 'timestamp' => 0]);
}
?>
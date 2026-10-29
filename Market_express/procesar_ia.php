<?php
// Permitir conexiones desde cualquier dispositivo en la red local
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 1. Recibir imagen en Base64 enviada desde el celular
$input = json_decode(file_get_contents('php://input'), true);
$imagenBase64 = $input['imagen'] ?? '';

if (empty($imagenBase64)) {
    echo json_encode(['exito' => false, 'error' => 'No se recibió ninguna imagen']);
    exit;
}

// Limpiar la cabecera data:image/jpeg;base64,
$data = explode(',', $imagenBase64);
$imagenLimpia = isset($data[1]) ? $data[1] : $data[0];

// 2. Clave de API de Gemini configurada
$apiKey = "AQ.Ab8RN6KMgeR6chIMhVVzUFXJ3gAcsiKmgHuZzzMu2_oNzJlY4g"; 

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

// Prompt especializado para detectar efectivo en USD
$payload = [
    "contents" => [
        [
            "parts" => [
                [
                    "text" => "Analiza esta imagen con precisión. Identifica todas las monedas y billetes en Dólares Estadounidenses (USD). Suma todo el dinero detectado. Responde ÚNICAMENTE con la cifra numérica del total en formato decimal (por ejemplo: 1.00, 5.25, 20.00). No agregues texto, símbolos de moneda ni explicaciones, SOLO EL NÚMERO."
                ],
                [
                    "inline_data" => [
                        "mime_type" => "image/jpeg",
                        "data" => $imagenLimpia
                    ]
                ]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['exito' => false, 'error' => 'Error al consultar la API de Gemini']);
    exit;
}

$resultado = json_decode($response, true);
$textoRespuesta = $resultado['candidates'][0]['content']['parts'][0]['text'] ?? '0.00';

// Extraer únicamente el valor numérico
$montoDetectado = floatval(preg_replace('/[^0-9.]/', '', $textoRespuesta));

// 3. Guardar el monto detectado en un archivo JSON para que market_express.php lo lea en tiempo real
$datosSincronizacion = [
    'monto' => $montoDetectado,
    'timestamp' => time()
];
file_put_contents('ultimo_efectivo.json', json_encode($datosSincronizacion));

echo json_encode([
    'exito' => true,
    'total' => $montoDetectado
]);
?>
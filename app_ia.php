<?php
// app_ia.php - App Móvil Market Express Asistente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Market Express - Asistencia Móvil</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        :root {
            --bg-dark: #121624;
            --card-bg: rgba(30, 38, 56, 0.9);
            --gold-accent: #decab0;
            --green-accent: #22c55e;
            --text-light: #ffffff;
            --text-muted: #a0aec0;
        }

        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            margin: 0;
            padding: 14px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ENCABEZADO CON LOGO Y TÍTULO CENTRADO */
        .brand-header {
            width: 100%;
            max-width: 520px;
            background: linear-gradient(135deg, rgba(35, 43, 67, 0.95), rgba(18, 22, 36, 0.95));
            border: 1px solid rgba(222, 202, 176, 0.35);
            border-radius: 18px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .brand-logo {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gold-accent);
            box-shadow: 0 2px 10px rgba(222, 202, 176, 0.3);
            flex-shrink: 0;
        }

        .brand-info {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .brand-title { 
            font-size: 1.1rem; 
            font-weight: 800; 
            margin: 0; 
            color: #ffffff; 
            line-height: 1.2;
        }

        .brand-title span { color: var(--gold-accent); }

        .brand-subtitle {
            font-size: 0.68rem; 
            color: var(--text-muted); 
            font-weight: 600;
            margin-top: 2px;
        }

        .panel-container {
            width: 100%; 
            max-width: 520px; 
            background: var(--card-bg);
            border: 1px solid rgba(222, 202, 176, 0.25); 
            border-radius: 18px;
            padding: 16px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        /* ÁREA DE BOTONES */
        .buttons-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }

        .btn-action-cam {
            width: 100%;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff; 
            border: none; 
            padding: 12px; 
            border-radius: 12px;
            font-size: 0.85rem; 
            font-weight: 700; 
            cursor: pointer;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px;
            transition: transform 0.15s ease;
            text-align: center;
        }

        .btn-action-cam:active {
            transform: scale(0.97);
        }

        .btn-action-live {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        /* ÁREA DEL ESCÁNER QR */
        .camera-viewport {
            width: 100%;
            min-height: 320px;
            max-height: 420px;
            background: #090c14;
            border: 2px dashed rgba(222, 202, 176, 0.4);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px;
            margin-bottom: 14px;
            position: relative;
            overflow: hidden;
        }

        #qr-live-stream-box {
            width: 100% !important;
            height: 100% !important;
            border-radius: 12px;
            overflow: hidden;
        }

        #qr-live-stream-box video {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            background: #000;
        }

        .img-preview {
            max-width: 100%;
            max-height: 300px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 10px;
            display: none;
        }

        /* MENSAJE FLOTANTE DE ESTADO */
        .qr-status-badge {
            font-size: 0.75rem; 
            color: var(--text-muted); 
            text-align: center; 
            position: absolute; 
            bottom: 10px; 
            margin: 0; 
            background: rgba(10, 14, 24, 0.88); 
            padding: 6px 14px; 
            border-radius: 20px;
            border: 1px solid rgba(222, 202, 176, 0.2);
            transition: all 0.3s ease;
        }

        .status-success {
            color: #4ade80 !important;
            border-color: #22c55e !important;
            font-weight: 700;
        }

        /* VISUALIZADOR DEL ÚLTIMO CÓDIGO LEÍDO */
        .code-display-box {
            background: rgba(18, 22, 36, 0.7); 
            padding: 12px; 
            border-radius: 12px; 
            text-align: center; 
            border: 1px solid rgba(222, 202, 176, 0.2);
        }

        .code-display-title {
            font-size: 0.7rem; 
            color: var(--text-muted); 
            text-transform: uppercase;
            font-weight: 600;
        }

        .code-display-val {
            font-size: 1.25rem; 
            font-weight: 800; 
            color: var(--gold-accent); 
            margin-top: 2px;
            word-break: break-all;
        }

        input[type="file"] { display: none; }
    </style>
</head>
<body>

    <!-- ENCABEZADO CON LOGO Y NOMBRE -->
    <header class="brand-header">
        <img src="assets/logo.jpg" onerror="this.onerror=null; this.src='assets/logo.png';" alt="Market Express" class="brand-logo">
        <div class="brand-info">
            <h1 class="brand-title">Market Express: <span>Asistencia Móvil</span></h1>
            <span class="brand-subtitle"><i class="fa-solid fa-qrcode" style="color: var(--gold-accent);"></i> ESCÁNER MÓVIL DE PRODUCTOS EN TIEMPO REAL</span>
        </div>
    </header>

    <main class="panel-container">

        <!-- BOTONES DE ACCIÓN -->
        <div class="buttons-grid">
            <label for="input-cam-qr-file" class="btn-action-cam">
                <i class="fa-solid fa-camera"></i> Escanear foto
            </label>
            <input type="file" id="input-cam-qr-file" accept="image/*" capture="environment" onchange="procesarFotoQR(this)">

            <button type="button" class="btn-action-cam btn-action-live" id="btn-toggle-live-qr" onclick="toggleCamaraLiveQR()">
                <i class="fa-solid fa-video"></i> Cámara en vivo
            </button>
        </div>

        <!-- VISOR DE CÁMARA / IMAGEN -->
        <div class="camera-viewport">
            <img id="preview-qr-img" class="img-preview" src="" alt="Vista Previa Escáner">
            <div id="qr-live-stream-box"></div>
            
            <p id="qr-status-text" class="qr-status-badge">
                Selecciona una opción para escanear.
            </p>
        </div>

        <!-- MUESTRA DEL ÚLTIMO CÓDIGO LEÍDO -->
        <div class="code-display-box">
            <span class="code-display-title">Último Código Leído:</span>
            <div id="last-code-display" class="code-display-val">Ninguno</div>
        </div>

    </main>

    <script>
        function id(e) { return document.getElementById(e); }

        let html5QrScannerLive = null;
        let camaraActiva = false;
        let statusTimer = null;

        // Mostrar aviso flotante y hacerlo desaparecer a los 3 segundos
        function mostrarEstadoTemporal(texto, esExito = false) {
            const statusElem = id('qr-status-text');
            statusElem.innerText = texto;
            
            if (esExito) {
                statusElem.classList.add('status-success');
            } else {
                statusElem.classList.remove('status-success');
            }

            if (statusTimer) clearTimeout(statusTimer);

            statusTimer = setTimeout(() => {
                statusElem.innerText = "Listo para el siguiente producto.";
                statusElem.classList.remove('status-success');
            }, 3000);
        }

        // 1. ESCANEAR DESDE ARCHIVO DE FOTO / CÁMARA RÁPIDA
        function procesarFotoQR(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const previewImg = id('preview-qr-img');
                
                previewImg.src = URL.createObjectURL(file);
                previewImg.style.display = 'block';
                id('qr-live-stream-box').style.display = 'none';

                id('qr-status-text').innerText = "Procesando imagen...";

                const html5QrCode = new Html5Qrcode("qr-live-stream-box");
                html5QrCode.scanFile(file, true)
                    .then(decodedText => {
                        id('last-code-display').innerText = decodedText;
                        mostrarEstadoTemporal("¡Código leído y enviado a caja!", true);
                        reproducirBeep();
                        enviarCodigoServidor(decodedText);
                    })
                    .catch(() => {
                        mostrarEstadoTemporal("No se detectó un código nítido. Enfoca más cerca.");
                    });
            }
        }

        // 2. TOGGLE CÁMARA EN VIVO
        function toggleCamaraLiveQR() {
            if (camaraActiva) {
                detenerCamaraLiveQR();
            } else {
                iniciarCamaraLiveQR();
            }
        }

        function iniciarCamaraLiveQR() {
            id('preview-qr-img').style.display = 'none';
            id('qr-live-stream-box').style.display = 'block';
            id('qr-status-text').innerText = "Iniciando cámara en vivo...";
            
            html5QrScannerLive = new Html5Qrcode("qr-live-stream-box");
            const config = { fps: 15, qrbox: { width: 250, height: 250 } };

            html5QrScannerLive.start({ facingMode: "environment" }, config, (decodedText) => {
                id('last-code-display').innerText = decodedText;
                mostrarEstadoTemporal("¡Código leído y enviado a caja!", true);
                reproducirBeep();
                enviarCodigoServidor(decodedText);
            }, (error) => {})
            .then(() => {
                camaraActiva = true;
                const btn = id('btn-toggle-live-qr');
                btn.innerHTML = '<i class="fa-solid fa-stop"></i> Detener Cámara';
                btn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                id('qr-status-text').innerText = "Apunta al código del producto.";
            })
            .catch(err => {
                alert("No se pudo iniciar la cámara en vivo. Intenta con 'Escanear foto'.");
            });
        }

        function detenerCamaraLiveQR() {
            if (html5QrScannerLive && camaraActiva) {
                html5QrScannerLive.stop().then(() => {
                    camaraActiva = false;
                    const btn = id('btn-toggle-live-qr');
                    btn.innerHTML = '<i class="fa-solid fa-video"></i> Cámara en vivo';
                    btn.style.background = 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)';
                    id('qr-status-text').innerText = "Cámara detenida.";
                });
            }
        }

        // ENVÍO DE CÓDIGO AL SERVIDOR/LAPTOP
        function enviarCodigoServidor(codigo) {
            const formData = new FormData();
            formData.append('action', 'registrar_qr');
            formData.append('codigo', codigo);

            fetch('api_ia.php', { method: 'POST', body: formData }).catch(() => {});
        }

        // BEEP AUDIBLE AL LEER CÓDIGO
        function reproducirBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                osc.type = 'sine';
                osc.frequency.value = 1100;
                osc.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch(e) {}
        }
    </script>
</body>
</html>
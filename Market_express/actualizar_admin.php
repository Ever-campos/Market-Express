<?php
require_once 'conexion.php';

// Contraseña deseada
$passwordPlana = 'n3wm4rketAdm1n';

// Generación del hash real en tu servidor
$hashReal = password_hash($passwordPlana, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE administradores SET password = :hash WHERE correo = 'admin'");
    $stmt->execute([':hash' => $hashReal]);

    if ($stmt->rowCount() > 0) {
        echo "<h2 style='color: green; font-family: sans-serif;'>¡Contraseña del administrador actualizada con éxito!</h2>";
        echo "<p style='font-family: sans-serif;'>Ahora puedes ir a <a href='login.php'>login.php</a> e ingresar con:</p>";
        echo "<ul style='font-family: sans-serif;'><li><strong>Correo:</strong> admin</li><li><strong>Contraseña:</strong> n3wm4rketAdm1n</li></ul>";
    } else {
        echo "<h2 style='color: orange; font-family: sans-serif;'>No se encontró el usuario 'admin' o la contraseña ya era la misma.</h2>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color: red; font-family: sans-serif;'>Error de base de datos: " . $e->getMessage() . "</h2>";
}
?>
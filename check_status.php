<?php
require_once __DIR__ . '/../../interface/globals.php';
require_once '../../vendor/autoload.php'; // Incluir GuzzleHTTP

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Configuración de WaSenderAPI
$apiKey = 'd492646b0f496fe95981ba074da1e212d1238a58c74fad785971fe2cc39ab80a';
$baseUrl = 'https://www.wasenderapi.com/api';
$logFile = '/var/www/html/origen.ar/hcd/modules/sms_email_reminder/check_status.log';

// Mapa de códigos de estado a valores descriptivos
$statusMap = [
    0 => 'ERROR',
    1 => 'PENDIENTE',
    2 => 'ENVIADO',
    3 => 'ENTREGADO',
    4 => 'LEIDO',
    5 => 'VISTO'
];

// Inicializar variables
$msgId = trim($_POST['msgId'] ?? '');
$result = '';
$errors = [];

// Validar entrada
if (empty($msgId)) {
    $errors[] = 'El ID del mensaje es obligatorio.';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: ID del mensaje vacío\n", FILE_APPEND);
}

if (empty($errors)) {
    $client = new Client();

    // Solicitud a /api/messages/{msgId}/info
    try {
        $response = $client->get("$baseUrl/messages/$msgId/info", [
            'headers' => [
                'Authorization' => "Bearer $apiKey",
                'Accept' => 'application/json',
            ]
        ]);
        $status = json_decode($response->getBody(), true);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Respuesta: " . $response->getBody() . "\n", FILE_APPEND);

        if ($status['success']) {
            $statusCode = $status['data']['status'] ?? -1;
            $statusText = isset($statusMap[$statusCode]) ? $statusMap[$statusCode] : 'UNKNOWN';
            $remoteJid = $status['data']['remoteJid'] ?? 'Desconocido';
            $timestamp = $status['data']['messageTimestamp'] ?? '';
            $formattedDate = $timestamp ? date('Y-m-d H:i:s', (int)$timestamp) : 'No disponible';

            // Preparar resultado para mostrar
            $result = "<h3>Estado del mensaje $msgId:</h3>";
            $result .= "<p>ID: {$status['data']['msgId']}<br>";
            $result .= "Destinatario: $remoteJid<br>";
            $result .= "Estado: $statusText<br>";
            $result .= "Fecha: $formattedDate</p>";

            // Actualizar notification_log
            try {
                $phone = preg_replace('/[^0-9]/', '', $remoteJid);
                $phone = substr($phone, -10);
                $sql = "UPDATE notification_log SET status = ? WHERE msg_id = ? AND patient_info LIKE ?";
                sqlStatement($sql, [$statusText, $msgId, "%|||$phone|||%"]);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - notification_log actualizado: msg_id=$msgId, status=$statusText, phone=$phone\n", FILE_APPEND);
            } catch (Exception $e) {
                $errors[] = "Error al actualizar notification_log: " . $e->getMessage();
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error SQL: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        } else {
            $result = "No se encontró información para el mensaje $msgId.";
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - No se encontró información para msg_id=$msgId\n", FILE_APPEND);
        }
    } catch (RequestException $e) {
        $errors[] = "Error en la solicitud a WaSenderAPI: " . $e->getMessage();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
        if ($e->hasResponse()) {
            $errorResponse = $e->getResponse()->getBody()->getContents();
            $errors[] = "Respuesta de la API: " . $errorResponse;
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Respuesta de error: $errorResponse\n", FILE_APPEND);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del Mensaje - WaSenderAPI</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-container { max-width: 600px; margin: 0 auto; }
        .error { color: red; }
        .result { margin-top: 20px; padding: 10px; border: 1px solid #ccc; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Estado del Mensaje - WaSenderAPI</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($result): ?>
            <div class="result">
                <?php echo $result; ?>
            </div>
        <?php endif; ?>
        
        <p><a href="check_messages.php">Volver al formulario</a></p>
    </div>
</body>
</html>
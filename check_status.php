<?php
require_once __DIR__ . '/../../interface/globals.php';
require_once '../../vendor/autoload.php'; // Incluir GuzzleHTTP

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Configuración de WaSenderAPI
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
$phoneNumber = trim($_POST['phoneNumber'] ?? '');
$result = '';
$errors = [];

// Validar entrada
if (empty($msgId)) {
    $errors[] = 'El ID del mensaje es obligatorio.';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: ID del mensaje vacío\n", FILE_APPEND);
}
if (empty($phoneNumber)) {
    $errors[] = 'El número de teléfono es obligatorio.';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Número de teléfono vacío\n", FILE_APPEND);
} elseif (!preg_match('/^\d{10}$/', $phoneNumber)) {
    $errors[] = 'El número de teléfono debe tener 10 dígitos (ej. 3404540440).';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Número de teléfono inválido ($phoneNumber)\n", FILE_APPEND);
}

// Obtener API Key desde la tabla facility
if (empty($errors)) {
    try {
        $sql = "SELECT f.oid 
                FROM patient_data pd 
                JOIN openemr_postcalendar_events ope ON pd.pid = ope.pc_pid 
                JOIN facility f ON ope.pc_facility = f.id 
                WHERE pd.phone_cell = ? AND ope.pc_apptstatus = 'WSP' 
                ORDER BY ope.pc_eventDate DESC LIMIT 1";
        $facility = sqlQuery($sql, [$phoneNumber]);
        $apiKey = $facility['oid'] ?? '';
        if (empty($apiKey)) {
            $errors[] = 'No se encontró la clave API para el número de teléfono proporcionado.';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: No se encontró oid para phone=$phoneNumber\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        $errors[] = 'Error al consultar la tabla facility: ' . $e->getMessage();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error al consultar facility: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Consultar estado si no hay errores
if (empty($errors) && !empty($apiKey)) {
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
    <title>Estado del Mensaje</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="mb-4 text-center">Estado del Mensaje</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($result): ?>
                    <div class="alert alert-info">
                        <?php echo $result; ?>
                    </div>
                <?php endif; ?>
                
                <p class="text-center"><a href="check_messages.php" class="btn btn-secondary">Volver al formulario</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
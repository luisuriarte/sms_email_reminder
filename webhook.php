<?php
require_once __DIR__ . '/../../interface/globals.php';

// Ruta absoluta del log
define('WEBHOOK_LOG', '/var/www/html/origen.ar/hcd/modules/sms_email_reminder/webhook.log');

// Log inicial para cualquier solicitud recibida
$log_result = file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Solicitud recibida. Método: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
if ($log_result === false) {
    error_log("Error al escribir en webhook.log: Permisos o ruta incorrecta", 0);
}

$input = file_get_contents('php://input');
$webhook_data = json_decode($input, true);

// Log del cuerpo de la solicitud
file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Cuerpo: " . $input . "\n", FILE_APPEND);

// Validar Content-Type
$headers = getallheaders();
$content_type = isset($headers['Content-Type']) ? $headers['Content-Type'] : '';
file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Content-Type: $content_type\n", FILE_APPEND);
if ($content_type !== 'application/json') {
    file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Error: Content-Type no es application/json. Recibido: $content_type\n", FILE_APPEND);
    http_response_code(400);
    exit;
}

// Procesar evento messages.update
if (isset($webhook_data['event']) && $webhook_data['event'] == 'messages.update') {
    $msg_id = $webhook_data['data']['key']['id'] ?? '';
    $jid = $webhook_data['data']['key']['remoteJid'] ?? '';
    if (strpos($jid, '@c.us') === false) {
        file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Ignorando messages.update: No es un número individual ($jid)\n", FILE_APPEND);
        http_response_code(200);
        exit;
    }
    $status = $webhook_data['data']['status'] ?? '';
    $phone = preg_replace('/[^0-9]/', '', $jid);
    $phone = substr($phone, -10);

    // Obtener facility_npi (Webhook Secret) basado en el número de teléfono
    try {
        $sql = "SELECT f.facility_npi 
                FROM patient_data pd 
                JOIN openemr_postcalendar_events ope ON pd.pid = ope.pc_pid 
                JOIN facility f ON ope.pc_facility = f.id 
                WHERE pd.phone_cell = ? AND ope.pc_apptstatus = 'WSP' 
                ORDER BY ope.pc_eventDate DESC LIMIT 1";
        $facility = sqlQuery($sql, [$phone]);
        $WEBHOOK_SECRET = $facility['facility_npi'] ?? '';
        if (empty($WEBHOOK_SECRET)) {
            file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Error: No se encontró facility_npi para phone=$phone\n", FILE_APPEND);
            http_response_code(500);
            exit;
        }
    } catch (Exception $e) {
        file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Error al consultar facility: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }

    // Validar Webhook Secret
    $received_secret = isset($headers['X-Webhook-Signature']) ? $headers['X-Webhook-Signature'] : '';
    file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - X-Webhook-Signature: $received_secret\n", FILE_APPEND);
    if ($received_secret !== $WEBHOOK_SECRET) {
        file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Error: Webhook Signature no coincide. Recibido: $received_secret\n", FILE_APPEND);
        http_response_code(401);
        exit;
    }

    // Actualizar estado en notification_log
    try {
        $sql = "UPDATE notification_log SET status = ? WHERE msg_id = ? AND patient_info LIKE ?";
        sqlStatement($sql, [$status, $msg_id, "%|||$phone|||%"]);
        file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - messages.update procesado: msg_id=$msg_id, phone=$phone, status=$status\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Error SQL: " . $e->getMessage() . "\n", FILE_APPEND);
        error_log("Error SQL en webhook.php: " . $e->getMessage(), 0);
        http_response_code(500);
        exit;
    }
}

// Responder con 200 OK
file_put_contents(WEBHOOK_LOG, date('Y-m-d H:i:s') . " - Respuesta enviada: HTTP 200 OK\n", FILE_APPEND);
http_response_code(200);
?>
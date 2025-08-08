<?php
/*
 * Purpose: to be run by cron every hour, look for appointments
 * in the pre-notification period and send a WhatsApp reminder
 *
 * @package OpenEMR
 * @author Larry Lart
 * @copyright Copyright (c) 2008 Larry Lart
 * @copyright Copyright (c) 2023 - 2024 Luis A. Uriarte <luis.uriarte@gmail.com>
 * @link https://www.open-emr.org
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

setlocale(LC_ALL, 'es-ES', 'Spanish_Spain', 'Spanish');

global $argc;
global $data_info;

// larry :: hack add for command line version
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME'] = 'localhost';
$backpic = "";

// for cron
if ($argc > 1 && empty($_SESSION['site_id']) && empty($_GET['site'])) {
    $c = stripos($argv[1], 'site=');
    if ($c === false) {
        echo xlt("Missing Site Id using default") . "\n";
        $argv[1] = "site=default";
    }
    $args = explode('=', $argv[1]);
    $_GET['site'] = isset($args[1]) ? $args[1] : 'default';
}
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $ignoreAuth = true;
}
require_once(__DIR__ . "/../../interface/globals.php");
require_once(__DIR__ . "/../../library/appointments.inc.php");
require_once(__DIR__ . "/../../library/patient_tracker.inc.php");
require_once(__DIR__ . "/cron_wsp_functions.php");
require_once(__DIR__ . "/../../vendor/autoload.php");

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// check command line for quiet option
$bTestRun = isset($_REQUEST['dryrun']) ? 1 : 0;
if ($argc > 1 && $argv[2] == 'test') {
    $bTestRun = 1;
}

$TYPE = "WSP";
$CRON_TIME = 5;

// get notification settings
$vectNotificationSettings = cron_GetNotificationSettings();
$CRON_TIME = $vectNotificationSettings['Send_SMS_Before_Hours'] ?? 5;
$SMS_NOTIFICATION_HOUR = $vectNotificationSettings['SMS_hours'] ?? 48; // Ajustado a 48 horas
$EMAIL_NOTIFICATION_HOUR = $vectNotificationSettings['Email_hours'] ?? 48;

// Debug: Mostrar configuración
$strMsg = "\nConfiguración: SMS_NOTIFICATION_HOUR=$SMS_NOTIFICATION_HOUR, CRON_TIME=$CRON_TIME, EMAIL_NOTIFICATION_HOUR=$EMAIL_NOTIFICATION_HOUR\n";
WriteLog($strMsg);

// get data from automatic_notification table
$db_email_msg = cron_getNotificationData($TYPE);

// Calcular check_date
$check_date = date("Y-m-d", mktime(date("H") + $SMS_NOTIFICATION_HOUR, 0, 0, date("m"), date("d"), date("Y")));
$strMsg = "Check Date: $check_date\n";
WriteLog($strMsg);

$db_patient = cron_getAlertpatientData($TYPE);
echo "\n<br />Total " . text(count($db_patient)) . " Registros Encontrados\n";

// Debug: Mostrar pacientes encontrados
if (count($db_patient) > 0) {
    $strMsg = "Pacientes encontrados:\n";
    foreach ($db_patient as $prow) {
        $strMsg .= "PID: {$prow['pid']}, Nombre: {$prow['fname']} {$prow['lname']}, Teléfono: {$prow['phone_cell']}, Fecha Cita: {$prow['pc_eventDate']} {$prow['pc_startTime']}\n";
    }
    WriteLog($strMsg);
} else {
    $strMsg = "No se encontraron pacientes para notificar.\n";
    WriteLog($strMsg);
}

// for every event found
for ($p = 0; $p < count($db_patient); $p++) {
    $prow = $db_patient[$p];
    $patient_name = $prow['fname'] . " " . $prow['mname'] . " " . $prow['lname'];
    $patient_phone = $prow['phone_cell'];
    $patient_email = $prow['email'];
    $app_date = $prow['pc_eventDate'] . " " . $prow['pc_startTime'];
    $app_end_date = $prow['pc_eventDate'] . " " . $prow['pc_endTime'];
    $app_time = strtotime($app_date);
    $eid = $prow['pc_eid'];
    $pid = $prow['pid'];
    $facility_name = $prow['facility_name'];
    $facility_address = $prow['facility_address'];
    $facility_phone = $prow['facility_phone'];
    $facility_email = $prow['facility_email'];
    $facility_url = $prow['facility_website'];
    $facility_vendor = $prow['service_vendor'];
    $facility_instance = $prow['vendor_instance'];
    $facility_api = $prow['vendor_api'];
    $facility_logo = $prow['facility_logo_wsp'];
    $provider_name = $prow['user_name'];
    $latitude = $prow['latitude'];
    $longitude = $prow['longitude'];

    $app_time_hour = round($app_time / 3600);
    $curr_total_hour = round(time() / 3600);
    $remaining_app_hour = round($app_time_hour - $curr_total_hour);
    $remain_hour = round($remaining_app_hour - $SMS_NOTIFICATION_HOUR);

    // build log message
    $strMsg = "\n========================" . $TYPE . " || " . date("Y-m-d H:i:s") . "=========================";
    $strMsg .= "\nSEND NOTIFICATION BEFORE: $SMS_NOTIFICATION_HOUR || CRONJOB RUN EVERY: $CRON_TIME || APPDATETIME: $app_date || REMAINING APP HOUR: $remaining_app_hour || REMAIN HOUR: $remain_hour";
    WriteLog($strMsg);

    // Comentar el intervalo para pruebas
    // if ($remain_hour >= -($CRON_TIME) && $remain_hour <= $CRON_TIME) {
        // set message
        $db_email_msg['message'] = cron_setmessage($prow, $db_email_msg);

        // send sms to patient - if not in test mode
        $msgId = null;
        $status = 'in_progress';
        if ($bTestRun == 0) {
            $result = cron_SendWSP(
                $patient_phone,
                $db_email_msg['message'],
                $app_date,
                $app_end_date,
                $patient_name,
                $patient_email,
                $facility_name,
                $facility_address,
                $facility_phone,
                $facility_email,
                $provider_name,
                $facility_url,
                $facility_vendor,
                $facility_instance,
                $facility_logo,
                $facility_api
            );

            // Capture msgId and log from cron_SendWSP
            if (isset($result['status']) && $result['status'] == 'success' && !empty($result['msgId'])) {
                $msgId = $result['msgId'];
                $strMsg = "\nWaSenderAPI: Mensaje enviado con msgId: $msgId";

                // Consultar estado del mensaje
                try {
                    $client = new Client();
                    $response = $client->get("https://www.wasenderapi.com/api/message-status/$msgId", [
                        'headers' => [
                            'Authorization' => "Bearer $facility_api",
                            'Accept' => 'application/json',
                        ]
                    ]);
                    $statusData = json_decode($response->getBody(), true);
                    if ($statusData['success']) {
                        $status = $statusData['data']['status'];
                        $strMsg .= "\nWaSenderAPI: Estado del mensaje $msgId: $status";
                    } else {
                        $strMsg .= "\nWaSenderAPI: No se pudo obtener el estado del mensaje $msgId";
                    }
                } catch (RequestException $e) {
                    $strMsg .= "\nError al consultar estado del mensaje $msgId: " . $e->getMessage();
                    if ($e->hasResponse()) {
                        $strMsg .= "\nRespuesta de la API: " . $e->getResponse()->getBody();
                    }
                }
            } else {
                $strMsg .= "\nWaSenderAPI: Error al enviar el mensaje: " . $result['log'];
            }
            WriteLog($strMsg);
        }

        // insert entry in notification_log table with msgId and status
        cron_InsertNotificationLogEntry($TYPE, $prow, $db_email_msg, $msgId, $status);
    
        // update entry >> pc_sendalertsms='Yes'
        cron_updateentry($TYPE, $prow['pid'], $prow['pc_eid']);

        // Update patient_tracker table and insert a row in patient_tracker_element table
        manage_tracker_status($prow['pc_eventDate'], $prow['pc_startTime'], $eid, $pid, $user = 'Automático', $status = $TYPE, $room = '', $enc_id = '');

        $strMsg = " || Mensaje enviado al teléfono $patient_phone";
        $strMsg .= "\nPatient Info: $patient_name || $patient_phone || $patient_email";
        $strMsg .= "\nMessage: " . $db_email_msg['message'];
        WriteLog($strMsg);
    // }
}

// larry :: update notification data again - todo :: fix change in cron_updateentry
$db_email_msg = cron_getNotificationData($TYPE);

?>

<html>
<head>
<title>Cronjob - Notificación por WhatsApp</title>
</head>
<body>
    <center>
    </center>
</body>
</html>
<?php
/**
 * CRON FUNCTIONS - to use with cron_smd(*) and cron_email backend
 * scripts to notify events
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Larry Lart
 * @copyright Copyright (c) 2008 Larry Lart
 * @copyright Copyright (c) 2023 - 2024 Luis A. Uriarte <luis.uriarte@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

global $smsgateway_info;
global $patient_info;
global $data_info;
global $gbl_time_zone;
global $zone;
global $SMS_NOTIFICATION_HOUR;
global $EMAIL_NOTIFICATION_HOUR;

////////////////////////////////////////////////////////////////////
// Function:    dateToCal
// Purpose: Fecha a formato iCalendar
////////////////////////////////////////////////////////////////////
function dateToCal($timestamp) {
    return date('Ymd\THis', strtotime($timestamp));
}


////////////////////////////////////////////////////////////////////
// Function:    WriteLog
// Purpose: written log into file
////////////////////////////////////////////////////////////////////
function WriteLog($data)
{
    if (stripos(PHP_OS, 'win') === 0) {
		$filename = "\\logs\\cronlog_" . date("Y-m-d_H-i-s") . ".html";
	} else {
		$filename = "/logs/cronlog_" . date("Y-m-d_H-i-s") . ".html";
	}
    //echo $filename;
    if (!$fp = fopen(__DIR__ . $filename, 'w+e')) {
            print "Cannot open file (" . text($filename) . ")";
            exit;
    }
    $sdata = "\n====================================================================\n";
    if (!fwrite($fp, $sdata . $data . $sdata)) {
        print "Cannot write to file (" . text($filename) . ")";
        exit;
    }
    fclose($fp);
}

////////////////////////////////////////////////////////////////////
// define my_print_r - used for debuging - if not defined
////////////////////////////////////////////////////////////////////
if (!function_exists('my_print_r')) {
    function my_print_r($data)
    {
        echo "<pre>";
        echo(text(print_r($data, true)));
        echo "</pre>";
    }
}

////////////////////////////////////////////////////////////////////
// Function:    cron_SendWSP
// Purpose: send WhatsApp
////////////////////////////////////////////////////////////////////

// Incluir Guzzle una sola vez al inicio del archivo

require_once '../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

function cron_SendWSP(
    $patient_phone, $vBody, $start_date, $end_date, $patient_name, $patient_email, $facility_name,
    $facility_address, $facility_phone, $facility_email, $provider, $facility_url, $facility_vendor,
    $facility_instance, $facility_logo, $facility_api
) {
    // Inicializar log para depuración
    $log = [];
    $log[] = "Iniciando cron_SendWSP: " . date('Y-m-d H:i:s');
    $result = ['status' => 'error', 'msgId' => null, 'log' => ''];

    // Validar parámetros obligatorios
    if (empty($patient_phone) || empty($vBody) || empty($start_date) || empty($end_date) ||
        empty($facility_name) || empty($facility_url) || empty($facility_vendor) ||
        empty($facility_instance) || empty($facility_api)) {
        $log[] = "Error: Faltan parámetros obligatorios.";
        $result['log'] = implode("\n", $log);
        file_put_contents('cron_sendwsp.log', implode("\n", $log) . "\n", FILE_APPEND);
        return $result;
    }

    // Validar formato del número de teléfono (10 dígitos)
    if (!preg_match('/^\d{10}$/', $patient_phone)) {
        $log[] = "Error: El número de teléfono '$patient_phone' no tiene un formato válido.";
        $result['log'] = implode("\n", $log);
        file_put_contents('cron_sendwsp.log', implode("\n", $log) . "\n", FILE_APPEND);
        return $result;
    }

    // Inicializar variables
    $Instance = $facility_instance;
    $ApiKey = $facility_api;
    $url_base = rtrim($facility_url, '/') . "/modules/sms_email_reminder/";
    $url_logo_wsp = $url_base . $facility_logo;
    $todaystamp = gmdate("Ymd\THis\Z");
    $zone = !empty($GLOBALS['gbl_time_zone']) ? $GLOBALS['gbl_time_zone'] : "America/Argentina/Buenos_Aires";

    $log[] = "Configuración: vendor=$facility_vendor, instance=$Instance, url_base=$url_base, logo=$url_logo_wsp";

    // Crear identificador único
    $cal_uid = date('Ymd') . 'T' . date('His') . "-" . rand() . substr($facility_url, 8);

    // Crear contenido ICAL
    $ical_content = "BEGIN:VCALENDAR
METHOD:REQUEST
PRODID:-//Microsoft Corporation//Outlook 11.0 MIMEDIR//EN
VERSION:2.0
BEGIN:VEVENT
DTSTART;TZID=$zone:" . dateToCal($start_date) . "
DTEND;TZID=$zone:" . dateToCal($end_date) . "
LOCATION:$facility_address
TRANSP:OPAQUE
SEQUENCE:0
UID:$cal_uid
ORGANIZER;CN=$facility_name:mailto:$facility_email
ATTENDEE;PARTSTAT=ACCEPTED;CN=$patient_name;EMAIL=$patient_email:mailto:$patient_email
CONTACT:$facility_name\\, $facility_phone\\,$facility_email
DTSTAMP:$todaystamp
SUMMARY:Turno en $facility_name
DESCRIPTION:$vBody
URL;VALUE=URI:$facility_url
PRIORITY:5
CLASS:PUBLIC
BEGIN:VALARM
TRIGGER:-PT60M
REPEAT:1
DURATION:PT30M
ACTION:DISPLAY
END:VALARM
END:VEVENT
END:VCALENDAR";

    // Crear archivo .ics
    $archivo = "TURNO-" . substr(md5(time()), 0, 8) . ".ics";
    $file_handle = @fopen($archivo, 'w+');
    if (!$file_handle) {
        $log[] = "Error: No se pudo crear el archivo .ics en el directorio actual.";
        $result['log'] = implode("\n", $log);
        file_put_contents('cron_sendwsp.log', implode("\n", $log) . "\n", FILE_APPEND);
        return $result;
    }
    fwrite($file_handle, $ical_content);
    fclose($file_handle);
    $log[] = "Archivo .ics creado: $archivo";

    // Verificar si el archivo .ics existe y es legible
    if (!file_exists($archivo) || !is_readable($archivo)) {
        $log[] = "Error: El archivo .ics ($archivo) no existe o no es legible.";
        $result['log'] = implode("\n", $log);
        file_put_contents('cron_sendwsp.log', implode("\n", $log) . "\n", FILE_APPEND);
        return $result;
    }

    // Verificar accesibilidad de las URLs
    $url_ics = $url_base . $archivo;
    $log[] = "URL del logo: $url_logo_wsp";
    $log[] = "URL del archivo .ics: $url_ics";

    // Probar accesibilidad de las URLs
    $headers_logo = @get_headers($url_logo_wsp);
    $headers_ics = @get_headers($url_ics);
    $log[] = "Accesibilidad logo: " . ($headers_logo ? $headers_logo[0] : "No se pudo verificar");
    $log[] = "Accesibilidad .ics: " . ($headers_ics ? $headers_ics[0] : "No se pudo verificar");

    // Procesar según el proveedor
    if (strtolower($facility_vendor) == "wasenderapi") {
        $ChatId = "+549" . $patient_phone;
        $log[] = "WaSenderAPI: Enviando a $ChatId";
        $client = new Client();
        $url = 'https://www.wasenderapi.com/api/send-message';

        // Enviar imagen con texto
        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => "Bearer $ApiKey",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'to' => $ChatId,
                    'text' => $vBody,
                    'imageUrl' => $url_logo_wsp,
                ]
            ]);
            $responseBody = json_decode($response->getBody(), true);
            $log[] = "WaSenderAPI (imagen): " . $response->getBody();
        } catch (RequestException $e) {
            $log[] = "Error en WaSenderAPI (imagen): " . $e->getMessage();
            if ($e->hasResponse()) {
                $log[] = "Respuesta: " . $e->getResponse()->getBody();
            }
            $result['log'] = implode("\n", $log);
            file_put_contents('cron_sendwsp.log', implode("\n", $log) . "\n", FILE_APPEND);
            return $result;
        }

        // Retraso de 60 segundos para la versión de prueba de WaSenderAPI
        $log[] = "WaSenderAPI: Esperando 60 segundos antes de enviar el archivo .ics (restricción de la versión de prueba)";
        sleep(60);

        // Enviar archivo iCalendar con tipo MIME explícito
        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => "Bearer $ApiKey",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'to' => $ChatId,
                    'text' => "$facility_name: Presione en el adjunto para verificar su turno. Gracias.",
                    'documentUrl' => $url_ics,
                    'mimeType' => 'text/calendar'
                ]
            ]);
            $responseBody = json_decode($response->getBody(), true);
            $log[] = "WaSenderAPI (iCalendar): " . $response->getBody();
            if ($responseBody['success'] && isset($responseBody['data']['msgId'])) {
                $result['status'] = 'success';
                $result['msgId'] = $responseBody['data']['msgId'];
            }
        } catch (RequestException $e) {
            $log[] = "Error en WaSenderAPI (iCalendar): " . $e->getMessage();
            if ($e->hasResponse()) {
                $log[] = "Respuesta: " . $e->getResponse()->getBody();
            }
        }
    } else {
        $log[] = "Error: Proveedor '$facility_vendor' no reconocido.";
    }

    // Retrasar eliminación del archivo .ics
    sleep(5);
    if (file_exists($archivo) && unlink($archivo)) {
        $log[] = "Archivo .ics borrado correctamente.";
    } else {
        $log[] = "Problema al borrar el archivo .ics.";
    }
    
    // Guardar log en archivo
    $result['log'] = implode("\n", $log);
    file_put_contents('cron_sendwsp.log', implode("\n", $log) . "\n", FILE_APPEND);

    // Retornar resultado con msgId
    return $result;
}

////////////////////////////////////////////////////////////////////
// Function:    cron_updateentry
// Purpose: update status yes if alert send to patient
////////////////////////////////////////////////////////////////////
function cron_updateentry($type, $pid, $pc_eid)
{
    if ($type == 'WSP') {
        $sql = "UPDATE openemr_postcalendar_events SET pc_sendalertsms = 'YES' WHERE pc_pid = ? AND pc_eid = ?";
        sqlStatement($sql, array($pid, $pc_eid));
    } else {
        $sql = "UPDATE openemr_postcalendar_events SET pc_sendalertemail = 'YES' WHERE pc_pid = ? AND pc_eid = ?";
        sqlStatement($sql, array($pid, $pc_eid));
    }
}
////////////////////////////////////////////////////////////////////
// Function:    cron_getAlertpatientData
// Purpose: get patient data for send to alert
////////////////////////////////////////////////////////////////////
function cron_getAlertpatientData($type)
{
    global $SMS_NOTIFICATION_HOUR, $EMAIL_NOTIFICATION_HOUR;
   
    if ($type == 'WSP') {
        $ssql = " AND pd.hipaa_allowsms='YES' AND pd.phone_cell<>'' AND ope.pc_sendalertsms='NO' ";
        // Buscar citas en los próximos 3 días
        $start_date = date("Y-m-d");
        $end_date = date("Y-m-d", strtotime("+3 days"));
        $ssql .= " AND ope.pc_eventDate BETWEEN '$start_date' AND '$end_date'";
    } else {
        $ssql = " AND pd.hipaa_allowemail='YES' AND pd.email <> '' AND ope.pc_sendalertemail='NO' ";
        $start_date = date("Y-m-d");
        $end_date = date("Y-m-d", strtotime("+3 days"));
        $ssql .= " AND ope.pc_eventDate BETWEEN '$start_date' AND '$end_date'";
    }
    $patient_field = "pd.pid,pd.title,pd.fname,pd.lname,pd.mname,pd.phone_cell,pd.email,pd.email_direct,pd.hipaa_allowsms,pd.hipaa_allowemail,";
    $query = "SELECT $patient_field ope.pc_eid, ope.pc_pid, ope.pc_title,
                    ope.pc_hometext, ope.pc_eventDate, ope.pc_endDate,
                    ope.pc_duration, ope.pc_alldayevent, ope.pc_startTime, ope.pc_endTime,
                    CONCAT(u.fname, ' ', u.mname, ' ', u.lname) user_name, u.suffix AS user_preffix, pte.pt_tracker_id,
                    pte.status, pt.lastseq, f.name AS facility_name, CONCAT(f.street, ', ', f.city, ', ', f.state)
                    AS facility_address, f.phone AS facility_phone, f.email AS facility_email, f.facility_code AS
                    service_vendor, f.facility_npi AS vendor_instance, f.oid AS vendor_api, f.website AS facility_website,
                    f.attn AS facility_logo_email, f.domain_identifier AS facility_logo_wsp
            FROM openemr_postcalendar_events AS ope
            LEFT OUTER JOIN patient_tracker AS pt ON pt.pid = ope.pc_pid 
            AND pt.apptdate = ope.pc_eventDate 
            AND pt.appttime = ope.pc_starttime
            AND pt.eid = ope.pc_eid
            LEFT OUTER JOIN patient_tracker_element AS pte ON pte.pt_tracker_id = pt.id AND pte.seq = pt.lastseq
            LEFT OUTER JOIN patient_data AS pd ON pd.pid = ope.pc_pid
            LEFT OUTER JOIN users AS u ON u.id = ope.pc_aid
            LEFT OUTER JOIN facility AS f ON f.id = ope.pc_facility
            WHERE ope.pc_pid = pd.pid $ssql
            ORDER BY ope.pc_eventDate, ope.pc_startTime";
	
    $db_patient = sqlStatement($query);
    $patient_array = array();
    $cnt = 0;
    while ($prow = sqlFetchArray($db_patient)) {
        $patient_array[$cnt] = $prow;
        $cnt++;
    }
    return $patient_array;
}


////////////////////////////////////////////////////////////////////
// Function:    cron_getNotificationData
// Purpose: get alert notification data
////////////////////////////////////////////////////////////////////
function cron_getNotificationData($type)
{
    $sql = "SELECT * FROM automatic_notification WHERE type = ? ";
    $res = sqlQuery($sql, array($type));
    return $res;
}

////////////////////////////////////////////////////////////////////
// Function:    cron_InsertNotificationLogEntry
// Purpose: insert log entry in table
////////////////////////////////////////////////////////////////////
// Función modificada para incluir msg_id
function cron_InsertNotificationLogEntry($type, $prow, $db_email_msg, $msg_id = null, $status = null) {
    if ($type == 'WSP') {
        $smsgateway_info = $db_email_msg['sms_gateway_type'];
    } else {
        $smsgateway_info = $db_email_msg['email_sender'] . "|||" . $db_email_msg['email_subject'];
    }
    $patient_info = $prow['title'] . " " . $prow['fname'] . " " . $prow['mname'] . " " . $prow['lname'] . "|||" . $prow['phone_cell'] . "|||" . $prow['email'];
    $data_info = $prow['pc_eventDate'] . "|||" . $prow['pc_endDate'] . "|||" . $prow['pc_startTime'] . "|||" . $prow['pc_endTime'];
    $sql_loginsert = "INSERT INTO `notification_log` ( `iLogId` , `pid` , `pc_eid` , `sms_gateway_type` , `message` , `email_sender` , `email_subject` , `type` , `patient_info` , `smsgateway_info` , `msg_id` , `status` , `pc_eventDate` , `pc_endDate` , `pc_startTime` , `pc_endTime` , `dSentDateTime` ) VALUES ";
    $sql_loginsert .= "(NULL , ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    sqlStatement(
        $sql_loginsert,
        [
            $prow['pid'],
            $prow['pc_eid'],
            $db_email_msg['sms_gateway_type'],
            $db_email_msg['message'],
            $db_email_msg['email_sender'],
            $db_email_msg['email_subject'],
            $db_email_msg['type'],
            $patient_info,
            $smsgateway_info,
            $msg_id,
            $status,
            $prow['pc_eventDate'],
            $prow['pc_endDate'],
            $prow['pc_startTime'],
            $prow['pc_endTime'],
            date("Y-m-d H:i:s")
        ]
    );
}

////////////////////////////////////////////////////////////////////
// Function:    cron_setmessage
// Purpose: set the message
////////////////////////////////////////////////////////////////////
function cron_setmessage($prow, $db_email_msg)
{
    $NAME = $prow['title'] . " " . $prow['fname'] . " " . $prow['mname'] . " " . $prow['lname'];
    $PROVIDER = $prow['user_name'];
    $dtWrk = strtotime($prow['pc_eventDate'] . ' ' . $prow['pc_startTime']);
	$dias = array("Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sábado");
	$meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
	$DATE = $dias[date('w',$dtWrk)]." ".date('d',$dtWrk)." de ".$meses[date('n',$dtWrk)-1]. " del ".date('Y',$dtWrk) ;
    $time_ap = strtotime($prow['pc_endTime']);
    $STARTTIME = date("H:i", $dtWrk);
    $ENDTIME = date("h:i", $time_ap);
    $FACILITY_NAME = $prow['facility_name'];
    $FACILITY_ADDRESS = $prow['facility_address'];
    $FACILITY_PHONE = $prow['facility_phone'];
    $FACILITY_EMAIL = $prow['facility_email'];
    $USER_PREFFIX = $prow['user_preffix'];
    $find_array = array('***NAME***' , '***PROVIDER***', '***USER_PREFFIX***' , '***DATE***' , '***STARTTIME***' , '***ENDTIME***', '***FACILITY_NAME***', 
                        '***FACILITY_ADDRESS***', '***FACILITY_PHONE***', '***FACILITY_EMAIL***');
    $replace_array = array($NAME , $PROVIDER, $USER_PREFFIX , $DATE , $STARTTIME , $ENDTIME, $FACILITY_NAME , $FACILITY_ADDRESS , $FACILITY_PHONE, $FACILITY_EMAIL);
    $message = str_replace($find_array, $replace_array, $db_email_msg['message']);
    return $message;
}

////////////////////////////////////////////////////////////////////
// Function:    cron_GetNotificationSettings
// Purpose: get notification settings
////////////////////////////////////////////////////////////////////
function cron_GetNotificationSettings()
{
    $sql = "SELECT * FROM notification_settings WHERE type = 'SMS/Email Settings'";
    $res = sqlQuery($sql);
    return $res;
}

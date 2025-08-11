<?php
/**
 * CRON FUNCTIONS - to use with cron_smd(*) and cron_email backend
 * scripts to notify events
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Larry Lart
 * @copyright Copyright (c) 2008 Larry Lart
 * @copyright Copyright (c) 2022 - 2024 Luis A. Uriarte <luis.uriarte@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// larry :: somne global to be defined here
require_once(__DIR__ . "/../../interface/globals.php");
global $smsgateway_info;
global $patient_info;
global $data_info;
global $gbl_time_zone;
global $zone;
global $SMS_NOTIFICATION_HOUR;
global $EMAIL_NOTIFICATION_HOUR;

//require_once("../../globals.php");
////////////////////////////////////////////////////////////////////
// Function:    cron_SendMail
// Purpose: send mail
// Input:   to, subject, email body and from
// Output:  status - if sent or not
////////////////////////////////////////////////////////////////////
use PHPMailer\PHPMailer\PHPMailer;
use OpenEMR\Common\Crypto\CryptoGen;

function cron_SendMail($patient_email, $cc, $subject, $vBody, $start_date, $end_date, $patient_name, $facility_name, 
                      $facility_address, $facility_phone, $facility_email, $facility_url, $provider, $logo_email, $latitude, $longitude)
{
    // Check if SMTP globals are set
    if ($GLOBALS['SMTP_HOST'] == '') {
        $mstatus = @mail($patient_email, $cc, $subject, $vBody);
    } else {
        
        if (!class_exists("SMTP")) {

            $SenderName = $GLOBALS['patient_reminder_sender_name'];
			$SenderEmail = $GLOBALS['patient_reminder_sender_email'];
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer'))
		{
		    require (__DIR__ . "/../../library/classes/PHPMailer/src/PHPMailer.php");
		    require (__DIR__ . "/../../library/classes/PHPMailer/src/SMTP.php");
		}
        }

        // Sanitizar las variables
        $latitude = urlencode(trim($latitude));
        $longitude = urlencode(trim($longitude));
        $facility_name = htmlspecialchars(trim($facility_name), ENT_QUOTES, 'UTF-8');
        $facility_address = htmlspecialchars(trim($facility_address), ENT_QUOTES, 'UTF-8');
        $patient_name = htmlspecialchars(trim($patient_name), ENT_QUOTES, 'UTF-8');
        $patient_email = htmlspecialchars(trim($patient_email), ENT_QUOTES, 'UTF-8');
        $facility_email = htmlspecialchars(trim($facility_email), ENT_QUOTES, 'UTF-8');
        $facility_phone = htmlspecialchars(trim($facility_phone), ENT_QUOTES, 'UTF-8');
        $facility_url = htmlspecialchars(trim($facility_url), ENT_QUOTES, 'UTF-8');
        $vBody = htmlspecialchars(trim($vBody), ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars(trim($subject), ENT_QUOTES, 'UTF-8');
        $zone = isset($GLOBALS['gbl_time_zone']) ? $GLOBALS['gbl_time_zone'] : 'America/Argentina/Buenos_Aires';

        $zoom = 15;
        $apiKey = "b9ec3d484da44247a912b9b27ada0d3d"; // Clave de Geoapify

        // Validar que las variables clave no estén vacías
        if (empty($latitude) || empty($longitude) || empty($apiKey) || empty($patient_email)) {
            echo "Error: Variables requeridas están vacías.\n";
            echo "Latitude: $latitude\n";
            echo "Longitude: $longitude\n";
            echo "FacilityName: $facility_name\n";
            echo "Patient Email: $patient_email\n";
            echo "API Key: $apiKey\n";
            $mstatus = false;
            return $mstatus;
        }

        // Construir la URL del mapa estático con Geoapify (sin texto en el marcador)
        $baseUrl = "https://maps.geoapify.com/v1/staticmap";
        $params = [
            'style' => 'osm-carto',
            'width' => 600,
            'height' => 300,
            'center' => "lonlat:$longitude,$latitude",
            'zoom' => $zoom,
            'marker' => "lonlat:$longitude,$latitude;color:red;size:medium",
            'apiKey' => $apiKey
        ];
        $staticMapUrl = $baseUrl . '?' . http_build_query($params);
        // URL para el mapa interactivo en OpenStreetMap
        $mapLinkUrl = "https://www.openstreetmap.org/?mlat={$latitude}&mlon={$longitude}#map={$zoom}/{$latitude}/{$longitude}";

        // Función para escapar caracteres en iCalendar
        function escapeIcalValue($value) {
            $value = str_replace(["\\", "\n", "\r", ",", ";"], ["\\\\", "\\n", "", "\\,", "\\;"], $value);
            return $value;
        }

        // Función para formatear fechas en iCalendar
        function dateToCal($timestamp) {
            return date('Ymd\THis', strtotime($timestamp));
        }

        // Función para plegar líneas largas (line folding) según RFC 5545 con soporte UTF-8
        function foldIcalContent($content) {
            $lines = explode("\r\n", $content);
            $folded = [];
            foreach ($lines as $line) {
                while (mb_strlen($line, 'UTF-8') > 75) {
                    $folded[] = mb_substr($line, 0, 75, 'UTF-8');
                    $line = ' ' . mb_substr($line, 75, mb_strlen($line, 'UTF-8'), 'UTF-8');
                }
                $folded[] = $line;
            }
            return implode("\r\n", $folded);
        }

        // Escapar valores para el archivo .ics
        $facility_name_escaped = escapeIcalValue($facility_name);
        $facility_address_escaped = escapeIcalValue($facility_address);
        $vBody_escaped = escapeIcalValue($vBody);
        $patient_name_escaped = escapeIcalValue($patient_name);
        $patient_email_escaped = escapeIcalValue($patient_email);
        $facility_email_escaped = escapeIcalValue($facility_email);
        $facility_phone_escaped = escapeIcalValue($facility_phone);
        $facility_url_escaped = escapeIcalValue($facility_url);

        // Generar fechas para el evento
        $todaystamp = gmdate('Ymd\THis\Z'); // UTC
        $cal_uid = gmdate('Ymd\THis') . "-" . rand() . "@example.com";
        $dtstart = dateToCal($start_date); // Ejemplo: 20250813T091500
        $dtend = dateToCal($end_date); // Ejemplo: 20250813T093000

        // Definir el componente VTIMEZONE para America/Argentina/Buenos_Aires
        $vtimezone = <<<EOT
BEGIN:VTIMEZONE
TZID:America/Argentina/Buenos_Aires
BEGIN:STANDARD
TZOFFSETFROM:-0300
TZOFFSETTO:-0300
TZNAME:ART
DTSTART:19700101T000000
END:STANDARD
END:VTIMEZONE
EOT;

        // Usar METHOD:PUBLISH por defecto para que Gmail lo muestre
        $method = "PUBLISH"; // Cambia a "REQUEST" si necesitas RSVPs

        // Generar el contenido del archivo .ics
        $ical_content = <<<EOT
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//TuOrganizacion//Evento//EN
METHOD:$method
$vtimezone
BEGIN:VEVENT
DTSTART;TZID=$zone:$dtstart
DTEND;TZID=$zone:$dtend
DTSTAMP:$todaystamp
UID:$cal_uid
SUMMARY:$facility_name_escaped
DESCRIPTION:$vBody_escaped
LOCATION:$facility_address_escaped
URL:$facility_url_escaped
ORGANIZER;CN=$facility_name_escaped:mailto:$facility_email_escaped
ATTENDEE;PARTSTAT=NEEDS-ACTION;CN=' . str_replace(',', '\,', $patient_name) . ';EMAIL=' . $patient_email . ':mailto:' . $patient_email . '
CONTACT:$facility_name_escaped\, $facility_phone_escaped\, $facility_email_escaped
CLASS:PUBLIC
PRIORITY:5
TRANSP:OPAQUE
STATUS:CONFIRMED
SEQUENCE:0
END:VEVENT
END:VCALENDAR
EOT;

        // Aplicar plegado de líneas
        $ical_content = foldIcalContent($ical_content);

        // Depurar el contenido del .ics
        //echo "iCal Content:\n" . $ical_content . "\n";

        // Generar un enlace a Google Calendar
        $eventTitle = urlencode("Turno en $facility_name");
        $eventDetails = urlencode($vBody_escaped);
        $eventStart = dateToCal($start_date);
        $eventEnd = dateToCal($end_date);
        $googleCalendarUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text=$eventTitle&dates=$eventStart/$eventEnd&details=$eventDetails&location=" . urlencode($facility_address_escaped);

        // Generar datos estructurados JSON-LD
        $jsonLd = json_encode([
            "@context" => "http://schema.org",
            "@type" => "Event",
            "name" => "Turno en $facility_name",
            "startDate" => date('c', strtotime($start_date)),
            "endDate" => date('c', strtotime($end_date)),
            "location" => [
                "@type" => "Place",
                "name" => $facility_name,
                "address" => $facility_address
            ],
            "description" => $vBody
        ], JSON_UNESCAPED_UNICODE);

        // Configurar PHPMailer
        $mail = new PHPMailer();
        $mail->SMTPDebug = 3;
        $mail->IsSMTP();
        $mail->Host = $GLOBALS['SMTP_HOST'];
        $mail->Port = $GLOBALS['SMTP_PORT'];
        $mail->SMTPAuth = true;
        $mail->Username = $GLOBALS['SMTP_USER'];
        $cryptoGen = new CryptoGen();
        $mail->Password = $cryptoGen->decryptStandard($GLOBALS['SMTP_PASS']);
        $mail->SMTPSecure = $GLOBALS['SMTP_SECURE'];
        $mail->CharSet = "UTF-8";
        $mail->From = $facility_email;
        $mail->FromName = $facility_name;
        $mail->AddAddress($patient_email);
        // $mail->AddCC($cc); // Remove comment to send, also to trusted mail
        $mail->WordWrap = 50;
        $mail->IsHTML(true);
        $mail->Subject = $subject;
        $mail->AddEmbeddedImage($logo_email, "logo", "logo.png");
        $mail->AddCustomHeader("Content-Class: urn:content-classes:calendarmessage");

        $html = <<<EOT
            <div>
                <img src="cid:logo"> 
                <p>$vBody</p>
        <p>$facility_name $facility_address</p>
        <p><a href="$googleCalendarUrl">Agregar a Google Calendar</a></p>
        <a href='{$mapLinkUrl}'>
        <img src='{$staticMapUrl}' alt='Mapa de la ubicación'>
        </a>
        <p>Haz clic en la imagen para ver el mapa interactivo.</p>
        <p>Descarga el archivo adjunto (calendar.ics) e impórtalo en Google Calendar si no ves los detalles del evento.</p>
        <script type="application/ld+json">
            $jsonLd
        </script>
            </div>
EOT;
        $mail->Body = $html;

        // Adjuntar el .ics con base64 para compatibilidad
        $mail->AddStringAttachment($ical_content, "calendar.ics", "base64", "text/calendar; charset=utf-8; method=$method");

        if(!$mail->send()) {
            echo "No se puede enviar mensaje a " . text($patient_email) . ".\nError: " . text($mail->ErrorInfo) . "\n";
            $mstatus = false;
        } else {
            echo "Mensaje enviado a " . text($patient_email) . " OK.\n";
            $mstatus = true;
        }
        unset($mail);

    }
    return $mstatus;
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
// Function:    cron_SendSMS
// Purpose: send sms
////////////////////////////////////////////////////////////////////
function cron_SendSMS($to, $subject, $vBody, $from)
{
    global $mysms;
    $cnt = "";
    $cnt .= "\nDate Time :" . date("d M, Y  h:i:s");
    $cnt .= "\nTo : " . $to;
    $cnt .= "\From : " . $from;
    $cnt .= "\nSubject : " . $subject;
    $cnt .= "\nBody : \n" . $vBody . "\n";
    if (1) {
        //WriteLog($cnt);
    }

    $mstatus = true;
    $mysms->send($to, $from, $vBody);
    return $mstatus;
}

////////////////////////////////////////////////////////////////////
// Function:    cron_updateentry
// Purpose: update status yes if alert send to patient
////////////////////////////////////////////////////////////////////
function cron_updateentry($type, $pid, $pc_eid)
{
    $query = "UPDATE openemr_postcalendar_events SET ";
    
    if ($type == 'SMS') {
        $query .= " openemr_postcalendar_events.pc_sendalertsms='YES' ,  openemr_postcalendar_events.pc_apptstatus='SMS' ";
    } else {
        $query .= " openemr_postcalendar_events.pc_sendalertemail='YES' ,  openemr_postcalendar_events.pc_apptstatus='EMAIL' ";
    }
    $query .= " WHERE openemr_postcalendar_events.pc_pid=? 
                AND openemr_postcalendar_events.pc_eid=? ";
    $db_sql = (sqlStatement($query, [$pid, $pc_eid]));
}

////////////////////////////////////////////////////////////////////
// Function:    cron_getAlertpatientData
// Purpose: get patient data for send to alert
////////////////////////////////////////////////////////////////////
function cron_getAlertpatientData($type)
{
    global $SMS_NOTIFICATION_HOUR, $EMAIL_NOTIFICATION_HOUR;
   
    if ($type == 'SMS') {
        $ssql = " AND pd.hipaa_allowsms='YES' AND pd.phone_cell<>'' AND ope.pc_sendalertsms='NO' ";
        $check_date = date("Y-m-d", mktime(date("h") + $SMS_NOTIFICATION_HOUR, 0, 0, date("m"), date("d"), date("Y")));
    } else {
        $ssql = " AND pd.hipaa_allowemail='YES' AND pd.email <> ''  AND ope.pc_sendalertemail='NO' ";
        $check_date = date("Y-m-d", mktime(date("h") + $EMAIL_NOTIFICATION_HOUR, 0, 0, date("m"), date("d"), date("Y")));
    }
    $patient_field = "pd.pid,pd.title,pd.fname,pd.lname,pd.mname,pd.phone_cell,pd.email,pd.email_direct,pd.hipaa_allowsms,pd.hipaa_allowemail,";
    $ssql .= " AND (ope.pc_eventDate='" . add_escape_custom($check_date) . "')";
    $query = "SELECT $patient_field ope.pc_eid, ope.pc_pid, ope.pc_title,
                    ope.pc_hometext, ope.pc_eventDate, ope.pc_endDate,
                    ope.pc_duration, ope.pc_alldayevent, ope.pc_startTime, ope.pc_endTime,
                    CONCAT(u.fname, ' ', u.mname, ' ', u.lname) AS user_name, u.suffix AS user_preffix, pte.pt_tracker_id,
                    pte.status, pt.lastseq, f.name AS facility_name, CONCAT(f.street, ', ', f.city, ', ', f.state)
                    AS facility_address, f.phone AS facility_phone, f.email AS facility_email, f.facility_code AS
                    service_vendor, f.facility_npi AS vendor_instance, f.oid AS vendor_api, f.website AS facility_website,
                    f.attn AS facility_logo_email, f.domain_identifier AS facility_logo_wsp, SUBSTRING_INDEX(f.iban, ',', 1) AS latitude,
                    SUBSTRING_INDEX(f.iban, ',', -1) AS longitude
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
	
    $db_patient = (sqlStatement($query));
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
    $query = "SELECT * FROM automatic_notification WHERE type=? ";
    $db_email_msg = sqlFetchArray(sqlStatement($query, [$type]));
    return $db_email_msg;
}

////////////////////////////////////////////////////////////////////
// Function:    cron_InsertNotificationLogEntry
// Purpose: insert log entry in table
////////////////////////////////////////////////////////////////////
function cron_InsertNotificationLogEntry($type, $prow, $db_email_msg)
{
    global $SMS_GATEWAY_USENAME, $SMS_GATEWAY_PASSWORD, $SMS_GATEWAY_APIKEY;
    if ($type == 'SMS') {
        $smsgateway_info = $db_email_msg['sms_gateway_type'] . "|||" . $SMS_GATEWAY_USENAME . "|||" . $SMS_GATEWAY_PASSWORD . "|||" . $SMS_GATEWAY_APIKEY;
    } else {
        $smsgateway_info = $db_email_msg['email_sender'] . "|||" . $db_email_msg['email_subject'];
    }
    $patient_info = $prow['title'] . " " . $prow['fname'] . " " . $prow['mname'] . " " . $prow['lname'] . "|||" . $prow['phone_cell'] . "|||" . $prow['email'];
    $data_info = $prow['pc_eventDate'] . "|||" . $prow['pc_endDate'] . "|||" . $prow['pc_startTime'] . "|||" . $prow['pc_endTime'];
    $sql_loginsert = "INSERT INTO `notification_log` ( `iLogId` , `pid` , `pc_eid` , `sms_gateway_type` , `message` , `email_sender` , `email_subject` , `type` , `patient_info` , `smsgateway_info` , `pc_eventDate` , `pc_endDate` , `pc_startTime` , `pc_endTime` , `dSentDateTime` ) VALUES ";
    $sql_loginsert .= "(NULL , ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $db_loginsert = (sqlStatement(
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
                $prow['pc_eventDate'],
                $prow['pc_endDate'],
                $prow['pc_startTime'],
                $prow['pc_endTime'],
                date("Y-m-d H:i:s")
            ]
        )
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
    $dias = array("Domingo" , "Lunes" , "Martes" , "Miercoles" , "Jueves" , "Viernes" , "Sábado");
    $meses = array("Enero" , "Febrero" , "Marzo" , "Abril" , "Mayo" , "Junio" , "Julio" , "Agosto" , "Septiembre" , "Octubre" , "Noviembre" , "Diciembre");
	$DATE = $dias[date('w' , $dtWrk)]." ".date('d' , $dtWrk)." de ".$meses[date('n' , $dtWrk)-1]. " del ".date('Y' , $dtWrk) ;
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
    $strQuery = "SELECT * FROM notification_settings WHERE type='SMS/Email Settings'";
    $vectNotificationSettings = sqlFetchArray(sqlStatement($strQuery));

    return( $vectNotificationSettings );
}
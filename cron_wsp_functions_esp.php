<?php
/**
 * CRON FUNCTIONS - to use with cron_smd(*) and cron_email backend
 * scripts to notify events
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Larry Lart
 * @copyright Copyright (c) 2008 Larry Lart
 * @copyright Copyright (c) 2023 Luis A. Uriarte <luis.uriarte@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

global $smsgateway_info;
global $patient_info;
global $data_info;
global $gbl_time_zone;
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
function cron_SendWSP($patient_phone, $vBody, $start_date, $end_date, $patient_name, $patient_email, $facility_name, 
                    $facility_address, $facility_phone, $facility_email, $provider, $SERVICE)
{
    $SenderEmail = $GLOBALS['patient_reminder_sender_email'];
    // Luis: No funciona SMS_GATEWAY_USENAME ni SMS_GATEWAY_APIKEY de Globals, 
    // en cambio lo toma de la tabla notification_settings. Los valores se 
    // colocan atraves de Admin/Miscellaneous/Batch Communication Tool
    // Pestaña: SMS/Email Alert Settings (Sin usuario).
    $Instance = $GLOBALS['SMS_GATEWAY_USENAME'];
    $ApiKey = $GLOBALS['SMS_GATEWAY_APIKEY'];
	$url_base = "https://hcd.origen.ar/modules/sms_email_reminder/";
	$url_logo_wsp = $url_base . "logo_wsp.png";
    //$url_logo_wsp = "https://previews.123rf.com/images/redgrphc/redgrphc2207/redgrphc220700462/189349247-dise%C3%B1o-de-vector-de-plantilla-de-logotipo-de-ilustraci%C3%B3n-cruzada-m%C3%A9dica.jpg";
    $todaystamp = gmdate("Ymd\THis\Z");

    //Create unique identifier
    $cal_uid = date('Ymd').'T'.date('His')."-".rand()."@origen.ar";

    //Create ICAL Content (Google rfc 2445 for details and examples of usage)
    $ical_content = 'BEGIN:VCALENDAR
METHOD:REQUEST
PRODID:-//Microsoft Corporation//Outlook 11.0 MIMEDIR//EN
VERSION:2.0
BEGIN:VEVENT
DTSTART;TZID=' . $GLOBALS['gbl_time_zone'] . ':' . dateToCal($start_date) . '
DTEND;TZID=' . $GLOBALS['gbl_time_zone'] . ':' . dateToCal($end_date) . '
LOCATION:' . $facility_address . '
TRANSP:OPAQUE
SEQUENCE:0
UID:' . $cal_uid . '
ORGANIZER;CN=' . $provider . ':mailto:' . $facility_email . '
ATTENDEE;PARTSTAT=ACCEPTED;CN=' . $patient_name . ';EMAIL=' . $patient_email . ':mailto:' . $patient_email . '
CONTACT:' . $facility_name . '\, ' . $facility_phone . '\,' . $facility_email . '
DTSTAMP:' . $todaystamp . '
SUMMARY:Turno en ' . $facility_name . '
DESCRIPTION:' . $vBody . '
URL;VALUE=URI:' . $GLOBALS['online_support_link'] . '
PRIORITY:5
CLASS:PUBLIC
BEGIN:VALARM
TRIGGER:-PT60M
REPEAT:1
DURATION:PT30M
ACTION:DISPLAY
END:VALARM
END:VEVENT
END:VCALENDAR';

    if ($SERVICE == "WaApi") {
        $ChatId = "549" . $patient_phone . "@c.us";
        // Para waapi.app Primero envio Imagen con Texto
        $body_json = json_encode([
        "chatId" => $ChatId,
        "mediaUrl" => $url_logo_wsp,
        //"mediaUrl" => "https://www.buscadorcomercial.com.ar/avisos/logo_478.png",
        "mediaCaption" => $vBody
        ]);

        require_once('../../vendor/autoload.php');

        $client = new \GuzzleHttp\Client();

        $response = $client->request(
        'POST',
        'https://waapi.app/api/v1/instances/' . $Instance . '/client/action/send-media',
        [
            'body' => $body_json,
            'headers' => [
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'authorization' => 'Bearer ' .$ApiKey,
            ],
        ]
        );

        echo $response->getBody();
        
        //Para waapi.app Luego envio archivo icalendar con texto de "Presione...."
        $archivo = "TURNO-" . substr(md5(time()), 0, 8) . ".ics";
        echo $archivo;
        $file_handle = fopen($archivo, 'w+');
        fwrite($file_handle, $ical_content);

        $body_json = json_encode([
            "chatId" => $ChatId,
            "mediaUrl" => "https://www.buscadorcomercial.com.ar/avisos/logo_478.png",
            //"mediaUrl" => $url_base . $archivo,
            "mediaCaption" => $facility_name . ': Presione en el adjunto para verificar su turno. Gracias.'
            ]);
    
            // require_once('../../vendor/autoload.php');
    
            $client = new \GuzzleHttp\Client();
    
            $response = $client->request(
            'POST',
            'https://waapi.app/api/v1/instances/' . $Instance . '/client/action/send-media',
            [
                'body' => $body_json,
                'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'authorization' => 'Bearer ' .$ApiKey,
                ],
            ]
            );
    
        echo $response->getBody();
        
    }    
    
    if ($SERVICE == "UltraMSG") {
        // Para UltraMSG Primero envio Imagen con Texto
        $wsp = "+549" . $patient_phone;
        $params=array(
            'token' => $ApiKey,
            'to' => $wsp,
            //'image' => 'https://www.buscadorcomercial.com.ar/avisos/logo_478.png',
            'image' => $url_logo_wsp,
            'caption' => $vBody
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.ultramsg.com/{$Instance}/messages/image",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_SSL_VERIFYHOST => 0,
              CURLOPT_SSL_VERIFYPEER => 0,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => http_build_query($params),
              CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
              ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
              echo "cURL Error #:" . $err;
            } else {
              echo $response;
            }
    
            // Para UltraMSG Luego envio archivo icalendar con texto: "Presione adjunto..."
            $archivo = "TURNO-" . substr(md5(time()), 0, 8) . ".ics";
            echo $archivo;
            $file_handle = fopen($archivo, 'w+');
            fwrite($file_handle, $ical_content);
    
         $params = array(
            'token' => $ApiKey,
            'to' => $wsp,
            'filename' => $url_base . $archivo,
            //'filename' => 'https://previews.123rf.com/images/redgrphc/redgrphc2207/redgrphc220700462/189349247-dise%C3%B1o-de-vector-de-plantilla-de-logotipo-de-ilustraci%C3%B3n-cruzada-m%C3%A9dica.jpg',
            'document' => 'TURNO.ics',
            'caption' => $facility_name . ': Presione en el adjunto para verificar su turno. Gracias.'
        );
            
        $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.ultramsg.com/{$Instance}/messages/document",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_SSL_VERIFYHOST => 0,
              CURLOPT_SSL_VERIFYPEER => 0,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => http_build_query($params),
              CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
              ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
              echo "cURL Error #:" . $err;
            } else {
              echo $response;
            }
    }
        If (unlink($archivo)) {
            // archivo borrado
           } else {
            // problema al borrar archivo 
        }    

}

////////////////////////////////////////////////////////////////////
// Function:    cron_updateentry
// Purpose: update status yes if alert send to patient
////////////////////////////////////////////////////////////////////
function cron_updateentry($type, $pid, $pc_eid)
{
    $query = "UPDATE openemr_postcalendar_events SET ";
    
    if ($type == 'WSP') {
        $query .= " openemr_postcalendar_events.pc_sendalertsms='YES' ,  openemr_postcalendar_events.pc_apptstatus='WSP' ";
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
   
    if ($type == 'WSP') {
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
                    CONCAT(u.fname, ' ', u.mname, ' ', u.lname) user_name, pte.pt_tracker_id,
                    pte.status, pt.lastseq, f.name AS facility_name, CONCAT(f.street, ', ', f.city, ', ', f.state)
                    AS facility_address, f.phone AS facility_phone, f.email AS facility_email
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
    if ($type == 'WSP') {
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
	$dias = array("Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sábado");
	$meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
	$DATE = $dias[date('w',$dtWrk)]." ".date('d',$dtWrk)." de ".$meses[date('n',$dtWrk)-1]. " del ".date('Y',$dtWrk) ;
    $STARTTIME = date("H:i", $dtWrk);
    $ENDTIME = $prow['pc_endTime'];
    $FACILITY_NAME = $prow['facility_name'];
    $FACILITY_ADDRESS = $prow['facility_address'];
    $FACILITY_PHONE = $prow['facility_phone'];
    $FACILITY_EMAIL = $prow['facility_email'];
    $find_array = array('***NAME***' , '***PROVIDER***' , '***DATE***' , '***STARTTIME***' , '***ENDTIME***', '***FACILITY_NAME***', 
                        '***FACILITY_ADDRESS***', '***FACILITY_PHONE***', '***FACILITY_EMAIL***');
    $replace_array = array($NAME , $PROVIDER , $DATE , $STARTTIME , $ENDTIME, $FACILITY_NAME , $FACILITY_ADDRESS , $FACILITY_PHONE, $FACILITY_EMAIL);
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

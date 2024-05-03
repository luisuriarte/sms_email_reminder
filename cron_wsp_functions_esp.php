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
function cron_SendWSP($patient_phone, $vBody, $start_date, $end_date, $patient_name, $patient_email, $facility_name, 
                    $facility_address, $facility_phone, $facility_email, $provider, $facility_url, $facility_vendor,
                    $facility_instance, $facility_logo, $facility_api)
{

    $Instance = $facility_instance;
    $ApiKey = $facility_api;
	$url_base = $facility_url . "/modules/sms_email_reminder/";
	$url_logo_wsp = $url_base . $facility_logo;
    $todaystamp = gmdate("Ymd\THis\Z");
    //$zone = ($GLOBALS['gbl_time_zone'] ?? null);
    $zone = "America/Argentina/Buenos_Aires";

    //Create unique identifier
    $cal_uid = date('Ymd').'T'.date('His')."-".rand().substr($facility_url, 8);

    //Create ICAL Content (Google rfc 2445 for details and examples of usage)
    $ical_content = 'BEGIN:VCALENDAR
METHOD:REQUEST
PRODID:-//Microsoft Corporation//Outlook 11.0 MIMEDIR//EN
VERSION:2.0
BEGIN:VEVENT
DTSTART;TZID=' . $zone . ':' . dateToCal($start_date) . '
DTEND;TZID=' . $zone . ':' . dateToCal($end_date) . '
LOCATION:' . $facility_address . '
TRANSP:OPAQUE
SEQUENCE:0
UID:' . $cal_uid . '
ORGANIZER;CN=' . $facility_name . ':mailto:' . $facility_email . '
ATTENDEE;PARTSTAT=ACCEPTED;CN=' . $patient_name . ';EMAIL=' . $patient_email . ':mailto:' . $patient_email . '
CONTACT:' . $facility_name . '\, ' . $facility_phone . '\,' . $facility_email . '
DTSTAMP:' . $todaystamp . '
SUMMARY:Turno en ' . $facility_name . '
DESCRIPTION:' . $vBody . '
URL;VALUE=URI:' . $facility_url . '
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
$archivo = "TURNO-" . substr(md5(time()), 0, 8) . ".ics";
$file_handle = fopen($archivo, 'w+');
fwrite($file_handle, $ical_content);

    if ($facility_vendor == "WaApi") {
        $ChatId = "549" . $patient_phone . "@c.us";
        // Para waapi.app Primero envio Imagen con Texto
        $body_json = json_encode([
        "chatId" => $ChatId,
        "mediaUrl" => $url_logo_wsp,
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
        $body_json = json_encode([
            "chatId" => $ChatId,
            "mediaUrl" => $url_base . $archivo,
            "mediaName" => "TURNO.ics",
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
    
    if ($facility_vendor == "UltraMSG") {
        // Para UltraMSG Primero envio Imagen con Texto
        $wsp = "+549" . $patient_phone;
        $params=array(
            'token' => $ApiKey,
            'to' => $wsp,
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
         $params = array(
            'token' => $ApiKey,
            'to' => $wsp,
            'filename' => 'TURNO.ics',
            'document' => $url_base . $archivo,
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
            echo " Archivo borrado";
           } else {
            echo " Problema al borrar archivo"; 
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

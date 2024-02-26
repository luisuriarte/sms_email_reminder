<?php

/*
 * Purpose: to be run by cron every hour, look for appointments
 * in the pre-notification period and send an email reminder
 *
 * @package OpenEMR
 * @author Larry Lart
 * @copyright Copyright (c) 2008 Larry Lart
 * @copyright Copyright (c) 2022 Luis A. Uriarte <luis.uriarte@gmail.com>
 * @link https://www.open-emr.org
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

setlocale(LC_ALL,'es-ES', 'Spanish_Spain', 'Spanish' );

global $argc;

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
require_once("cron_email_functions_esp.php");

// check command line for quite option
$bTestRun = isset($_REQUEST['dryrun']) ? 1 : 0;
if ($argc > 1 && $argv[2] == 'test') {
    $bTestRun = 1;
}

$TYPE = "Email";
$CRON_TIME = 5;

// set cron time (time to event ?) - todo extra tests
$vectNotificationSettings = cron_GetNotificationSettings();
$CRON_TIME = $vectNotificationSettings['Send_Email_Before_Hours'];

$check_date = date("Y-m-d", mktime(date("h") + $EMAIL_NOTIFICATION_HOUR, 0, 0, date("m"), date("d"), date("Y")));

// get data from automatic_notification table
$db_email_msg = cron_getNotificationData($TYPE);

// get patient data for send alert
$db_patient = cron_getAlertpatientData($TYPE);
echo "<br />Total " . count($db_patient) . " Registros Encontrados\n";
for ($p = 0; $p < count($db_patient); $p++) {
    $prow = $db_patient[$p];
    $patient_name = $prow['fname'] . " " . $prow['mname'] . " " . $prow['lname'];
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
    $provider_name = $prow['user_name'];
    $provider_preffix = $prow['user_preffix'];
    $logo_email = $prow['facility_logo_email'];

    $app_time_hour = round($app_time / 3600);
    $curr_total_hour = round(time() / 3600);

    $remaining_app_hour = round($app_time_hour - $curr_total_hour);
    $remain_hour = round($remaining_app_hour - $EMAIL_NOTIFICATION_HOUR);

    $strMsg = "\n========================" . $TYPE . " || " . date("Y-m-d H:i:s") . "=========================";
    $strMsg .= "\nSEND NOTIFICATION BEFORE:" . $EMAIL_NOTIFICATION_HOUR . " || CRONJOB RUN EVERY:" . $CRON_TIME . " || APPDATETIME:" . $app_date . " || REMAINING APP HOUR:" . ($remaining_app_hour) . " || SEND ALERT AFTER:" . ($remain_hour);

    if ($remain_hour >= -($CRON_TIME) &&  $remain_hour <= $CRON_TIME) {
        
        //set message
        $db_email_msg['message'] = cron_setmessage($prow, $db_email_msg);
        
        // send mail to patinet
        cron_SendMail(
            $patient_email,
            $prow['email_direct'],
            $db_email_msg['email_subject'],
            $db_email_msg['message'],
            $app_date,
            $app_end_date,
            $patient_name,
            $facility_name,
            $facility_address,
            $facility_phone,
            $facility_email,
            $facility_url,
            $provider_name,
            $logo_email
        );

        // insert entry in notification_log table
        cron_InsertNotificationLogEntry($TYPE, $prow, $db_email_msg);
    
        //update entry >> pc_sendalertemail='Yes'
        cron_updateentry($TYPE, $prow['pid'], $prow['pc_eid']);

        // Update patient_tracker table and insert a row in patient_tracker_element table
        manage_tracker_status($prow['pc_eventDate'], $prow['pc_startTime'], $eid, $pid, $user = 'Automático', $status = 'EMAIL', $room = '', $enc_id = '');

        $strMsg .= " || ALERT SENT SUCCESSFULLY TO " . $prow['email'];
        $strMsg .= "\n" . $patient_info . "\n" . $smsgateway_info . "\n" . $data_info . "\n" . $db_email_msg['message'];
    }

    WriteLog($strMsg);

    // larry :: get notification data again - since was updated by cron_updateentry
    $db_email_msg = cron_getNotificationData($TYPE);
}

//sqlClose();
?>

<html>
<head>
<title>Cronjob - Notificaciones por E-Mail</title>
</head>
<body>
    <center>
    </center>
</body>
</html>

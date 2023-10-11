<?php

/*
 * Purpose: to be run by cron every hour, look for appointments
 * in the pre-notification period and send an email reminder
 *
 * @package OpenEMR
 * @author Larry Lart
 * @copyright Copyright (c) 2008 Larry Lart
 * @copyright Copyright (c) 2023 Luis A. Uriarte <luis.uriarte@gmail.com>
 * @link https://www.open-emr.org
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 **/

// comment below exit if plan to use this script
//exit;

setlocale(LC_ALL, 'es-ES', 'Spanish_Spain', 'Spanish');

if (php_sapi_name() !== 'cli') {
    exit;
}

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
    // $_SERVER[‘HTTP_HOST’] = ‘localhost’;
    $_SERVER['HTTP_HOST'] = 'localhost';

    $ignoreAuth = true;
}
require_once(__DIR__ . "/../../interface/globals.php");
require_once(__DIR__ . "/../../library/appointments.inc.php");
require_once(__DIR__ . "/../../library/patient_tracker.inc.php");
require_once("cron_wsp_functions_esp.php");

// check command line for quite option
$bTestRun = isset($_REQUEST['dryrun']) ? 1 : 0;
if ($argc > 1 && $argv[2] == 'test') {
    $bTestRun = 1;
}

$TYPE = "SMS";
$CRON_TIME = 5;

$curr_date = date("Y-m-d");
$curr_time = time();
$check_date = date("Y-m-d", mktime(date("h") + $SMS_NOTIFICATION_HOUR, 0, 0, date("m"), date("d"), date("Y")));

// larry :: move this in the loop to keep it fresh - perhaps try to use it without change
// it's content - to do latter
$db_email_msg = cron_getNotificationData($TYPE);

// get notification settings
$vectNotificationSettings = cron_GetNotificationSettings();
$SMS_GATEWAY_USENAME = $vectNotificationSettings['SMS_gateway_username'];
$SMS_GATEWAY_PASSWORD = $vectNotificationSettings['SMS_gateway_password'];
$SMS_GATEWAY_APIKEY = $vectNotificationSettings['SMS_gateway_apikey'];
// set cron time (time to event ?) - todo extra tests
$CRON_TIME = $vectNotificationSettings['Send_SMS_Before_Hours'];

// create sms object
//$mysms = new sms($SMS_GATEWAY_USENAME, $SMS_GATEWAY_PASSWORD, $SMS_GATEWAY_APIKEY);

$db_patient = cron_getAlertpatientData($TYPE);
echo "\n<br />Total " . text(count($db_patient)) . " Records Found";

// for every event found
for ($p = 0; $p < count($db_patient); $p++) {
    $prow = $db_patient[$p];

    $app_date = $prow['pc_eventDate'] . " " . $prow['pc_startTime'];
    $app_end_date = $prow['pc_eventDate'] . " " . $prow['pc_endTime'];
    $app_time = strtotime($app_date);

    $app_time_hour = round($app_time / 3600);
    $curr_total_hour = round(time() / 3600);

    $remaining_app_hour = round($app_time_hour - $curr_total_hour);
    $remain_hour = round($remaining_app_hour - $SMS_NOTIFICATION_HOUR);

    // build log message
    $strMsg = "\n========================" . $TYPE . " || " . date("Y-m-d H:i:s") . "=========================";
    $strMsg .= "\nSEND NOTIFICATION BEFORE:" . $SMS_NOTIFICATION_HOUR . " || CRONJOB RUN EVERY:" . $CRON_TIME . " || APPDATETIME:" . $app_date . " || REMAINING APP HOUR:" . ($remaining_app_hour) . " || SEND ALERT AFTER:" . ($remain_hour);

    // check in the interval
    if ($remain_hour >= -($CRON_TIME) &&  $remain_hour <= $CRON_TIME) {
        // insert entry in notification_log table
        cron_InsertNotificationLogEntry($TYPE, $prow, $db_email_msg);

        //set message
        $db_email_msg['message'] = cron_setmessage($prow, $db_email_msg);

        // send sms to patinet - if not in test mode
        if ($bTestRun == 0) {
            cron_SendWSP(
                $prow['phone_cell'],
                $db_email_msg['message'],
                $app_date,
                $app_end_date
            );
        }

        //update entry >> pc_sendalertsms='Yes'
        cron_updateentry($TYPE, $prow['pid'], $prow['pc_eid']);

        $strMsg .= " || ALERT SENT SUCCESSFULLY TO " . $prow['phone_cell'];
        $strMsg .= "\n" . $patient_info . "\n" . $smsgateway_info . "\n" . $data_info . "\n" . $db_email_msg['message'];
    }

    // write logs for every reminder sent
    WriteLog($strMsg);

    // larry :: update notification data again - todo :: fix change in cron_updateentry
    $db_email_msg = cron_getNotificationData($TYPE);
}

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

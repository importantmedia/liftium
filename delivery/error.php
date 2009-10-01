<?php
require dirname(__FILE__) . '/../includes/Framework.php';

// Parse incoming
$type = Framework::getRequestVal("type", "UnknownType");
$pubid = Framework::getRequestVal("pubid", "UnknownPubid");
$browser = Framework::getBrowser();
$ip = Framework::getIp();
$msg = Framework::getRequestVal("msg");
if (preg_match("/error on line #([0-9]+) of (https*:\/\/[^ :]+))/", $msg, $match)){
	$line = $match[1];
	$url = trim($match[2]);
} else {
	$line = "-1";
	$url = "UnkwownUrl";
}

// Debug
if (!empty($_GET['debug'])){
	echo "<pre>";
	print_r($_GET);
	echo "type = $type\n";
	echo "pubid = $pubid\n";
	echo "browser = $browser\n";
	echo "ip = $ip\n";
	echo "msg = $msg\n";
	echo "</pre>";

	phpinfo(INFO_VARIABLES);
}

$logit = true;
$statit = true;
$emailto = array("nick@liftium.com");


// Ignore certain noisy messages that aren't our fault
$ignores = array(
	"translate.google",
	"quantserve",
	"urchin",
	"greasemonkey",
	"Error loading script" // Will happen if user interrupts transfer
);
// Triage
if (preg_match("/(" . implode("|", $ignores) . ")/", $message)){
	$logit = false;
	$statit = false;
	$emailto = false;
} else if (!strstr($url, "liftium.com")){
	// Not our site. Log it, no e-mail and no stats
	$statit = false;
	$emailto = false;
}

// Create message
$message = "$ip|Pubid:$pubid|$msg|" . @$_SERVER['HTTP_REFERER'] . "|$browser";

// Log the message
if ($logit) {
	// Write to a log file
	ini_set('error_log', '/home/tempfiles/10days/jserrors.' . @$_GET['type'] . '.' . date('Y-m-d'));
	error_log($message);
}

// Send e-mail
if (!empty($emailto)){
	mail(implode(",", $emailto), "Liftium Javascript Error - @{$_GET['type']}", $message);
}

// Record in memcache for stats
if ($statit) {
	EventRecorder::record(array('JavascriptErrors', Framework::getBrowser()), "minute");
	EventRecorder::record(array('JavascriptErrors'), "minute");
	if (@$_GET['type'] == 'tag') {
		EventRecorder::record(array('TagErrors'), "minute");
	} else {
			EventRecorder::record(array('JavascriptErrors_' . $_GET['type']), "minute");
	}
}
?>

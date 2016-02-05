<?php
define("DONT_RUN_NPRESS_APP", true);
require_once "index.php";

$url = "https://lists.openstreetmap.org/pipermail/talk-cz/2016-February.txt";
$mbox = file_get_contents($url);
if (!$mbox) { 
    $url .= ".gz";
    $mbox = gzdecode(file_get_contents($url));
}
//$url = "./2016-January.txt";
//$mbox = file_get_contents($url);

error_reporting(0);

dibi::query('DELETE FROM mailarchive WHERE YEAR(`date`) = 2016 AND MONTH(`date`) = 2');


foreach (preg_split('/\nFrom .+?\n/', $mbox) as $r) {

    $e = new PlancakeEmailParser($r);

    $from = $e->getHeader('from');
    $name = "";
    if(preg_match("/(.*) at (.*) \((.*)\)/", $from, $matches)){
        $from = "$matches[1]@$matches[2]";
        $name = $matches[3];
    }

    $subject = $e->getSubject();
    $subject = preg_replace('/^\[Talk-cz\] */', '', $subject);

    dibi::query("INSERT INTO mailarchive", array(
        "msgid" => $e->getHeader('message-id'),
        "replyid" => $e->getHeader('in-reply-to'),
        "date" => date("Y-m-d H:i:s", strtotime($e->getHeader('date'))),
        "from" => $from,
        "name" => $name,
        "subject" => $subject,
        "text" => $e->getPlainBody(),
    ));
    echo ".";

}


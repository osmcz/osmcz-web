<?php

define("DONT_RUN_NPRESS_APP", true);
require_once "index.php";
error_reporting(0);

if(!function_exists('gzdecode')){
  function gzdecode($data){
    return gzinflate(substr($data, 10, -8));
  }
}

// get current mbox archive
$url = "https://lists.openstreetmap.org/pipermail/talk-cz/" . date('Y-F') . ".txt";
$mbox = file_get_contents($url);
if (!$mbox) {
    $url .= ".gz";
    $mbox = gzdecode(file_get_contents($url));
}

dibi::query('DELETE FROM mailarchive WHERE YEAR(`date`) = %i', date('Y'), ' AND MONTH(`date`) = %i', date('n'));
insertMailsFromMbox($mbox);

/*/

// fetch all from 2007-1 til now
for ($y = 2007; $y <= date('Y'); $y++) {
    for ($m = 1; $m <= 12; $m++) {

        $url = "https://lists.openstreetmap.org/pipermail/talk-cz/" . date('Y-F', strtotime("$y-$m-10")) . ".txt.gz";
        echo "<hr>$url<br>";
        $mbox = gzdecode(file_get_contents($url));
        insertMailsFromMbox($mbox);
        flush();
        sleep(1);

        //TODO encode corectly mails in CP1250

        if ($y == date('Y') && $m == date('n')) //current month of current year
            break;
    }
}
*/


/**
 * @param $mbox plain text format
 */
function insertMailsFromMbox($mbox)
{
    if (!$mbox) {
        echo "Blank mbox. End.";
        return;
    }

    foreach (preg_split('/\nFrom .+?\n/', $mbox) as $r) {

        $e = new PlancakeEmailParser($r);

        $from = $e->getFieldDecoded('from');
        $name = "";
        if (preg_match("/(.*) at (.*) \((.*)\)/", $from, $matches)) {
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
}

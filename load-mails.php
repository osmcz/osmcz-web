<?php

// WARNING: needs extension=php_imap.dll .. otherwise UTF encoded strings are wrong

define("DONT_RUN_NPRESS_APP", true);
require_once "index.php"; //db connection
error_reporting(0);

if (!function_exists('gzdecode')) {
  function gzdecode($data)
  {
    return gzinflate(substr($data, 10, -8));
  }
}

if(isset($_GET['when']) && preg_match('/^[12][90][0-9][0-9]-[0-1][0-9]$/', $_GET['when'])){
  //import for requested month
  //can be used to import missing emails (waiting for moderator aproval on end of month
  // run as: https://openstreetmap.cz/load-mails.php?when=2020-11
  $url = "https://lists.openstreetmap.org/pipermail/talk-cz/" . date('Y-F', strtotime($_GET['when']."-10")) . ".txt";
  echo "Requested import for ".$_GET['when']." from $url\n";
} else {
  // get current mbox archive
  $url = "https://lists.openstreetmap.org/pipermail/talk-cz/" . date('Y-F') . ".txt";
}

  $mbox = file_get_contents($url);
  if (!$mbox) {
    $url .= ".gz";
    $mbox = gzdecode(file_get_contents($url));
  }

  insertMailsFromMbox($mbox);

/*/
set_time_limit(10*60);

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
//*/

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
    if (
      dibi::fetch(
        "SELECT 1 FROM mailarchive WHERE msgid = %s",
        $e->getHeader('message-id')
      )
    ) {
      echo "msgid exists skipping<br>";
      continue;
    }

    $from = $e->getFieldDecoded('from');
    $name = "";
    if (preg_match("/(.*) [an][ta] (.*) \((.*)\)/", $from, $matches)) {
      $from = "$matches[1]@$matches[2]";
      $name = $matches[3];
    }

    $subject = $e->getSubject();
    $subject = preg_replace('/^\[[Tt]alk-cz\] */', '', $subject);

    // find conversation
    $cid = dibi::fetchSingle(
      "
          SELECT conversationid
          FROM mailarchive
          WHERE msgid = %s",
      $e->getHeader('in-reply-to'),
      "
            OR BINARY subject = %s",
      $subject
    );

    if (!$cid) {
      $cid = dibi::fetchSingle("SELECT max(conversationid)+1 FROM mailarchive");
    }
    if (!$cid) {
      $cid = 1;
    }

    if (
      $from == "=?UTF-8?Q?Petr_Mor=c3=a1vek_ (=?UTF-8?Q?Petr_Mor=c3=a1vek_)"
    ) {
      // mailman broken
      $name = "Petr Morávek [Xificurk]";
      $from = "petr@pada.cz";
    }

    try {
      dibi::query("INSERT INTO mailarchive", array(
        "msgid" => $e->getHeader('message-id'),
        "replyid" => $e->getHeader('in-reply-to'),
        "date" => date("Y-m-d H:i:s", strtotime($e->getHeader('date'))),
        "from" => $from,
        "name" => $name,
        "subject" => $subject,
        "text" => $e->getPlainBody(),
        "conversationid" => $cid
      ));
    } catch(DibiException $e) {
      if ($e->getCode() === 1062) {
        echo "DUP";
      }
      else {
        throw $e;
      }
    }

    echo ".";
  }
}

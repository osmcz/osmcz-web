<?php

FileTemplate::extensionMethod('timeago', function (
  $that,
  $s,
  $format = false,
  $formatAfterDays = 15
) {
  return Helpers::timeAgoInWords($s, $format, $formatAfterDays);
});
FileTemplate::extensionMethod('modified', function ($that, $s) {
  return $s . "?" . dechex(filemtime(WWW_DIR . $s));
});
FileTemplate::extensionMethod('talkMailBody', function ($that, $s) {
  return Helpers::talkMailBody($s);
});
FileTemplate::extensionMethod('czechMonth', function ($that, $num) {
  $mesicetxt = array(
    1 => "Leden",
    "Únor",
    "Březen",
    "Duben",
    "Květen",
    "Červen",
    "Červenec",
    "Srpen",
    "Září",
    "Říjen",
    "Listopad",
    "Prosinec"
  );
  return $mesicetxt[$num];
});
FileTemplate::extensionMethod('gravatar', function ($that, $mail) {
  return "https://www.gravatar.com/avatar/" .
    md5(strtolower($mail)) .
    "?s=32&d=mm";
});
FileTemplate::extensionMethod('talkstub', function ($that, $mail) {
  return strstr($mail, '@', true) . "-" . substr(md5($mail), -5);
});
FileTemplate::extensionMethod('excerpt', function ($that, $text, $query) {
  return Helpers::excerpt($text, $query);
});

// https://stackoverflow.com/questions/34115174/error-related-to-only-full-group-by-when-executing-a-query-in-mysql
// https://stackoverflow.com/questions/23921117/disable-only-full-group-by
dibi::query(
  "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
);

if (isset($_SERVER['HTTPS'])) {
  Route::$defaultFlags = Route::SECURED;
}

// add some osmcz routes before everything
$oldFrontRouter = $container->router[1];
$container->router[1] = new RouteList('Front');
$container->router[1][] = new Route(
  'talkcz/c<id [0-9]+>',
  'Talkcz:conversation',
  isset($_SERVER['HTTPS']) ? Route::SECURED : false
);
$container->router[1][] = new Route(
  'talkcz/<month [0-9]{6}>',
  'Talkcz:default',
  isset($_SERVER['HTTPS']) ? Route::SECURED : false
);
$container->router[1][] = new Route(
  'talkcz/<stub .+-[0-9a-f]{5}>',
  'Talkcz:author',
  isset($_SERVER['HTTPS']) ? Route::SECURED : false
);

$container->router[1][] = new Route(
  '<osmtype (node|way|relation)>/<osmid [0-9]+>',
  array(
    //osmcz JS URLs
    'presenter' => 'Pages',
    'action' => 'default',
    'id_page' => 1
  )
);
foreach ($oldFrontRouter as $r) {
  $container->router[1][] = $r;
}

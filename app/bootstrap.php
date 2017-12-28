<?php

FileTemplate::extensionMethod('timeago', function ($that, $s) {
    return Helpers::timeAgoInWords($s);
});

FileTemplate::extensionMethod('modified', function ($that, $s) {
    return $s . "?". dechex(filemtime(WWW_DIR.$s));
});

FileTemplate::extensionMethod('talkMailBody', function ($that, $s) {
    return Helpers::talkMailBody($s);
});

FileTemplate::extensionMethod('czechMonth', function ($that, $num) {
    $mesicetxt = array(1=>"Leden","Únor","Březen","Duben","Květen", "Červen", "Červenec", "Srpen","Září","Říjen","Listopad","Prosinec");
    return $mesicetxt[$num];
});

FileTemplate::extensionMethod('gravatar', function ($that, $mail) {
   return "https://www.gravatar.com/avatar/" . md5(strtolower($mail)) . "?s=32&d=mm";
});




Route::$defaultFlags = Route::SECURED;

// add some osmcz-JS routes before everything
$oldFrontRouter = $container->router[1];
$container->router[1] = new RouteList('Front');
$container->router[1][] = new Route('<osmtype (node|way|relation)>/<osmid [0-9]+>', array( //default route
        'presenter' => 'Pages',
        'action' => 'default',
        'id_page' => 1,
    ));
foreach($oldFrontRouter as $r) $container->router[1][] = $r;


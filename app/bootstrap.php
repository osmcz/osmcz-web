<?php

FileTemplate::extensionMethod('timeago', function ($that, $s) {
    return Helpers::timeAgoInWords($s);
});

FileTemplate::extensionMethod('modified', function ($that, $s) {
    return $s . "?". dechex(filemtime(WWW_DIR.$s));
});


$oldFrontRouter = $container->router[1];
$container->router[1] = new RouteList('Front');
$container->router[1][] = new Route('<osmtype (node|way|relation)>/<osmid [0-9]+>', array( //default route
        'presenter' => 'Pages',
        'action' => 'default',
        'id_page' => 1, //TODO default page from config (but matched only when '/' page missing)
    ), isset($_SERVER['HTTPS']) ? Route::SECURED : false);
foreach($oldFrontRouter as $r) $container->router[1][] = $r;

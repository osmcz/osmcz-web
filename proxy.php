<?php


$url = "http://" . substr($_SERVER["REQUEST_URI"], strlen("/proxy.php/"));

//fetch
$content = file_get_contents($url, false, stream_context_create(array(
    'http' => array(
        'header' => "User-agent: osmcz-https-proxy (Please enable https on your site, so we can offer your osm project directly. Thanks openstreetmap.cz)\r\n"
    )
)));

foreach ($http_response_header as $r)
    if (!preg_match("~^(HTTP/1|Date|Server)~", $r))
        header($r);
header('Server: openstreetmap.cz-proxy');

echo $content;

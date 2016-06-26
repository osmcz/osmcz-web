<?php

//from http://www.ajax-cross-origin.com/how.html#js-how-it-works

$url = (isset($_GET['url'])) ? $_GET['url'] : false;
if(!$url) exit;

//$referer = (isset($_SERVER['HTTP_REFERER'])) ? strtolower($_SERVER['HTTP_REFERER']) : false;
//$is_allowed = $referer && strpos($referer, strtolower($_SERVER['SERVER_NAME'])) !== false;
$is_allowed = preg_match("~^https?://~", $url);

$json = ($is_allowed) ? utf8_encode(file_get_contents($url)) : 'You are not allowed to use this proxy!';

//TODO add only rawgit, osm.cz, localhosts, etc
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');


$callback = (isset($_GET['callback'])) ? $_GET['callback'] : false;
if($callback){
	$jsonp = "$callback($json)";
	header('Content-Type: application/javascript');
	echo $jsonp;
	exit;
}
echo $json;

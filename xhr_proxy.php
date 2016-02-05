<?php
$url = (isset($_GET['url'])) ? $_GET['url'] : false;
if(!$url) exit;

header('Content-Type: application/json; charset=utf-8');

$referer = (isset($_SERVER['HTTP_REFERER'])) ? strtolower($_SERVER['HTTP_REFERER']) : false;
$is_allowed = $referer && strpos($referer, strtolower($_SERVER['SERVER_NAME'])) !== false;

$json = ($is_allowed) ? utf8_encode(file_get_contents($url)) : 'You are not allowed to use this proxy!';

$callback = (isset($_GET['callback'])) ? $_GET['callback'] : false;
if($callback){
	$jsonp = "$callback($json)";
	header('Content-Type: application/javascript');
	echo $jsonp;
	exit;
}
echo $json;

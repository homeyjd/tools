<?php

if (!defined('HANDLER_PRESENT'))
	define('HANDLER_PRESENT', true);
else
	return;

if (isset($_GET['bare'])) return;

/*********** CONSTANTS **********/
define('DS', DIRECTORY_SEPARATOR);
define('LIBS', dirname(__FILE__).DS);
define('WWW_ROOT', dirname(dirname(__FILE__)));

/*********** INCLUDES ***********/
require(LIBS . 'functions.php');
define('START_TIME', getmicrotime());

/*********** DEFAULTS ***********/
require(LIBS . 'config.app.php');
define_once('ERROR404', 'libs/404.php');
define_once('ELEMENT_DIR', dirname(__FILE__));
define_once('TEMPLATE_ROOT', dirname(dirname(__FILE__)));

//uses('mysqlconnector');


/*********** DO NOT EDIT BELOW THIS LINE **************/

// For generating absolute URI's
$base = '';
$doc_root = preg_replace("/(\\/|\\\\)+/", DS, $_SERVER['DOCUMENT_ROOT']);
if (($pos = strpos(WWW_ROOT, $doc_root)) !== false) {
	$base = str_replace($doc_root, '', WWW_ROOT);
}
$base = preg_replace("/(\\/|\\\\)+/", '/', $base);
$base = preg_replace("/\\/$/",'',$base);

// For in-site relative links
$here = $_SERVER['PHP_SELF'];


// Find the URL from GET
if (!empty($_GET['url'])) {
	$url = $_GET['url'];
}
// else find URL from SCRIPT
elseif (!empty($_SERVER['REQUEST_URI'])) {
	$url = $_SERVER['REQUEST_URI'];
	$url = str_replace($base, '', $url);
	if (($pos=strrpos($url,'?'))!==false)
		$url = substr($url, 0, $pos);
}
// else use home page
else {
	$url = '/index.html';
}

$url_path = preg_replace('/(\\\\|\\/)+/', DS, TEMPLATE_ROOT . DS . $url);
$here = str_replace(WWW_ROOT, '', $url_path);
$here = preg_replace('/(\\\\|\\/)+/', '/', $here);

// Find the file
if (!is_file($url_path))
	$url_path .= ((substr($url_path,-1)==DS) ? '' : DS) . 'index.php';
if (!is_file($url_path))
	$url_path = substr( $url_path, 0, -9 ) . 'index.html'; // cut off 'index.php';
// 404
if (!is_file($url_path))
	$url_path = TEMPLATE_ROOT .DS. ERROR404;

// Include the path
ob_start();
include($url_path);
$content_for_layout = ob_get_clean();

// Include the layout and exit
if (!isset($layout) && !empty($default_layout))
	$layout = $default_layout . '.php';
if (!empty($layout))
	include($layout);
else
	echo $content_for_layout;
die();

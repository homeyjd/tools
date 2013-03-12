<?php

if (!empty($title)) $title_for_layout =& $title;
$title_for_layout = (empty($title_for_layout)) ? 'Home' : $title_for_layout;

// Menu structure
$page_nav = array(
	array(
		'link'	=> '/marketing.html',
		'text'	=> 'Marketing'
	),
	array(
		'link'	=> '/development.html',
		'text'	=> 'Profile'
	),
	array(
		'link'	=> '/green.html',
		'text'	=> 'Green<span class="dark">/</span>Ecological',
		'class'	=> 'green'
	),
	array(
		'link'	=> '/current.html',
		'text'	=> 'Current Projects'
	),
	array(
		'link'	=> '/investors.html',
		'text'	=> 'Investors',
	),
	array(
		'link'	=> '/contact.html',
		'text'	=> 'Contact Us',
		'class'	=> 'last'
	)
);

// Find active menu item
foreach ($page_nav as $i => $a) {
	if (strpos($here, $a['link']) !== false) {
		$page_nav[$i]['active'] = 1;
		break;
	}
}

function page_nav ($menu) {
	global $base;
	echo "\n<ul class=\"menu\">";
	foreach ($menu as $i) {
		ensure($i, array('link','text','active','class'));
		extract($i);
		$active  = ($active) ? 'active' : '';
		$classes = (!empty($class)) ? "$class $active" : $active;
		$class = (!empty($classes)) ? ' class="' . trim($classes) . '"' : '';
		$link = (strpos($link,'/')==0) ? $base.$link : $link;
		echo "\n<li{$class}><a href=\"{$link}\">{$text}</a></li>";
	}
	echo "\n</ul>";
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?=$title_for_layout?> ~ Visions for the future</title>
<link rel="stylesheet" href="<?=$base?>/styles.css" />
<link rel="icon" href="<?=$base?>/favicon.ico" type="image/vnd.microsoft.icon" /> 
<link rel="shortcut icon" href="<?=$base?>/favicon.ico" type="image/vnd.microsoft.icon" />
<script type="text/javascript" src="<?=$base?>/site.js"></script>
<?=(empty($header))?'':$header?>
</head>
<body>
<div id="container">
	
<div id="header">
	<h1 class="title"><a href="<?=$base?>/">Development &amp; Marketing Partners</a></h1>
<?php 
if (!isset($menuStyle) || $menuStyle === false || $menuStyle == 'horizontal')
	page_nav($page_nav);
?>
</div>

<?=$content_for_layout?>

</div>

<div id="footer">
	<div class="container">
		<div class="left">
			<p>Content &copy;2008, All Rights Reserved</p>
			<p>Development &amp; Marketing Partners | <a href="<?=$base?>/contact.html">Contact us</a></p>
		</div>
		<div class="right">
			<p class="small">toll free</p>
			<p class="big">1.800.367.1984</p>
		</div>
	</div>
</div>

</body>
</html>
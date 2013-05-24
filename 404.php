<?php
	if (!headers_sent()) {
		header('HTTP/1.0 404 Not Found');
	}
?><!DOCTYPE html> 
<html lang="en" dir='ltr'> 
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
<meta name="keywords" content="web design, event production, ecommerce, application development, business management" />
<meta name="description" content="Hello there, I'm Jesse. I help people find, develop, implement, and use technology." />
<link rel="shortcut icon" href="favicon.png" type="image/png"/>
<link rel="icon" href="favicon.png" type="image/png"/>
<title>Jesse Decker ~ 404</title>
<style type="text/css">
html, body { margin: 0; padding: 0; }
html { font-family: "Lucida Grande", Trebuchet MS, Arial, Verdana; font-size: 62.5%; }
body {
	font-size: 1.4em;
	line-height: 1.8em;
	letter-spacing: -0.03em;
	text-align: center;
	}
a { color: #49d1f3; text-decoration: underline; padding: .2em .3em; margin: -.1em -.2em -.6em -.3em; }
a:active { position:relative; top:1pt; }
a:hover { color: #ffffff; border-bottom: none; background: #49d1f3; text-decoration: none; text-shadow: 0px 1px 1px #232525; }
*:focus { outline: none; }

.clearfix { *zoom: 1; }
.clearfix:before, .clearfix:after { display: table; line-height: 0; content: ""; }
.clearfix:after { clear: both; }

#quote { text-align:left; background: #ebebeb; color: #191815; text-shadow: 0px 1px 1px #fff; position: relative; box-shadow: 0 1px 4px rgba(0,0,0,.6); border-radius:8px; -moz-border-radius: 8px; -webkit-border-radius: 8px; border: 1px solid #49d1f3; }
#quote::after {
	background-color: #ebebeb;
	box-shadow: 2px 2px 2px 0 rgba(0,0,0,.6);
	content: "\00a0";
	display: block;
	height: 16pt;
	width: 16pt;
	right: 30%;
	position: absolute;
	transform: rotate( 45deg );
	-webkit-transform: rotate(45deg);
	bottom: -8pt;
	border-right: 1px solid #49d1f3;
	border-bottom: 1px solid #49d1f3;
	}
#author { text-align: right; width:90%; margin:2em auto 6em; }
h1 { margin:0; border-bottom: 1px solid #fff; padding:16pt 24pt; position:relative; }
h1 { border-top-left-radius: 8px; border-top-right-radius: 8px; -moz-border-top-left-radius: 8px; -moz-border-top-right-radius: 8px; -webkit-border-top-left-radius: 8px; -webkit-border-top-right-radius: 8px; }
h1::after { content: ''; position: absolute; bottom: 0; height: 1px; width: 100%; border-bottom: 1px solid #aaa; left: 0; }
h3 { padding:20pt 24pt; margin:0; }

@media screen {
	body { background: #3a3833; color: #f3f8f8; text-shadow: 0px 1px 2px #232525; }
	h1 { 
		background-image: linear-gradient(bottom, rgb(235,235,235) 24%, rgb(250,250,250) 100%);
		background-image: -o-linear-gradient(bottom, rgb(235,235,235) 24%, rgb(250,250,250) 100%);
		background-image: -moz-linear-gradient(bottom, rgb(235,235,235) 24%, rgb(250,250,250) 100%);
		background-image: -webkit-linear-gradient(bottom, rgb(235,235,235) 24%, rgb(250,250,250) 100%);
		background-image: -ms-linear-gradient(bottom, rgb(235,235,235) 24%, rgb(250,250,250) 100%);
		background-image: -webkit-gradient(linear,left bottom,left top,color-stop(0.24, rgb(235,235,235)),color-stop(1, rgb(250,250,250)));
	}
}

@media all and (min-width: 35em) {
	#quote { width:34em; margin:6em auto 0;  }
	#author { width:32em;  }
}

@media all and (max-width: 34.99em) {
	#quote { margin: 1.5em 1.5em 0; }
}
</style>
</head>
<body>
<div id="quote" class="clearfix">
	<h1>Oops.</h1>
	<h3>I can't find that page. Maybe try <a href="/">the home page</a>.</h3>
</div>
<div id="author">This web server, managed by <a id="mail-link" href="javascript:;">Jesse</a></div>
<script type="text/javascript">
document.getElementById('mail-link').href='mailto:me@jessedecker.com?subject='+escape('Found 404 Error')+'&body='+escape('Hi Jesse,\n\nI found a 404 Error at '+document.location.href+'. What\'s going on?\n')
</script>
</body>
</html>
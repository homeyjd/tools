<?php
/*
 * Created on Jan 12, 2007
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */

function uses ($name)
{
	require_once(LIBS . $name . '.php');
}

/**
 * Include a page element in a template.
 */
function element ($name, $vars = array(), $echo = false)
{
	extract($vars);
	if ($echo) {
		return include(LIBS . 'element_' . $name . '.php');
	} else {
		ob_start();
		include(LIBS . 'element_' . $name . '.php');
		return ob_get_clean();
	}
}

/**
 * Ensures default values in an array.
 */
function defaults (&$source, $vars = array())
{
	if ($source === null) {
		if (!empty($vars))
			$source = array();
		else 
			return array();
	}
	
	foreach ($vars as $s => $default) {
		if (!isset($source[$s]))
			$source[$s] = $default;
	}
	return $source;
}

/**
 * Ensure key existence.
 */
function ensure (&$source, $vars = array())
{
	if ($source === null) {
		if (!empty($vars))
			$source = array();
		else 
			return array();
	}
	
	foreach ($vars as $key) {
		if (!isset($source[$key]))
			$source[$key] = '';
	}
	return $source;
}

if (!function_exists('define_once')) {
	/**
	 * Define a constant only if the constant is not defined already.
	 */
	function define_once ($name, $value)
	{
		if (!defined($name))
			define($name, $value);
	}
} //if

/**
 * Returns the time in microseconds from the Unix epoch.  
 */
function getmicrotime ()
{ 
    list($usec, $sec) = split(" ",microtime()); 
    return ((float)$usec + (float)$sec); 
}

/**
 * Returns a formatted timestamp in the form "[HH:mm:ss m.icro] ".
 */
function gettimestamp ()
{
	global $starttime;
	$cur = getmicrotime();
	$cur = $cur-$starttime;
	
	$time = "[".date('H:i:s')." ".number_format($cur,4,'.','')."] ";
	
	return $time;
}

/**
 * Debugs a variable
 * @param $name string  A message or variable name to output before the debug info
 * @param $var  mixed   A variable to display debugging information for
 */
function debug_var ($var,$name=null)
{
	// Capture output of debug var into $str
	ob_start();
	var_dump($var);
	$str = ob_get_contents();
	ob_end_clean();
	
	// Edit the output
	// Remove spaces after '=>'
	$str = preg_replace("/=>\n(\s*)/"," => ",$str);
	// Add a space before the first '{'
	$str = preg_replace("/^(.{0,100})\{\n/","\\1\n{\n",$str);
	// Add a space before all other opening brackets ('{')
	$str = preg_replace("/(\n[ \t]*)(.+)\{/","\\1\\2\\1{",$str);
	// Double white space before all lines
	$str = preg_replace("/\n(\s*)/","\n\\1\\1",$str);
	
	// Create array split by new line breaks
    $str = preg_split("/\r?\n|\n/",$str);
    
    print "<div>";
    
    // Only print out javascript function once
    if (!defined('_DEBUG_VAR_JAVASCRIPT_PRINTED'))
    {
	    print "<script>
// Toggles the visibility of a div. The visibility can be specified with
// the 'open' parameter or the function will just toggle it.
// @param num   An integer specifying what debug output to work with.
//             Each time the PHP function debug_var() is called, that function call is assigned
//             a unique number, starting with 0.
// @param open  Whether to open or close the div.  If this is NULL, this function call
//              reverses whatever the div's current status is.
function toggleDebugDiv(num,open){
	 var span = document.getElementById('debug_div'+num);
	 if (!span) return false; 
	 var a = document.getElementById('debug_a'+num);
	 if(open == true || (open==null && span.style.display == 'none')){
	    a.innerHTML = '{-';
	    span.style.display = 'inline';
	 }else if (open == false || (open==null && span.style.display == 'inline')){
	    a.innerHTML = '{+';
	    span.style.display = 'none';
	 }
	 return true;
}
// Toggles all divs for a specific variable debug output
// @param open  Boolean flag of whether to make all the divs open or closed
// @param inst  An integer specifying what debug output to work with.
//              Each time the PHP function debug_var() is called, that function call is assigned
//              a unique number, starting with 0.
//              If this param is NULL, this function will open ALL divs on ALL debug_var() outputs.
// @param lvl   Which level of div to open.  The root level is 0.  A div inside of a root 0-level div
//              is at level 1.
function toggleAllDebugDivs(open,inst,lvl){
	if (inst == null) var inst = 0; // instance
	var i = 0; // instance
	var j = 0; // level
	var k = 0; // id
	var loop = 1; // whether to continue looping
	if (inst != null) {
		if (lvl != null) {
			while (toggleDebugDiv('_'+inst+'_'+lvl+'_'+k,open))
				k++;
			return true;
		}
		while (loop > 0) {
			while (toggleDebugDiv('_'+inst+'_'+j+'_'+k,open)) {
				k++;
				loop = 2;
			}
			j++;
			k = 0;
			loop--;
		}
		return true;
	}
	while (loop > 0) {
		while (loop > 1) {
		 	while (toggleDebugDiv('_'+i+'_'+j+'_'+k,open)) {
		 		k++;
		 		loop = 3;
		 	}
		 	j++;
		 	k = 0;
		 	loop--;
	 	}
	 	j = 0;
	 	i++;
	 	loop--;
	}
	return true;
}</script>\n";
		define('_DEBUG_VAR_JAVASCRIPT_PRINTED',true);
	}
	
	// Print out the message
	if ($name != null)
		print "<b>$name</b>\n";
	print "<pre>";
	$string = '';
	global $debug_instance;
	if (!isset($debug_instance)) $debug_instance = -1;
	$debug_instance++;
	$numStr = count($str);
	for($i = 0; $i < $numStr; $i++)
	{
		$string = debug_colorize_string($str[$i])."\n";
		
		// Add on the expand and contract commands at the beginning if there's an opening
		// bracket on the second line
		if ($i <= 3)
		{
			// Put in one more link - the "show all" link
			if (substr($string,0,1) == '{')
				$string = substr_replace($string, "{ <a href=\"javascript:;\" onclick=\"toggleAllDebugDivs(false,$debug_instance)\">&lt;-</a>|<a href=\"javascript:;\" onclick=\"toggleAllDebugDivs(true,$debug_instance)\">+&gt;</a>", 0, 1);
		}
		
		// Add on the expand and contract commands if we're near the end
		if ($i >= $numStr-2)
		{
			if (substr($string,0,1) == '}')
				$string = substr_replace($string, "} <a href=\"javascript:;\" onclick=\"toggleAllDebugDivs(false,$debug_instance)\">&lt;-</a>|<a href=\"javascript:;\" onclick=\"toggleAllDebugDivs(true,$debug_instance)\">+&gt;</a>", 0, 1);
		}
		
		// Output the string
		echo $string;
	}
		
	print "</pre></div>\n";
}

/**
 * Keep track of what div ID we're on.  Should be used in a regex function.
 * @see debug_colorize_string($string)
 */
function debug_next_div ($matches)
{
  global $debug_num, $debug_lvl, $debug_instance;
  if (!isset($debug_lvl)) $debug_lvl = 0;
  else ++$debug_lvl;
  if (!isset($debug_num)) $debug_num = array(0=>array(0));
  else {
  	if (!isset($debug_num[$debug_instance][$debug_lvl]))
  		$debug_num[$debug_instance][$debug_lvl] = 0;
  	else
  		$debug_num[$debug_instance][$debug_lvl]++;
  }
  $str = "{$debug_instance}_{$debug_lvl}_{$debug_num[$debug_instance][$debug_lvl]}";
  return "$matches[1]   <a id=\"debug_a_{$str}\" href=\"javascript:;\"" .
  		" onclick=\"toggleDebugDiv('_{$str}')\">{++</a><span id=\"" .
  		"debug_div_{$str}\" style=\"display:none\">";
}
/**
 * Keep track of what div ID we're on.  Should be used in a regex function.
 * @see debug_colorize_string($string)
 */
function debug_prev_div ($matches)
{
  global $debug_num, $debug_lvl, $debug_instance;
  $debug_lvl--;
  return "$matches[1]   }</span>\n"; // replaced "\$1   }</span>\n"
}
/**
* colorize a string for pretty display
*
* @access private
* @param $string string info to colorize
* @return string HTML colorized
* @global
*/
function debug_colorize_string($string)
{
   // BUGFIX: We need to replace any <> characters with their HTML equivalents so the browser
   // doesn't render the actual tag
   $string = preg_replace(array("/</","/([^=])>/"), array("&lt;","\$1&gt;"), $string);
   
   // Turn stuff in brackets, such as array indexes, to red
   $string = preg_replace("/\[(\"?\w*\"?)\]/i", '[<font color="red">$1</font>]', $string);
   // Hide stuff inside divs
   $string = preg_replace_callback("/(\s+)\{$/", 'debug_next_div', $string);
   $string = preg_replace_callback("/(\s+)\}$/", 'debug_prev_div', $string);
   
   // Turn the word Array blue
   //$string = str_replace('Array','<font color="blue">Array</font>',$string);
   //$string = str_replace('array','<font color="blue">array</font>',$string);
   
   // Turn variable type blue
   $string = preg_replace("/=>\s*([^\(]+)\((.*)\)/","=> <font color=\"blue\">\\1</font>(\\2)",$string);
   
   // turn arrows graygreen 
   $string = str_replace('=>','<font color="#556F55">=></font>',$string);
   return $string;
}


/**
 * Outputs an HTML table from a PHP array, including the option for a heading.
 * @param array   $a       The array.  $a[0], if an array, will be used as headings
 * @param boolean $strict  Whether to use strict filtering
 */
function getTable($a, $strict = true)
{
	$headings = array();
	
	$r = "<table border=1 cellspacing=0 cellpadding=3>\n<tr>\n";
	if (!is_array($a) || count($a) < 1) 
	{
		$r .= "<tr><td>VALUE IS NOT AN ARRAY!</td></tr></table>";
		return $r;
	}
	if (is_array($a[0])) 
	{
		// Create headers
		$r .= "<tr>";
		foreach (array_keys($a[0]) as $key) 
		{
			$r .= "<th>$key</th>";
			$headings[] = $key;
		}
		$r .= "</tr>\n";
		
		// Loop over values
		foreach ($a as $index => $row) 
		{
			$r .= "<tr>";
			if ($strict) 
			{
				// Look explicitly for keys
				foreach ($headings as $key)
				{
					$r .= "<td>";
					$c = remove($row[$key]);
					if (is_array($c)) $r .= getTable($c);
					elseif (isset($c) && $c != '') $r .= "$c";
					else $r.= "&nbsp;";
					$r .= "</td>";
				}
			} 
			else 
			{
				// Loop over the keys
				foreach ($row as $key => $value) 
				{
					$r .= "<td>";
					$value = remove($value);
					if (is_array($value)) $r .= getTable($value);
					elseif ($value != '') $r .= "$value";
					else $r.= "&nbsp;";
					$r .= "</td>";
				}
			}
			$r .= "</tr>";
		}
	}
	else 
	{
		// Loop over values
		foreach ($a as $key => $value) 
		{
			$r .= "<tr><td>";
			$value = remove($value);
			if (is_array($value)) $r .= getTable($value);
			elseif ($value != '') $r .= "$value";
			else $r.= "&nbsp;";
			$r .= "</td></tr>";
		}
	}
	
	
	$r .= "</table>";
	return $r;
	
}

/**
 * Counts the number of \r\n's, \n's, and <br/>'s.
 * @param $haystack string
 */
function count_lines($haystack)
{
	// Count text before HTML markup
	$n = strpos($haystack,'<');
	if ($n === false) {
		$haystack = trim($haystack);
		if (strlen($haystack)>0)
			return 1 + preg_match_all("/(\r?\n|\r)/",$haystack,$ns);
		else 
			return 0;
	}
	elseif ($n > 0) $n = 1;
	//$haystack = substr($haystack,$n); /* no nesseccary */
	
	// Match tags like p and li, and match any br tag
	$n += preg_match_all("/((\r?\n\\s*)?<(pre|p|li)( [^>]*)?\/?>)|(<tr( [^>]*)?>)|(<br ?\/?>)|(<\/(pre|p)[^>]*>\\s*\\w)/",$haystack,$ns);
	
	// Add all newline characters between <pre> tags
	$start = 0;
	while (true) {
		// Doesn't look for variant HTML tags of <pre> -- that tag
		// cannot have any parameters!
		$start = strpos($haystack,'<pre>',$start);
		if ($start === false)
			break;
		$len = strpos($haystack,'</pre>',$start);
		$len = ($len !== false) ? $len-$start : strlen($haystack)-$start;
		$n += preg_match_all("/(\r?\n|\r)/",substr($haystack,$start,$len),$ns);
		$start += $len;
	}
	if ($start > 0) $n += 1; // There will always be at least one new line.
	
	// Add all newline characters between <textarea> tags
	$start = 0;
	while (true) {
		$start = strpos($haystack,'<textarea',$start);
		if ($start === false)
			break;
		$start = strpos($haystack,'>',$start);
		$len = strpos($haystack,'</textarea>',$start);
		$len = ($len !== false) ? $len-start : strlen($haystack)-$start;
		$n += preg_match_all("/(\r?\n|\r)/",substr($haystack,$start,$len),$ns);
		$start += $len;
	}
	
	if ($n < 1 && strlen($n) > 0) $n = 1;
	return $n;
}


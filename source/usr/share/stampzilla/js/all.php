<?php

echo file_get_contents("mootools-core-1.3-full-compat-yc.js");

$files = scandir('.');
foreach($files as $key => $line) {
	if ( substr($line,-3) != '.js' || $line == "mootools-core-1.3-full-compat-yc.js" )
		continue;
	
	echo file_get_contents($line);
}


?>

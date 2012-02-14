<?php

echo file_get_contents("mootools-core-1.3-full-compat-yc.js");

//ll.php                              json.js                              mootools-core-1.3-full-compat-yc.js  room.js                              swipe.js                             
//editmode.js                          menu.js                              mootools-more-1.4.0.1.js             settings.js                          video.js    

$files = scandir('.');
foreach($files as $key => $line) {
	if ( substr($line,-3) != '.js' || $line == "mootools-core-1.3-full-compat-yc.js" || $line == "mif.tree-v1.2.6.4.js" || $line == "sape.transport.js" || $line == 'iScroll.js')
		continue;
	
	echo file_get_contents($line);
}


?>

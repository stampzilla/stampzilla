<?php

include "lib/errorhandler.php";

for( $i=0;$i<10;$i++ ) 
errorhandler::send($i,'TEST');

?>

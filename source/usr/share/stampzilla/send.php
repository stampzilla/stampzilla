<?php

passthru("stampzilla send -o \"".addslashes(http_build_query($_GET))."\"");

?>

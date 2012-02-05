<?php

chdir("/usr/lib/stampzilla/components");
passthru("./send.php -o \"".addslashes(http_build_query($_GET))."\"");

?>

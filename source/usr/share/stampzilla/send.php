<?php

exec("stampzilla send \"".addslashes(http_build_query($_GET))."\"");

?>

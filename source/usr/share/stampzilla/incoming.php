<style>
body {
	background:#000;
	color:#fff;
	white-space:nowrap;
}
</style>
<?php

require "/usr/lib/stampzilla/lib/constants.php";
require "/usr/lib/stampzilla/lib/udp.php";

$logger = new logger();

class logger {
	function __construct() {
		// Create a new udp socket
		$this->udp = new udp('0.0.0.0','255.255.255.255',8282);

		$msg = "\n".'<script language="javascript">parent.communicationReady();</script>';
        echo str_pad($msg,4096,' ',STR_PAD_LEFT);
        ob_flush();
        flush();

		while(1) {
			$this->parent++;
			if ( !$pkt = $this->udp->listen() )
				continue;

            $p = json_encode($pkt,JSON_FORCE_OBJECT);
			// Format message
			$msg = "\n".$p.'<br /><script language="javascript">parent.incoming("'.addslashes($p).'");window.scroll(0,999999999);</script>';

			echo str_pad($msg,4096,' ',STR_PAD_LEFT);
			ob_flush();
			flush();
		}
	}
}



?>

<?php

require "/usr/lib/stampzilla/lib/constants.php";
require "/usr/lib/stampzilla/lib/udp.php";

echo str_pad('',4096,' ');
ob_flush();
flush();

$logger = new logger();

class logger {
	function __construct() {
		// Create a new udp socket
		$this->udp = new udp('0.0.0.0',8282);

		while(1) {
			$this->parent++;
			if ( !$pkt = $this->udp->listen() )
				continue;

			// Format message
			$msg = "\n".'<script language="javascript">parent.incoming("'.addslashes(json_encode($pkt)).'");</script>';

			echo str_pad($msg,4096,' ',STR_PAD_LEFT);
			ob_flush();
			flush();
		}
	}
}



?>

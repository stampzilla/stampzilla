<?php

require_once("../lib/component.php");

class intercomtest extends component {
	
	function intercom_event() {
		echo "Parent recived\n";
		print_r(func_get_args());
	}

	function child() {
		$hash = md5(time());
		while(true) {
			// Testsend intercom event
			$this->intercom(
				time(),
				$hash
			);
			sleep(1);
		}
	}
}

$i = new intercomtest();
$i->start('intercomtest','child');
?>

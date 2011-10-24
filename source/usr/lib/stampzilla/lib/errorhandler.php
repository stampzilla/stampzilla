<?php

require_once "constants.php";
set_error_handler('errorhandler::error');

class errorhandler {

    static function error( $no, $text, $file, $line, $context ) {
		errorhandler::send( logLevel::error,"$file:$line" );
        echo $text.'@'.$file.':'.$line."\n";
	}

	static function send( $level, $msg ) {
		if ( !$s = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP ) )
            die("Failed to create error send socket");

        socket_set_option( $s, SOL_SOCKET, SO_BROADCAST, 1 );
		$string = json_encode(
			array(
				'type' => 'log',
				'from' => '???',
				'level' => $level,
				'message' => $msg
			)
		);
        socket_sendto($s, $string, strlen($string), 0 ,'255.255.255.255', 8282);
	}
}

?>

<?php

require_once "constants.php";
set_error_handler('errorhandler::error');

class errorhandler {
    static function error( $no, $text, $file, $line, $context ) {
		errorhandler::send( error,$text,array('file'=>$file,'line'=>$line) );
        echo $text.'@'.$file.':'.$line."\n";
	}

	static function send( $level, $msg, $data=array() ) {
		if ( !$s = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP ) )
            die("Failed to create error send socket");

        socket_set_option( $s, SOL_SOCKET, SO_BROADCAST, 1 );
		$string = json_encode(
			array(
				'type' => 'log',
				'from' => '???',
				'level' => $level,
				'message' => $msg,
                'data' => $data
			)
		);
        socket_sendto($s, $string, strlen($string), 0 ,'255.255.255.255', 8282);
	}

}

function note($level,$text) {
    if ( is_array($text) )
        $text = json_encode($text);
    errorhandler::send($level,$text);
    echo $text."\n";
}

?>

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

    static function currentTime() {
        $utimestamp = microtime(true);
        $timestamp = floor($utimestamp);
        $milliseconds = round(($utimestamp - $timestamp) * 1000000);
        return date('Y-m-d H:i:s.'.str_pad($milliseconds,6,' '),$timestamp);
    }
}

function note($level,$text) {
    if ( is_array($text) )
        $text = json_encode($text);

    errorhandler::send($level,$text);

    switch($level) {
        case critical:
            echo "\033[31mCRITICAL ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case error:
            echo "\033[31mERROR    ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case warning:
            echo "\033[35mWARNING  ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case notice:
            echo "\033[34mNOTICE   ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case debug:
            echo "\033[32mDEBUG    ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        default:
            echo "\033[36mUNKNOWN  ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
    }
}

?>

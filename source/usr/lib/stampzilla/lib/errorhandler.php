<?php

require_once "functions.php";

if ( isset($_SERVER['argv']) ) {
    $args = arguments($_SERVER['argv']);
} else {
    $args = array(
        'flags' => array()
    );
}

require_once "constants.php";

$lastprint = 0;

class errorhandler {
    static function error( $no, $text, $file, $line, $context ) {
		//if($no & 32676)
		//	return false;

        if ( $no == 2 && $text == 'socket_recv(): unable to read from socket [11]: Resource temporarily unavailable' )
            return false;

        switch($no){
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_RECOVERABLE_ERROR:
                $level = critical;
                break;
            case E_USER_ERROR:
            case E_ERROR:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $level = error;
                break;
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_WARNING:
            case E_COMPILE_WARNING:
                $level = warning;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $level = notice;
                break;
            default:
                $level = unknown;
        }

		errorhandler::send( $level,$text,array('file'=>$file,'line'=>$line) );
        printout(format($level,$text.' {'.$level.'} @'.$file.':'.$line));
	}

	static function send( $level, $msg, $data=array() ) {
		if ( !$s = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP ) )
            die("Failed to create error send socket");

        socket_set_option( $s, SOL_SOCKET, SO_BROADCAST, 1 );
		global $stampzilla;
		if ( !$stampzilla )
			$stampzilla = '???';

		$string = json_encode(
			array(
				'type' => 'log',
				'from' => $stampzilla,
				'level' => $level,
				'message' => $msg,
                'data' => $data
			)
		)."\n";
        socket_sendto($s, $string, strlen($string), 0 ,'255.255.255.255', 8281);
	}

    static function recive_pkt( $pkt, $raw ) {
        global $args;
        if ( in_array('j',$args['flags']) )
        	printout("\033[1;36mJSON     ".errorhandler::currentTime()." PKT ".$pkt['from']." (".trim($raw).") \n\033[0m");
    }

    static function send_pkt( $pkt, $raw ) {
        global $args;
        if ( in_array('j',$args['flags']) )
            printout("\033[1;31mJSON     ".errorhandler::currentTime()." PKT ".$pkt['from']." (".trim($raw).") \n\033[0m");
    }

    static function currentTime($full = false) {
		global $headprint;

		if ( $full ) 
    	    return date('Y-m-d H:i:s');
		else {

			$utimestamp = microtime(true) - $headprint;
			$timestamp = floor($utimestamp);
			$milliseconds = round(($utimestamp - $timestamp) * 1000000);

			return str_pad($timestamp,3,' ',STR_PAD_LEFT)."s ".str_pad(round($milliseconds/1000,0),3,' ',STR_PAD_LEFT)."ms";

	        //return date('H:i:s.'.str_pad($milliseconds,6,'0',STR_PAD_LEFT),$timestamp);
		}
    }
}

set_error_handler('errorhandler::error');

function note($level,$text) {
    if ( is_array($text) )
        $text = json_encode($text);

    errorhandler::send($level,$text);

    global $args;
    if ( in_array('d',$args['flags']) || $level < warning )
        printout(format($level,$text));

	return false;
}

function printout( $text ) {
	global $lastprint,$headprint;

	if ( time() - $lastprint > 2 ) {
		echo "\n----[ ".errorhandler::currentTime(true)." ]--------------------------\n";
		$headprint = microtime(true);
	}
	$lastprint = time();

	echo $text;
}

function format($level,$text){
   switch($level) {
        case critical:
            return "\033[31mCRITICAL ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case error:
            return "\033[31mERROR    ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case warning:
            return "\033[1;33mWARNING  ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case notice:
            return "\033[32mNOTICE   ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case debug:
            return "\033[34mDEBUG    ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        default:
            return "\033[36mUNKNOWN  ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
    }
}

?>

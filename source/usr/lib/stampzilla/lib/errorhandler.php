<?php

require_once "constants.php";
set_error_handler('errorhandler::error');

class errorhandler {
    static function error( $no, $text, $file, $line, $context ) {
		if($no & 32767)
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
        echo format($level,$text.'@'.$file.':'.$line);
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
		)."\n";
        socket_sendto($s, $string, strlen($string), 0 ,'255.255.255.255', 8281);
	}

	static function recive_pkt( $pkt, $raw ) {
    	echo "\033[1;36mJSON     ".errorhandler::currentTime()." PKT ".$pkt['from']." (".trim($raw).") \n\033[0m";
	}

	static function send_pkt( $pkt, $raw ) {
    	echo "\033[1;31mJSON     ".errorhandler::currentTime()." PKT ".$pkt['from']." (".trim($raw).") \n\033[0m";
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
    
    echo format($level,$text);

 
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
            return "\033[35mWARNING  ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case notice:
            return "\033[34mNOTICE   ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        case debug:
            return "\033[32mDEBUG    ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
        default:
            return "\033[36mUNKNOWN  ".errorhandler::currentTime()." EE ".$text."\n\033[0m";
            break;
    }
}

?>

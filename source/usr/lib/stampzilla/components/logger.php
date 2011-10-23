<?php

require "../lib/constants.php";
require "../lib/udp.php";

$logger = new logger();

class logger {
	function __construct() {
		// Create a new udp socket
		$this->udp = new udp('0.0.0.0',8282);

		while(1) {
			$this->parent++;
			if ( !$pkt = $this->udp->listen() )
				continue;

			// Ignore packages that arent errors
			if ( $pkt['type'] != 'log' ) 
				continue;

			// Format message
			$msg = $this->textFormat($pkt);

            // Write message in log file
            $filename = '/var/log/stampzilla/stampzilla.log';

            if ( ($f = fopen($filename,'a+')) !== false ) {
                fwrite($f,$msg);
                fclose($f);
            }

			echo $msg;
		}
	}

	function textFormat($pkt) {
		switch($pkt['level']) {
			case logLevel::critical:
        		return "\033[31mCRITICAL ".$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."\n\033[0m";
			case logLevel::error:
        		return "\033[31mERROR    ".$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."\n\033[0m";
			case logLevel::warning:
        		return "\033[31mWARNING  ".$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."\n\033[0m";
			case logLevel::notice:
        		return "\033[31mNOTICE   ".$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."\n\033[0m";
			case logLevel::debug:
        		return "\033[31mDEBUG    ".$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."\n\033[0m";
			default:
        		return "\033[31mUNKNOWN  ".$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."\n\033[0m";
		}
	}

    function currentTime() {
        $utimestamp = microtime(true);
        $timestamp = floor($utimestamp);
        $milliseconds = round(($utimestamp - $timestamp) * 1000000);
        return date('Y-m-d H:i:s.'.str_pad($milliseconds,6,' '),$timestamp);
    }
}



?>

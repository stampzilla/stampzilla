<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="chrome=1">
        <meta http-equiv="Expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
        <meta http-equiv="Pragma" content="no-cache"> 
        <title>stampzilla log</title>
		<style>
			pre {
				margin:0px;
			}

            .critical {
                color:#f00;
            }

            .error {
                color:#ff8c00;
            }

            .warning {
                color:#ff1493;
            }

            .notice {
                color:#0000cd;
            }

            .debug {
                color:#556b2f;
            }
		</style>
	</head>
    <body>
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

			// Ignore packages that arent errors
			if (!isset($pkt['type']) || $pkt['type'] != 'log' ) 
				continue;

			// Format message
			$msg = $this->textFormat($pkt);
			$msg = "<pre>$msg</pre>";
			$msg .= '<script language="javascript">window.scroll(0,99999999)</script>';

			echo str_pad($msg,4096,' ');
			ob_flush();
			flush();
		}
	}

	function textFormat($pkt) {
		switch($pkt['level']) {
			case critical:
        		return '<div class="critical">CRITICAL '.$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."</div>";
			case error:
        		return '<div class="error">ERROR    '.$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."</div>";
			case warning:
        		return '<div class="warning">WARNING  '.$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."</div>";
			case notice:
        		return '<div class="notice">NOTICE   '.$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."</div>";
			case debug:
        		return '<div class="debug">DEBUG    '.$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."</div>";
			default:
        		return '<div class="unknown">UNKNOWN  '.$this->currentTime()." EE ".trim($pkt['from'])." EE ".$pkt['message']."</div>";
		}
	}

    function currentTime() {
        $utimestamp = microtime(true);
        $timestamp = floor($utimestamp);
        $milliseconds = round(($utimestamp - $timestamp) * 1000000);
        return date('Y-m-d H:i:s.'.str_pad($milliseconds,6,'&nbsp'),$timestamp);
    }
}



?>

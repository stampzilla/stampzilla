<?php

class udp {
    private $sent = array();
	private $buff = '';

    function __construct($host, $port) {
        $this->port = $port;
        if ( !$this->s = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP ) )
            trigger_error("Failed to create send socket");

        if ( !$this->r = stream_socket_server("udp://$host:$port", $errno,$errstr, STREAM_SERVER_BIND ) )
            trigger_error("Failed to create listen socket"); 

        socket_set_option( $this->s, SOL_SOCKET, SO_BROADCAST, 1 );
		stream_set_timeout($this->r,1);
    }

    function broadcast( $string ) {
		$string .= "\n";
        $this->sent[$string] = 1;
        socket_sendto($this->s, $string, strlen($string), 0 ,'255.255.255.255', $this->port);
    }

    function listen( ) {
		// We cant use fread, fgets and stream_get_line beacuse of bug PHP https://bugs.php.net/bug.php?id=32810 :(
		if ( !strpos($this->buff,"\n") )
        	$this->buff .= stream_socket_recvfrom($this->r,1500000,0,$peer);

		// Check if a whole package has arrived
		if ( $pos = strpos($this->buff,"\n") ) {
			$start = strpos($this->buff,"{");
			$pkt = substr($this->buff,$start,$pos+1-$start);
			$this->buff = strpos($this->buff,$pos+1);

			// Ignore prev sent messages
			if ( isset($this->sent[$pkt]) ) {
				unset($this->sent[$pkt]);
				return false;
			}

			// Decode and check fail
			if ( !$json = json_decode($pkt,true) ) {
				note(critical, "INVALID JSON! ".$pkt."\n");
				return false;
			}
        	return $json;
		}

		return false;
    }
}

?>

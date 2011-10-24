<?php

class udp {
    private $sent = array();

    function __construct($host, $port) {
        $this->port = $port;
        if ( !$this->s = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP ) )
            trigger_error("Failed to create send socket");

        if ( !$this->r = stream_socket_server("udp://$host:$port", $errno,$errstr, STREAM_SERVER_BIND ) )
            trigger_error("Failed to create listen socket"); 

        socket_set_option( $this->s, SOL_SOCKET, SO_BROADCAST, 1 );
    }

    function broadcast( $string ) {
        $this->sent[$string] = 1;
        socket_sendto($this->s, $string, strlen($string), 0 ,'255.255.255.255', $this->port);
    }

    function listen( ) {
        $pkt = stream_socket_recvfrom($this->r,12000,0,$peer);

		// Ignore prev sent messages
        if ( isset($this->sent[$pkt]) ) {
            unset($this->sent[$pkt]);
            return false;
        }

		// Decode and check fail
		if ( !$pkt = json_decode($pkt,true) ) 
			return false;

        return $pkt;
    }
}

?>

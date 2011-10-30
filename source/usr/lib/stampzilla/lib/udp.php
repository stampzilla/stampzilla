<?php

function superdie() {
    echo "HJÄLP!\n";
    die();
}

class udp {
    private $sent = array();
	private $buff = '';
	private $log = false;
    public $peer = '';
    public $istcp = false;

    function __construct($host, $port) {
        $this->port = $port;
        if ( !$this->s = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP ) )
            trigger_error("Failed to create send socket");

        if ( !$this->r = stream_socket_server("udp://$host:$port", $errno,$errstr, STREAM_SERVER_BIND ) )
            trigger_error("Failed to create listen socket"); 

		$this->log = method_exists('errorhandler','recive');

        socket_set_option( $this->s, SOL_SOCKET, SO_BROADCAST, 1 );
    }

    function broadcast( $array ) {
        $string = json_encode($array)."\n";
        if(strlen($string) > 8192){
            if($array['cmd'] == 'ack'){
                if ( !$pid = pcntl_fork() ){
                    $this->istcp = true;

                    echo "setting up tcp socket\n";
                    $this->tcp_socket = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
                    socket_bind($this->tcp_socket,'0.0.0.0');
                    socket_getsockname($this->tcp_socket,$ip,$p);
                    socket_listen($this->tcp_socket,100);

                    $this->broadcast(array(
                        'cmd'=>$array['cmd'],
                        'from'=>$array['from'],
                        'port' => $p,
                        'to'=>$array['to']
                    ));

                    socket_set_nonblock($this->tcp_socket);
                    $start = time();
                    while( $start + 10 > time() ) {
                        if ( $client = @socket_accept($this->tcp_socket) ) {
                            socket_write($client,$string);
                            socket_close($client);
                        } else {
                            usleep(100000);
                        }
                    }

                    socket_close($this->tcp_socket);
                    die();
                } elseif ( $pid < 0 ) {
                    note(error,"Failed to fork ($pid)");
                }
            }
            else
                trigger_error('Broadcast packet is to LARGE! (8192)',E_USER_ERROR);

        }
        else{
            $this->sent[$string] = 1;
            socket_sendto($this->s, $string, strlen($string), 0 ,'255.255.255.255', $this->port);
        }

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

			// Decode and check fail
			if ( !$json = json_decode($pkt,true) ) {
				note(critical, "INVALID JSON! ".$pkt."\n");
				return false;
			}
			// Ignore prev sent messages
			if ( isset($this->sent[$pkt]) || $json['from'] == $this->peer ) {
				unset($this->sent[$pkt]);
				return false;
			}

            if( isset($json['port']) ){
                echo "get from tcp socket on port ".$json['port']."\n";

                $peer = explode(':',$peer);
                $fp = fsockopen($peer[0], $json['port'], $errno, $errstr, 30);
                if (!$fp) {
                    echo "$errstr ($errno)<br />\n";
                } else {
                    $string = '';
                    while (!feof($fp)) {
                        $string .= fgets($fp, 128);
                    }
                    fclose($fp);
                    $json = json_decode($string,true);
                    echo "got $string\n";
                }

            }

			//if ( $this->log && (!isset($json['type']) || $json['type'] != 'log') ) {
			if ( $this->log  && (!isset($json['type']) || $json['type'] != 'log') ) {
				errorhandler::recive($json,$pkt);
            }

        	return $json;
		}

		return false;
    }
}

?>

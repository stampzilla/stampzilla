<?php

declare(ticks = 1);

require_once("udp.php");
require_once("errorhandler.php");

class component {
    private $parent = 0;
    private $child = 0;
    private $pid = 0;

    function __construct() {
        $this->udp = new udp('0.0.0.0',8282);
        $this->peer = '';

    }

    function broadcast( $data ) {
        if ( !isset($data['from']) )
            $data['from'] = $this->peer;

        $pkg = json_encode($data);
        note(debug,$pkg);
        $this->udp->broadcast( $pkg );

        return sha1($pkg);
    }

    function exec( $to, $cmd ) {
        $this->broadcast(array(
            'to' => $to,
            'from' => $this->peer,
            'cmd' => $cmd
        ));
    }

    function intercom( ) {
		// Send the message
		$ic = json_encode( func_get_args() )."\n";
		if ( !socket_write( $this->intercom_socket,$ic,strlen($ic) ) ) {
			$code = socket_last_error();
			if ( $code == 32 )
				die("Intercom socket broken, DIE");

			return trigger_error("Failed to send intercom using IC socket: $code");
		}

		// Send the alarm signal to parent
		posix_kill( $this->parent_pid, SIGALRM );
    }

	function recive_intercom() {
		// Add the signal handler again
		if ( !pcntl_signal( SIGALRM,array($this,'recive_intercom') ) )
			trigger_error("Failed to install signal handler");

		// Read the message
		if ( ($line = socket_read($this->intercom_socket, 1024, PHP_NORMAL_READ)) !== false ) {
			if ( ($args = json_decode($line)) === NULL )
				return trigger_error("Syntax error in intercom JSON (".trim($line).")");

			// Call the intercom_event
			if ( is_callable(array($this,'intercom_event')) )
				call_user_func_array( array($this,'intercom_event'),$args );
			else
				trigger_error("Got intercom event but no intercom_event function exists!");
		}
	}

	function parent_loop() {
		while(1) {
			// Wait for a udp package
			if ( !$pkt = $this->udp->listen() )
				continue;

			// Ignore invalid packages
			if ( !isset($pkt['from']) || (!isset($pkt['cmd'])&&!isset($pkt['type'])) )
				continue;

			if ( !isset($pkt['type']) || $pkt['type'] != 'log' )
				note(debug,$pkt);

			//die("DOE");

			// Event function is the default
			$call = 'event';

			// All standard packet types, ex log,event and so on
			if( isset($pkt['type']) && is_callable(array($this,$pkt['type'])) )
				$call = strtolower($pkt['type']);

			// CMD packages, send directly to function the corresponding function, if it exists
			if( isset($pkt['cmd']) && is_callable(array($this,$pkt['cmd'])) )
				$call = strtolower($pkt['cmd']);
	
			// Call the function
			if ( is_callable(array($this,$call)) )
				$res = $this->$call( $pkt );
			else
				$res = null;

			// If the packet is to this component, answer with result, NULL = fail = nak
			if ( !isset($pkt['to']) || $pkt['to'] == $this->peer ) {
				if ( $res )
					$this->broadcast(array(
						'to' => $pkt['from'],
						'from' => $this->peer,
						'cmd' => 'ack',
						'ret' => $res
					));
				elseif ( $res !== null )
					$this->broadcast(array(
						'to' => $pkt['from'],
						'from' => $this->peer,
						'cmd' => 'nak',
					));
			}
		}
	}

	function child_loop($function) {
    	while(1) {
        	call_user_func(array($this,$function));
        }
	}

	function kill_parent() {
		note(warning, "Died, killing child");
		posix_kill( $this->parent_pid, 9 );
	}

	function kill_child() {
		note(warning, "Died, killing child");
		posix_kill( $this->child_pid, 9 );
	}


    function start( $id, $child=null ) {
        $this->peer = $id;

        // Create a name if the node dosnt have one
        if ( !$this->peer ) {
            $this->peer = md5(time());
            $hashed = true;
        }

        note(debug,"----- Starting up component (".get_class($this).") with callsign ".$this->peer." -----");

		if( is_callable(array($this,'startup')) ) {
			$this->startup();
		}

		// No childprocess needed, start the main loop directly
        if ( !$child )
			return $this->parent_loop();
		
		// Create a intercom socket between parent and child process
		$sockets = array();
		if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
			return trigger_error("Failed to create socket pair: ".socket_strerror(socket_last_error()));
		}

		// Fork a child process
		if ( ($this->child_pid = pcntl_fork()) == -1 )
            return trigger_error('Failed to fork');
		
		note(debug,'Forked process with pid:'.$this->child_pid);

        if ( $this->child_pid ) {
			// Add an signal handler so the child can notify the parent when there are new intercom data
			pcntl_signal( SIGALRM,array($this,'recive_intercom') );

			// Make sure we dont leave any childs
			register_shutdown_function(array($this,'kill_child') );

			// Save the intercom socket, and close the other
			$this->intercom_socket = $sockets[0]; // Reader
			//socket_set_nonblock( $this->intercom_socket );
			socket_close( $sockets[1] );

			// Say hello to the network
			if ( !isset($hashed) ) 
				$this->broadcast(array(
					'from' => $this->peer,
					'cmd' => 'greetings'
				));

			// Parent
			note( debug, "Starting parent loop" );
			$this->parent_loop();
        } else {
			// Make shure we stop the parent if child dies
			register_shutdown_function(array($this,'kill_parent') );

			// Save the intercom socket, and close the other
			$this->intercom_socket = $sockets[1]; // Writer
			socket_close($sockets[0]);

			$this->parent_pid = posix_getppid();

			// Child
			note( debug, "Starting child loop" );
			$this->child_loop($child);
        }
    }
}

?>

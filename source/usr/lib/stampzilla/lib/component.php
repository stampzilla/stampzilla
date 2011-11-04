<?php

declare(ticks = 1);

require_once("errorhandler.php");
require_once("udp.php");
require_once("spyc.php");

class component {
    private $parent = 0;
    private $child = 0;
    private $pid = 0;
	private $died = false;

    function __construct() {
        $this->udp = new udp('0.0.0.0',8282);
        $this->peer = '';

    }

    function broadcast( $data ) {
        if ( !isset($data['from']) )
            $data['from'] = $this->peer;

        return $this->udp->broadcast( $data );
    }

    function intercom( ) {
		// Send the message
		$ic = json_encode( func_get_args() );

		// Send the alarm signal to parent
		posix_kill( $this->parent_pid, SIGALRM );

        $ic = wordwrap($ic,8192,"\n",true);
        $parts = explode("\n",$ic);

        foreach( $parts as $ic ) {
            if ( !socket_write( $this->intercom_socket,$ic,strlen($ic) ) ) {
                $code = socket_last_error();
                if ( $code == 32 )
                    die("Intercom socket broken, DIE");

                return trigger_error("Failed to send intercom using IC socket: $code");
            }
        }

        socket_write( $this->intercom_socket,"\n",1 );

    }

	function recive_intercom() {
		// Add the signal handler again
		if ( !pcntl_signal( SIGALRM,array($this,'recive_intercom') ) )
			trigger_error("Failed to install signal handler");

		// Read the message, and try at least 1000 times
        $buff = '';
        $cnt = 0;
		while( (@socket_recv($this->intercom_socket,$bytes, 1, MSG_DONTWAIT) ) || $cnt < 1000 ){
            $cnt++;
			$buff .= $bytes;
        }

		$buff = explode("\n",$buff);

        foreach ( $buff as $buffen ){
			if ( !trim($buffen) )
				continue;

			if ( ($args = json_decode($buffen)) === NULL )
				return trigger_error("Syntax error in intercom JSON (".trim($buffen).")");

			// Call the intercom_event
			if ( is_callable(array($this,'intercom_event')) ) {
				call_user_func_array( array($this,'intercom_event'),$args );
            } else
				trigger_error("Got intercom event but no intercom_event function exists!");
        }
	}

	function kill() {
		$this->kill_child();
	}

	function parent_loop() {
		while(1) {
			// Wait for a udp package
			if ( !$pkt = $this->udp->listen() )
				continue;

			// Ignore invalid packages
			if ( !isset($pkt['from']) || (!isset($pkt['cmd'])&&!isset($pkt['type'])) )
				continue;

			//if ( !isset($pkt['type']) || $pkt['type'] != 'log' )
			//	note(debug,$pkt);

			// Answer to hello
			if ( isset($pkt['type']) && $pkt['type'] == 'hello' ) {
				$this->greetings();
				continue;
			}

			// Event function is the default
			$call = 'event';

			if ( isset($pkt['to']) && $pkt['to'] == $this->peer ) {
				// All standard packet types, ex log,event and so on
				if( isset($pkt['type']) && is_callable(array($this,$pkt['type'])) )
					$call = strtolower($pkt['type']);

				// CMD packages, send directly to function the corresponding function, if it exists
				if( isset($pkt['cmd']) && is_callable(array($this,$pkt['cmd'])) && !in_array($pkt['cmd'],array('ack','nak','greetings','bye')) )
					$call = strtolower($pkt['cmd']);
			}

			// Call the function
			if ( is_callable(array($this,$call)) )
				$res = $this->$call( $pkt );
			else
				$res = null;

			// If the packet is to this component, answer with result, NULL = fail = nak
			if ( isset($pkt['to']) && $pkt['to'] == $this->peer ) {
				if ( $res )
                    $this->ack($pkt,$res);
				elseif ( $res !== null )
                    $this->nak($pkt);
			}
		}
	}
    //can be called to acknowledge a packet to the sender.
    function ack($pkt,$ret=NULL){
	    $this->broadcast(array(
            'to' => $pkt['from'],
            'cmd' => 'ack',
            'ret' => $ret,
			'pkt' => $pkt
        ));
    }

    function nak($pkt,$ret=null){
	    $this->broadcast(array(
            'to' => $pkt['from'],
            'cmd' => 'nak',
			'pkt' => $pkt,
			'ret' => $ret
        ));
    }

    function bye(){
	    $this->broadcast(array(
            'cmd' => 'bye'
        ));
    }

    function greetings(){
		if ( !isset($this->componentclasses) ) {
			trigger_error('No component classes defined!',E_USER_ERROR);
			$this->componentclasses = array();
		}
		if ( !isset($this->settings) ) {
			trigger_error('No component settings defined!',E_USER_WARNING);
			$this->settings = array();
		}

		$this->broadcast(array(
			'from' => $this->peer,
			'cmd' => 'greetings',
			'class' => $this->componentclasses,
			'settings' => $this->settings
		));
    }

    function broadcast_event( $event,$data=array() ){
	    $this->broadcast(array(
            'type' => 'event',
            'event' => $event,
			'data' => $data
        ));
    }

	function child_loop($function) {
    	while(1) {
        	call_user_func(array($this,$function));
        }
	}

	function kill_parent() {
        $this->bye();
		note(warning, "Died in child, killing parent");
		posix_kill( $this->parent_pid, SIGINT );
        die();
	}

	function kill_child()  {
        if ( $this->udp->istcp )
            return;

        // Send bye
        $this->bye();

        // Kill the child
        note(warning, "Died in parent, killing child");
        posix_kill( $this->child_pid, 9 );
		die();
	}


    function start( $id=NULL, $child=null ) {
        if($id)
            $this->peer = $id;

        // Create a name if the node dosnt have one
        if ( !$this->peer ) {
            $this->peer = md5(time());
            $hashed = true;
        }
        $this->udp->peer = $this->peer;

        note(debug,"----- Starting up component (".get_class($this).") with callsign ".$this->peer." -----");
			
		// Try to read settings
		$this->read_settings();

		if ( is_callable(array($this,'startup')) )
			$this->startup();

		// No childprocess needed, start the main loop directly
        if ( !$child ) {
            $this->child_pid = 1;
        } else {
            // Create a intercom socket between parent and child process
            $sockets = array();
            if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
                return trigger_error("Failed to create socket pair: ".socket_strerror(socket_last_error()));
            }

            // Fork a child process
            if ( ($this->child_pid = pcntl_fork()) == -1 )
                return trigger_error('Failed to fork');

            note(debug,'Forked process with pid:'.$this->child_pid);
        }

        if ( $this->child_pid ) {
			// Add an signal handler so the child can notify the parent when there are new intercom data
			pcntl_signal( SIGALRM,array($this,'recive_intercom') );

			// Make sure we dont leave any childs
			pcntl_signal( SIGINT ,array($this,'kill_child'), true );
			//pcntl_signal( SIGTERM ,array($this,'kill_child'), true );
			register_shutdown_function(array($this,'kill_child') );

			// Save the intercom socket, and close the other
			$this->intercom_socket = $sockets[0]; // Reader

			// Say hello to the network
			if ( !isset($hashed) )
				$this->greetings();

			// Parent
			note( debug, "Starting parent loop" );
			$this->parent_loop();
        } else {
			$this->parent_pid = posix_getppid();

			// Make shure we stop the parent if child dies
			register_shutdown_function(array($this,'kill_parent') );

			// Save the intercom socket, and close the other
			$this->intercom_socket = $sockets[1]; // Writer
			socket_close($sockets[0]);

			// Wait so parent have time to register SIGALRM handler
			sleep(1);

			// Child
			note( debug, "Starting child loop" );
			$this->child_loop($child);
        }
    }

	function save_setting($pkt) {
		// Fail if the setting key is not defined
		if ( !isset($this->settings[$pkt['key']]) )
			return $this->nak($pkt,array('msg' => 'Unknown setting "'.$pkt['key'].'"','value'=>''));

		$file = '/etc/stampzilla/'.$this->peer.'.yml';

		// Check if file exists
		if ( !is_file($file) )
			return $this->nak($pkt,array('msg' => "Config file ($file) is missing!",'value'=>''));

		// Try to read the settings file (yml)
		$data = spyc_load_file($file);

		$data[$pkt['key']] = $pkt['value'];
	
		$string = Spyc::YAMLDump($data);

		if ( !file_put_contents($file,$string) )
			return $this->nak($pkt,array('msg' => "Failed to save config file ($file)!",'value'=>''));
	
        if ( is_callable(array($this,'setting_saved')) ) {
            $this->setting_saved($pkt['key'],$pkt['value']);
        }

        note(notice,"Saved setting '".$pkt['key']."' to '".$pkt['value']."'");
		$this->ack($pkt,array('value'=>$pkt['value']));
	}

	function read_settings() {
		// Check if there are any settings defined
		if ( !isset($this->settings) )
			return;

		$file = '/etc/stampzilla/'.$this->peer.'.yml';

		// Check if file exists
		if ( !is_file($file) )
			return !trigger_error("Config file ($file) is missing!",E_USER_WARNING);

		// Try to read the settings file (yml)
		$data = spyc_load_file($file);
		foreach($this->settings as $key => $line) {
			if ( isset($data[$key]) ) {
				$this->settings[$key]['value'] = $data[$key];
			}
		}

		return true;
	}

	function setting($key) {
		if ( !isset($this->settings[$key]['value']) )
			return;

		return $this->settings[$key]['value'];
	}
}

?>

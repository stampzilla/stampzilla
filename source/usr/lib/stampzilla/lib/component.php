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
    private $hashed = false;
    public $state = array();
    private $intercom_id = 0;

// STARTUP
    function __construct() {/*{{{*/
        // Load network config

        $this->network = spyc_load_file('/etc/stampzilla/network.yml');

        if(!isset($this->network['listen']) )
            $this->network['listen'] = '0.0.0.0';
        if(!isset($this->network['broadcast']) )
            $this->network['broadcast'] = '255.255.255.255';
        if(!isset($this->network['port']) )
            $this->network['port'] = '8282';

        $this->udp = new udp($this->network['listen'],$this->network['broadcast'],$this->network['port']);
        $this->peer = '';

    }/*}}}*/
    function start( $id=NULL, $child=null, $child_setup=null ) {/*{{{*/
        if($id)
            $this->peer = $id;

		if ( DEFINED('INHIBIT_START') ) {
			global $node;
			$node = $this;
			return;
		}

        // Create a name if the node dosnt have one
        if ( !$this->peer ) {
            $this->peer = md5(time());
            $this->hashed = true;
        }
        $this->udp->peer = $this->peer;

        note(debug,"----- Starting up component (".get_class($this).") with callsign ".$this->peer." -----");
		global $stampzilla;
		$stampzilla = $this->peer;

        if ( !$this->hashed ) {
            // Say hello to the network
            $this->greetings();

            // Try to read settings
            $this->read_settings();

            $this->setState(
                array(
                    'node.started' => date('Y-m-d H:i:s'),
                    'node.pid' => posix_getpid()
                )
            );
        }

        if ( is_callable(array($this,'startup')) )
            $this->startup();

        // No childprocess needed, start the main loop directly
        if ( !$child ) {
            $this->child_pid = 1;
        } else {
            $this->child_func = $child;

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

            // If child is alive
            if(isset($sockets[0])){
                // Add an signal handler so the child can notify the parent when there are new intercom data
                pcntl_signal( SIGALRM,array($this,'recive_intercom') );

                // Make sure we dont leave any childs
                pcntl_signal( SIGINT ,array($this,'kill_child'), true );
                pcntl_signal(SIGCHLD, SIG_IGN);
                //pcntl_signal( SIGTERM ,array($this,'kill_child'), true );
                register_shutdown_function(array($this,'kill_child') );

                // Save the intercom socket, and close the other
                $this->intercom_socket = $sockets[0]; // Reader

                $this->setState('node.child',$this->child_pid);
            }

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

            if ( $child_setup && is_callable(array($this,$child_setup)) )
                $this->$child_setup();

            // Child
            note( debug, "Starting child loop" );
            $this->child_loop($child);
        }
    }/*}}}*/

    function restart_child() {
        $oldchild = $this->child_pid;

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
            $this->intercom_socket = $sockets[0]; 

            $this->setState('node.child',$this->child_pid);

            posix_kill( $oldchild, 9 );
        } else {
            $this->parent_pid = posix_getppid();

            // Make shure we stop the parent if child dies
            register_shutdown_function(array($this,'kill_parent') );

            $this->intercom_socket = $sockets[1]; // Writer
            socket_close($sockets[0]);

            if ( $child_setup && is_callable(array($this,$child_setup)) )
                $this->$child_setup();

            // Child
            note( debug, "Starting child loop" );
            $this->child_loop($this->child_func);
        }
    }

// MAIN LOOPS
    function parent_loop() {/*{{{*/
        while(1) {
            // Wait for a udp package
            if ( !$pkt = $this->udp->listen() )
                continue;

            // Ignore invalid packages
            if ( !isset($pkt['from']) || (!isset($pkt['cmd'])&&!isset($pkt['type'])) )
                continue;

            //if ( !isset($pkt['type']) || $pkt['type'] != 'log' )
            //    note(debug,$pkt);

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
    }/*}}}*/
    function child_loop($function) {/*{{{*/
        while(1) {
            call_user_func(array($this,$function));
        }
    }/*}}}*/

// COMMANDS
    function emergency($msg) {
        // TODO: Do some broadcasting here
        note(emergency,$msg);

        if ( isset($this->parent_pid) )
            $this->kill_parent();

        $this->bye();

        die($msg);
    }
    function getVariables() {/*{{{*/
        $data = get_object_vars($this);
        unset($data['udp']);
        unset($data['intercom_socket']);
        return $data;
    }/*}}}*/
    function kill($pkt=null) {/*{{{*/
        if ($pkt) 
            $this->ack($pkt);
        $this->kill_child();
    }/*}}}*/

    function kill_parent() {/*{{{*/
        $this->bye();
        note(warning, "Died in child, killing parent");
        posix_kill( $this->parent_pid, SIGINT );
        die();
    }/*}}}*/
    function kill_child()  {/*{{{*/
        if ( $this->udp->istcp )
            return;

        // Send bye
        $this->bye();

        // Kill the child
        note(warning, "Died in parent, killing child");
        posix_kill( $this->child_pid, 9 );
        die();
    }/*}}}*/

// RESPONSES
    function broadcast( $data ) {/*{{{*/
        if ( !isset($data['from']) )
            $data['from'] = $this->peer;

        return $this->udp->broadcast( $data );
    }/*}}}*/
    function broadcast_event( $event,$data=array() ){/*{{{*/
        $this->broadcast(array(
            'type' => 'event',
            'event' => $event,
            'data' => $data
        ));
    }/*}}}*/
    function greetings(){/*{{{*/
        if ( !isset($this->componentclasses) ) {
            note(warning,'No component classes defined!');
            $this->componentclasses = array();
        }

        if ( !isset($this->settings) ) {
            note(warning,'No component settings defined!');
            $this->settings = array();
        }

        $this->broadcast(array(
            'from' => $this->peer,
            'cmd' => 'greetings',
            'class' => $this->componentclasses,
            'settings' => $this->settings
        ));

        $this->sendState();
    }/*}}}*/
    function bye(){/*{{{*/
        if ( !$this->hashed ) 
            $this->broadcast(array(
                'cmd' => 'bye'
            ));
    }/*}}}*/
    function ack($pkt,$ret=NULL){/*{{{*/
        $this->broadcast(array(
            'to' => $pkt['from'],
            'cmd' => 'ack',
            'ret' => $ret,
            'pkt' => $pkt
        ));
    }/*}}}*/
    function nak($pkt,$ret=null){/*{{{*/
        $this->broadcast(array(
            'to' => $pkt['from'],
            'cmd' => 'nak',
            'pkt' => $pkt,
            'ret' => $ret
        ));
    }/*}}}*/

// INTERCOM
    function intercom( ) {/*{{{*/
        // Send the message
        $ic = json_encode( func_get_args() );

        // Send the alarm signal to parent
        posix_kill( $this->parent_pid, SIGALRM );

        $this->intercom_id++;

        $ic2 = wordwrap($ic,4000,"\n",true);
        $parts = explode("\n",$ic2);
        //note('debug','Sending intercom '.$this->intercom_id.' | '.strlen($ic).' byte in '.count($parts).' parts');


        foreach( $parts as $key => $ic ) {
            $ic = base64_encode($ic);
            $ic = json_encode( array(
                'id' => $this->intercom_id,
                'p' => count($parts),
                'd' => $ic
            ))."\n";

            if ( !socket_write( $this->intercom_socket,$ic,strlen($ic) ) ) {
                $code = socket_last_error();
                if ( $code == 32 )
                    die("Intercom socket broken, DIE");

                return trigger_error("Failed to send intercom using IC socket: $code");
            }
        }

        socket_write( $this->intercom_socket,"\n",1 );
    }/*}}}*/
    function recive_intercom() {/*{{{*/
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
        $intercoms = array();

        foreach ( $buff as $buffen ){
            if ( !trim($buffen) )
                continue;

            if ( ($args = json_decode($buffen,true)) === NULL )
                return trigger_error("Syntax error in intercom JSON (".trim($buffen).")");

            if ( !isset($intercoms[$args['id']]) )
                $intercoms[$args['id']] = array(
                    'cnt' => $args['p'],
                    'parts' => array()
                );

            $intercoms[$args['id']]['parts'][] = base64_decode($args['d']);

            if ( $intercoms[$args['id']]['cnt'] == count($intercoms[$args['id']]['parts']) ) {
                $data = implode($intercoms[$args['id']]['parts']);

                note(debug,'Recived intercom '.$args['id']);

                if ( ($data2 = json_decode($data)) === NULL )
                    return trigger_error("Syntax error in intercom JSON (".trim($data).")");

                unset($intercoms[$args['id']]);

                // Call the intercom_event
                if ( is_callable(array($this,'intercom_event')) ) {
                    call_user_func_array( array($this,'intercom_event'),$data2 );
                } else
                    trigger_error("Got intercom event but no intercom_event function exists!");
            }
        }
    }/*}}}*/

// STATES
    function getState( $pkt ) {/*{{{*/
        return $this->state;
    }/*}}}*/
    function readState( $path ) {/*{{{*/
        $path = explode('.',$path);
        $path = array_filter($path, 'strlen'); // Remove empty

        $a = '$this->state';
        foreach($path as $key => $line) {
            if ( eval("return is_object($a);") ) {
                $a .= '->'.$line;
            } else {
                $a .= '["'.$line.'"]';
            }
        }

        return eval("
            if ( isset($a) ) {
                return $a;
            }"
            //} else {
            //    note(notice,'State $a is missing');
            //};"
        );
    }/*}}}*/
    function setState() {/*{{{*/
        if ( $this->hashed )
            return;

		$prev = $this->state;

        switch( func_num_args() ) {
            case 1:
                $list = func_get_args();
                if ( is_array($list[0]) || is_object($list[0]) ) {
                    foreach($list[0] as $key => $line)
                        $this->setStatePath($key,$line);
                }
                break;
            case 2:
                list($key,$value) = func_get_args();
                $this->setStatePath($key,$value);
                break;
        }

		if ( $prev != $this->state )
	        $this->sendState();
    }/*}}}*/
    function sendState() {/*{{{*/
        if ( $this->hashed )
            return;

        //$this->state['node']['memory'] = memory_get_usage();

        $this->broadcast( array(
            'type' => 'state',
            'data' => $this->state
        ));
    }/*}}}*/
    function setStatePath( $path, $value ) {/*{{{*/
        $path = explode('.',$path);
        $path = array_filter($path, 'strlen'); // Remove empty

        $a = '$this->state';
        foreach($path as $key => $line) {
            $a .= '["'.$line.'"]';
            eval("
                if ( !isset($a) || !is_array($a) ) {
                    $a = array();
                }
            ");
        }

        $string = "$a = \$value;";

        eval($string);
    }/*}}}*/

// SETTINGS
    function save_setting($pkt) {/*{{{*/
        // Fail if the setting key is not defined
        if ( !isset($this->settings[$pkt['key']]) )
            return $this->nak($pkt,array('msg' => 'Unknown setting "'.$pkt['key'].'"','value'=>''));
	
		if ( $err = $this->set_setting($pkt['key'],$pkt['value']) === true ) {
			note(notice,"Saved setting '".$pkt['key']."' to '".$pkt['value']."'");
	        $this->ack($pkt,array('value'=>$pkt['value']));
		} else {
            $this->nak($pkt,array('msg' => $err,'value'=>''));
		}
    }/*}}}*/
	function set_setting($key,$value) {

    if ( is_callable(array($this,'setting_validate')) ) {
        if ( $ret = $this->setting_validate($key,$value) )
          return $ret;
    }

    $file = '/etc/stampzilla/'.$this->peer.'.yml';

    // Check if file exists
    if ( !is_file($file) )
        if ( !touch($file) )
	      return "Failed to create config file ($file)!";

        // Try to read the settings file (yml)
        $data = spyc_load_file($file);

        $data[$key] = $value;

        $string = Spyc::YAMLDump($data);

        if ( !file_put_contents($file,$string) )
          return "Failed to save config file ($file)!";

        if ( is_callable(array($this,'setting_saved')) ) {
          return $this->setting_saved($key,$value);
        }

        $this->settings[$key] = $value;

		return true;
	}
    function read_settings() {/*{{{*/
        // Check if there are any settings defined
        if ( !isset($this->settings) )
            return;

        $file = '/etc/stampzilla/'.$this->peer.'.yml';

        // Check if file exists
        if ( !is_file($file) )
            return !note(debug,"Config file ($file) is missing!");

        // Try to read the settings file (yml)
        $data = spyc_load_file($file);
        foreach($this->settings as $key => $line) {
            if ( isset($data[$key]) ) {
                $this->settings[$key]['value'] = $data[$key];
            }
        }

        return true;
    }/*}}}*/
	function get_settings() {
		return $this->settings;
	}
    function setting($key) {/*{{{*/
        if ( !isset($this->settings[$key]['value']) )
            return;

        return $this->settings[$key]['value'];
    }/*}}}*/

}

?>

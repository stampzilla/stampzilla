<?php

class actor {
    private $parent = 0;
    private $child = 0;
    private $pid = 0;

    function __construct() {
        $this->udp = new udp('0.0.0.0',8282);
        $this->peer = '';

        define('actor',get_class($this));
        note(debug,"----- Starting up new actor named ".get_class($this)." -----");
    }

    function broadcast( $data ) {
        if ( !isset($data['from']) )
            $data['from'] = $this->peer;

        $pkg = json_encode($data);
        trigger_error($pkg);
        $this->udp->broadcast( $pkg );

        return sha1($pkg);
    }

    function checkin() {
        $this->broadcast($this->peer.'|php|parent:'.$this->parent.'|child:'.$this->child.'|pid:'.$this->pid);
        $this->parent=0;
        $this->child=0;
    }

    function exec( $to, $cmd ) {
        $this->broadcast(array(
            'to' => $to,
            'from' => $this->peer,
            'cmd' => $cmd
        ));
    }

    function intercom( $data ) {
        $data['intercom'] = $this->peer;

        $this->udp->broadcast( json_encode($data) );
    }

    function getInterface(){

        if( !array_key_exists('name',$this->interface))
            return array('error'=>'no name set');
        if( !array_key_exists('type',$this->interface))
            return array('error'=>'no type set');
        if( !array_key_exists('events',$this->interface))
            return array('error'=>'no events set');
        if( !array_key_exists('commands',$this->interface))
            return array('error'=>'no commands set');

        //return array_intersect_assoc($this->interface,array('name','type','events','commands'));
        return $this->interface;
    }

    function start( $id,$check_fnk='' ) {
        $this->peer = $id;

        // Create a name if the node dosnt have one
        if ( !$this->peer ) {
            $this->peer = md5(time());
            $hashed = true;
        }

        note(debug,"----- Starting up runner with callsign ".$this->peer." -----");

		if( is_callable(array($this,'startup')) ) {
			$this->startup();
		}

        if ( $check_fnk ) {
            $this->pidParent = posix_getpid();
            if ( $this->pidChild = pcntl_fork() )
                trigger_error('Forked process with pid:'.$this->pidChild);
        } else
            $this->pidChild = '1';

        if ( $this->pidChild == -1 ) {
            trigger_error('Failed to fork');
            die();
        } elseif ( $this->pidChild ) { // Parent
            if ( !isset($hashed) )
                $this->broadcast(array(
                    'to' => 'global',
                    'from' => $this->peer,
                    'cmd' => 'greetings'
                ));

            while(1) {
                $this->parent++;
                if ( !$pkt = $this->udp->listen() )
                    continue;

                if ( isset($pkt['intercom']) && $pkt['intercom'] == $this->peer ) {
                    trigger_error( $pkt );
                    $this->intercom_event( $pkt );
                    continue;
                }

                if ( !isset($pkt['from']) || !isset($pkt['to']) || !isset($pkt['cmd']) )
                    continue;

                if ( $pkt['from'] == $this->peer )
                    continue;

                trigger_error($pkt);

                // Commands to this node
                if ( $pkt['to'] == $this->peer ) {
                    $res = $this->event( $pkt );

                    if ( $res )
                        $this->broadcast(array(
                            'to' => $pkt['from'],
                            'from' => $this->peer,
                            'cmd' => 'ack',
                            'msg' => sha1($in),
                            'ret' => $res
                        ));
                    elseif ( $res !== null )
                        $this->broadcast(array(
                            'to' => $pkt['from'],
                            'from' => $this->peer,
                            'cmd' => 'nak',
                            'msg' => sha1($in)
                        ));

                // Commands not to this node :)
                } else {
                    if ( $res = $this->event( $pkt ) ) {
                    	if ( $pkt['to'] != 'global' )
	                       	note(debug,$pkt);

						if ( $res )
							$this->broadcast(array(
								'to' => $pkt['from'],
								'from' => $this->peer,
								'cmd' => 'ack',
								'msg' => sha1($in),
								'ret' => $res
							));
						elseif ( $res !== null )
							$this->broadcast(array(
								'to' => $pkt['from'],
								'from' => $this->peer,
								'cmd' => 'nak',
								'msg' => sha1($in)
							));
                    }
                }
            }
        } else { // Child
            if ( $check_fnk ) {
                while(1) {
                    $this->parent++;
                    call_user_func(array($this,$check_fnk));
                }
            }
            trigger_error('Forked child has nothing to do, exits');
        }
    }
}

?>

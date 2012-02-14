#!/usr/bin/php
<?php

require_once "../lib/component.php";

class squeezebox extends component {

    protected $componentclasses = array('audio.player');
    protected $settings = array(
        'hostname'=>array(
            'type'=>'text',
            'name' => 'Hostname',
            'required' => true
        ),
        'port'=>array(
            'type'=>'text',
            'name' => 'CLI port',
            'required' => true
        ),
        'username'=>array(
            'type'=>'text',
            'name' => 'Username'
        ),
        'password'=>array(
            'type'=>'password',
            'name' => 'Password'
        )
    );

    protected $commands = array(
        'next' => 'Next song.',
        'prev' => 'Previous song.',
        'power' => 'Power 1 or 0',
        'play' => 'Start playing',
        'pause' => 'Pause playback',
        'stop' => 'Stop playback',
    );

    function startup( ){
        $this->connect();
    }

    function connect() {
        $host = $this->setting('hostname');
        $port = $this->setting('port');
        $u = $this->setting('username');
        $p = $this->setting('password');

        if ( $host && $port ) {
            note(notice,"Connecting to $host:$port");
            $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
            socket_connect($this->socket,$host,$port);

            if ( $u && $p ) {
                note(notice,"Sending auth parameters ($u)");
                $this->send("login $u $p\n");
            }

            $this->send("players 0 999 \n");
            //$this->send("listen 1\n");
            $this->send("subscribe alarm,pause,play,stop,client,mixer\n");
        }
    }

    function send( $text ) {
        $text = trim($text);
        note(debug,"Send: ".trim($text));
        $text .= "\n";
        return socket_write($this->socket, $text,strlen($text));
    }

    function next($pkt){
        return $this->send($this->nameToMac($pkt['id']).' playlist jump +1');
    }
    function prev($pkt){
        return $this->send($this->nameToMac($pkt['id']).' playlist jump -1');
    }
    function play($pkt){
        return $this->send($this->nameToMac($pkt['id']).' play');
    }
    function playRandom($pkt){
        return $this->send($this->nameToMac($pkt['id']).' randomplay tracks');
    }
    function stop($pkt){
        return $this->send($this->nameToMac($pkt['id']).' stop');
    }
    function pause($pkt){
        return $this->send($this->nameToMac($pkt['id']).' pause');
    }
    function power($pkt){
        if(!isset($pkt['power']))
            $pkt['power'] = (!$this->state[$pkt['id']]['power'])+0;
        return $this->send($this->nameToMac($pkt['id']).' power '.$pkt['power']);
    }
    function volume($pkt){
        if(!isset($pkt['volume']))
			return false;
        return $this->send($this->nameToMac($pkt['id']).' mixer volume '.$pkt['volume']);
    }

    function intercom_event($cmd) {
        $cmd = trim($cmd);
        list($main,$data) = explode(' ',$cmd,2);
        $main = urldecode($main);
    
        $data = explode(' ',$data);
        foreach($data as $key => $line)
            $data[$key] = urldecode($line);        

        note(debug,'Read: '.$cmd);
        
        switch($main) {
			case 'play':
				$this->setState('playing',true);
				break;
			case 'pause':
			case 'stop':
				$this->setState('playing',false);
				break;
            case 'client':
                switch($data[0]) {
                    case 'disconnect':
                    case 'reconnect':
                    case 'new':
        	    		$this->send("players 0 999 \n");
						break;
                }
            case 'alarm':
                switch($data[0]) {
                    case 'sound':
                    case 'end':
                    case 'snooze':
                    case 'snooze_end':
                }
            case 'listen':
                $this->setState('server.connected',true);
                break;
            case 'players':
                $players = array();
                $current = '';
                foreach($data as $key => $line) {
                    $line = explode(':',$line,2);
                    if ( $line[0] == 'playerid' ) {
                        $current = $line[1];
                        $players[$current] = array();
                        $players[$current][$line[0]] = $line[1];

                        // request power
                        //$this->send(urlencode($current)." power ?\n");
                        // request volume
                        //$this->send(urlencode($current)." mixer volume ?\n");
                        $this->send(urlencode($current)." status 0 999 subscribe:60\n");
                    } elseif ($current) {
                        $players[$current][$line[0]] = $line[1];
                    }
                }
                foreach($players as $key=>$player){
                    $players[$player['name']] = $players[$key];
                    unset($players[$key]);
                }
                $this->setState($players);
				break;
			case 'subscribe':
				break;
            default:
                $main = $this->macToName($main);
                if ( isset($this->state[$main]) ) {
                    switch($data[0]) {
                        case 'power':
                            $this->setState($main.'.power',$data[1]);
                            break;
						/*case 'playlist':
							switch($data[1]) {
								case 'newsong':
                                    $this->setState($main.'.title',$data[2]);
									break;
							}
                        */
                        case 'mixer':
                            switch($data[1]) {
                                case 'volume':
                                    if ( ctype_digit(substr($data[2],0,1)) )
                                        $this->setState($main.'.mixer.volume',$data[2]);
                                    break;
                            }
                            break;
                        /*
                        case 'prefset':
                            switch($data[1]) {
                                case 'server';
                                    switch($data[2]) {
                                        case 'volume':
                                            $this->setState($main.'.mixer.volume',$data[3]);
                                            break;
                                    }
                                    break;
                            }
                            break;*/
						case 'status':
							$prev = $this->state[$main];

							unset($data[0]);
							foreach($data as $key => $line) {
								$data[$key] = explode(':',$line,2);

								if( $data[$key][0] == 'mode' ) {
									switch($data[$key][1]) {
										case 'play':
											$data['playing'] = true;
											break;
										case 'pause':
										case 'stop':
											$data['playing'] = false;
											break;
									}
								}

								if ( !isset($data[$key][1]) || $data[$key][0] == '-' || $data[$key][0] == 'tags' || $data[$key][0] == '2' || $data[$key][0] == 'mode') {
									unset($data[$key]);
									continue;
								}

								if ( $data[$key][0] == 'playlist index' ) {
									$index = $data[$key][1];
								}

								if ( isset($index) &&  $index != $data['playlist_cur_index']) {
									unset($data[$key]);
									continue;
								} elseif (isset($index)) {
									$data[$key][0] = 'song '.$data[$key][0];
								}

								if ( $data[$key][0] == 'time' ) {
									$data[$key][1] = time() - floor($data[$key][1]);
								}

								if ( strstr($data[$key][0],' ') ) {
									$line = explode(' ',$data[$key][0],2);
									if ( !isset($data[$line[0]]) )
										$data[$line[0]] = array();

									$data[$line[0]][$line[1]] = $data[$key][1];
								} else {
									$data[$data[$key][0]] = $data[$key][1];
								}


								unset($data[$key]);
							}
							foreach($this->state[$main] as $key => $line) {
								if ( !isset($data[$key]) )
									$data[$key] = $line;
							}

							if ( $prev != $data ) {
								$this->setState($main,$data);
							}
							break;
                    }
				}
        }
    }

    function macToName($mac){
        foreach($this->state as $player){
            if(isset($player['playerid']) && $player['playerid'] === $mac)
                return $player['name'];
        }

		note(warning, 'Cant find player with id:'.$mac);
        return false;
    }
    function nameToMac($name){
        return urlencode($this->state[$name]['playerid']);
    }

    function _child() {
        if( false == ($bytes = socket_recv($this->socket,$buff, 2048,0) ) ){
            sleep(10000);
            die("DIE");
        }

        $this->buff .= $buff;

        while ( $pos = strpos($this->buff,"\n")){
            $cmd = substr($this->buff,0,$pos);
            $this->buff = substr($this->buff,$pos+1);

            $this->intercom($cmd);
        }
    }
}



$b = new squeezebox();
$b->start('squeezebox','_child');


?>

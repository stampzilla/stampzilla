#!/usr/bin/php
<?php
require_once "../lib/component.php";

class xbmc3 extends component {
    private $id = 1;
    private $active_player ='';
    private $lastcmd = array();
	private $players = array();

    private $commands = array(/*{{{*/
        'play'=>'play'
    );

    // INTERFACE
    protected $componentclasses = array('video.player','audio.player');
    protected $settings = array(
        'hostname'=>array(
            'type'=>'text',
            'name' => 'Hostname',
            'required' => true
        ),
        'port'=>array(
            'type'=>'text',
            'name' => 'Web port',
            'required' => true
        )
    );/*}}}*/

    function startup() {/*{{{*/
        $this->connect($this->setting('hostname'),9090);
    }/*}}}*/

    function connect($host,$port) {/*{{{*/
        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);

        if ( !$host || !$port )
            return;

        socket_connect($this->socket,$host,$port);

        $this->json('JSONRPC.Version');
        $this->json('Player.GetActivePlayers');
		//$this->json('VideoLibrary.GetTVShows');
    }/*}}}*/

	function notify($pkt) {
		$this->json('JSONRPC.NotifyAll',array('stampzilla',$pkt['message']),$pkt);
	}

    function intercom_event($event){

        // Decode the incomming message/*{{{*/
        note(debug,"From XBMC: \n".substr($event,0,100)."\n <> \n".substr($event,-100));
        if ( !$event = json_decode($event) ) {
            note(error,"Syntaxerror from XBMC");
            return;
        }/*}}}*/
        // Handle error messages/*{{{*/
        if(isset($event->error)){
            if ( isset($this->lastcmd[$event->id]['var']['to']) )
                $this->nak( $this->lastcmd[$event->id]['var'] );

            return trigger_error($event->error->message." (".$event->error->data->stack->message.")",E_USER_WARNING);
        };/*}}}*/

        // Handle sent commands
        if( isset($event->id) && isset($this->lastcmd[$event->id]) ) {
            $cmd = $this->lastcmd[$event->id]['data'];
            $var = $this->lastcmd[$event->id]['var'];
            $params = $this->lastcmd[$event->id]['params'];
            unset($this->lastcmd[$event->id]);

            switch($cmd['method']) {
                case 'JSONRPC.Version':
                    $this->api_version = $event->result->version;

                    if ( $this->api_version != 3 && $this->api_version != 4 ){
                        trigger_error("Unsupported JSON.RPC API version ({$this->api_version})",E_USER_ERROR);
                        die();
                    }
                    break;
                case 'Player.GetActivePlayers':
					$found = array();
					foreach ( $event->result as $player ) {
						$this->players[$player->playerid]['type'] = $player->type;
						$found[$player->playerid] = true;
					}

					foreach( $this->players as $key => $line ) {
						if ( !isset($found[$key]) ) {
							unset($this->players[$key]['media']);
							$this->players[$key]['playing'] = false;
						}
					}
                        
					$this->setPlayerStates();

                    break;
				case 'VideoLibrary.GetTVShows':
				print_r($event->result);
					break;
				case 'Player.GetItem':
					$this->players[$params[0]]['media'] = $event->result->item;
					$this->setPlayerStates();
					break;
				case 'Player.GetProperties':
					foreach( $event->result as $key => $line ) {
						switch ($key) {
							case 'speed':
								if ( $line == 0 )
									$this->players[$params[0]]['playing'] = false;
								else
									$this->players[$params[0]]['playing'] = true;
								break;
							default:
								$this->players[$params[0]][$key] = $line;
								break;
						}
					}
					$this->setPlayerStates();
					break;
				default:
					print_r($event);
					break;
            }

            if ( isset($var['to']) )
                $this->ack($var,$event);
        } else {
        // Handle broadcasts
            if(isset($event->method)){
                switch($event->method){
					case 'Player.OnPlay':
						$id = $event->params->data->player->playerid;

						if ( !isset($this->players[$id]) )
							$this->players[$id] = array();

						$this->players[$id]['playing'] = true;

						$this->setPlayerStates();

						break;
					case 'Player.OnPause':
						$id = $event->params->data->player->playerid;

						if ( !isset($this->players[$id]) )
							$this->players[$id] = array();

						$this->players[$id]['playing'] = false;

						$this->setPlayerStates();
						break;
					case 'Player.OnStop':
						$this->json('Player.GetActivePlayers');
						break;
					case 'GUI.OnScreensaverActivated':
						$this->setState('screensaver',true);
						break;
					case 'GUI.OnScreensaverDeactivated':
						$this->setState('screensaver',false);
						break;
				}
            }
        }
    }

	function setPlayerStates() {
		foreach($this->players as $key => $line) {
			if ( isset($line['type']) ) {
				if ( !isset($line['playing']) ) 
					$this->json('Player.GetProperties',array($key,array('speed')));
				elseif ( $line['playing'] && !isset($line['media']) ) 
					$this->json('Player.GetItem',array($key));

				$this->setState($line['type'],$line);
			} else {
				return $this->json('Player.GetActivePlayers');
			}
		}
	}


    function getId(){/*{{{*/
        $this->id++;
        if($this->id > 10000)
            $this->id=1;
        return $this->id;
    }
/*}}}*/

    function json($cmd,$params=null,$var = array()){/*{{{*/
        $data = array(
            'jsonrpc'=>"2.0",
            'method'=>$cmd,
            'params'=>$params,
            'id'=>$this->getId()
        );

        if($params==null)
            unset($data['params']);

        $this->lastcmd[$this->id] = array('var'=>$var,'params'=>$params,'data'=>$data);
        $text =  json_encode($data);

        return $this->send($text);
    }/*}}}*/

    function send($text){/*{{{*/
        note(notice,$text);
        return socket_write($this->socket,$text."\n\r",strlen($text));
    }/*}}}*/

    function readSocket(){/*{{{*/
        if( false == ($bytes = @socket_recv($this->socket,$buff, 2048,0) ) ){
            sleep(1); // Sleep a litte, and wait for connection
        }
        $this->buff .= $buff;

        while ( $pos = preg_match('/\}\n?(\{|$)/',$this->buff,$match,PREG_OFFSET_CAPTURE) && substr_count(substr($this->buff,0,$match[0][1]+1),'{') == substr_count(substr($this->buff,0,$match[0][1]+1),'}') ){
            $pos = $match[0][1];
            $cmd = substr($this->buff,0,$pos+1);
            $this->buff = substr($this->buff,$pos+1);
            $this->intercom($cmd);
        }
    }/*}}}*/


}

$hostname = exec('hostname');
if ( !$hostname ) {
	note(critical,'Failed to get hostname (exec hostname)');
	die();
}

$xbmc3 = new xbmc3();
$xbmc3->start($hostname.'_xbmc3','readSocket');

?>

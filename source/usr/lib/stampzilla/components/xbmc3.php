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
		$this->json('VideoLibrary.GetTVShows');
    }/*}}}*/

    function intercom_event($event){

        // Decode the incomming message/*{{{*/
        note(debug,"From XBMC: \n".substr($event,0,100)."\n <> \n".substr($event,-100));
        if ( !$event = json_decode($event) ) {
            note(error,"Syntaxerror from XBMC");
            return;
        }/*}}}*/
        // Handle error messages/*{{{*/
        if(isset($event->error)){
            if ( $this->lastcmd[$event->id]['var']['to'] )
                $this->nak( $this->lastcmd[$event->id]['var'] );

            return trigger_error($event->error->message,E_USER_WARNING);
        };/*}}}*/

        // Handle sent commands
        if( isset($event->id) && isset($this->lastcmd[$event->id]) ) {
            $cmd = $this->lastcmd[$event->id]['data'];
            $var = $this->lastcmd[$event->id]['var'];
            unset($this->lastcmd[$event->id]);

            switch($cmd['method']) {
                case 'JSONRPC.Version':
                    $this->api_version = $event->result->version;

                    if ( $this->api_version != 3 ){
                        trigger_error("Unsupported JSON.RPC API version ({$this->api_version})",E_USER_ERROR);
                        die();
                    }
                    break;
                case 'Player.GetActivePlayers':
					foreach ( $event->result as $player ) {
						$this->players[$player->playerid]['type'] = $player->type;
					}
				print_r($event->result);
                        
					$this->setPlayerStates();

                    break;
				case 'VideoLibrary.GetTVShows':
				print_r($event->result);
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

						$this->players[$id]['state'] = true;

						$this->setPlayerStates();

						break;
					case 'Player.OnPause':
						$id = $event->params->data->player->playerid;

						if ( !isset($this->players[$id]) )
							$this->players[$id] = array();

						$this->players[$id]['state'] = false;

						$this->setPlayerStates();
						break;
				}
            }
        }
    }

	function setPlayerStates() {
		print_r($this->players);
		foreach($this->players as $key => $line) {
			if ( !isset($line['state']) )
				continue;

			if ( isset($line['type']) ) {
				$this->setState($line['type'],$line['state']);
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

        $this->lastcmd[$this->id] = array('var'=>$var,'data'=>$data);
        $text =  json_encode($data);
        return $this->send($text);
    }/*}}}*/

    function send($text){/*{{{*/
        note(debug,$text);
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

$xbmc3 = new xbmc3();
$xbmc3->start('xbmc','readSocket');

?>

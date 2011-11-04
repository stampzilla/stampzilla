<?php
require_once "../lib/component.php";

class xbmc extends component {
    private $id = 1;
    private $active_player ='';
    private $lastcmd = array();
    private $commands = array(
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
	);

	function startup() {
		$this->connect($this->setting('hostname'),9090);
	}

    function setting_saved($key, $value) {
    }

	function connect($host,$port) {
        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);

		if ( !$host || !$port )
			return;

        socket_connect($this->socket,$host,$port);

        $this->json('JSONRPC.Version');
        $this->json('Player.GetActivePlayers');

	}
    function getId(){
        $this->id++;
        if($this->id > 10000)
            $this->id=1;
        return $this->id;
    }

    function event($pkt){
        return null;
        $this->json($pkt['cmd']);
        return true;
    }

    //handle raw packets sent like this: to=xbmc-test&type=cmd&cmd=raw&raw={"jsonrpc": "2.0", "method": "VideoLibrary.GetMovies","params":{"fields":["title"]},"id": 1}}}
    function raw($pkt) {
        if(is_array($pkt['raw']))
            $this->send(json_encode($pkt['raw']));
        elseif(is_string($pkt['raw']))
            $this->send($pkt['raw']);
        return true;
    }

	//recive a command and send it to xbmc
    function cmd($pkt){
        $this->json($pkt['run'],null,$pkt);
    }

	function state($pkt){
		if ( isset($this->state) )
			return $this->state;
	}
	function media($pkt){
        $this->json('VideoLibrary.GetMovies',array('fields'=>array('rating','length','tagline','lastplayed')),$pkt);
	}

    //COMMANDS FOR CONTROLING PLAYER

    function play($pkt){
        if( $this->active_player && $this->state->paused){
            $this->json('VideoPlayer.PlayPause',null,$pkt);
        }
        else
            $this->nak($pkt['from']);
    }
    function playMovie($pkt){
            $this->json('XBMC.Play',array('movieid'=>(int)$pkt['file']),$pkt);
		return true;
    }
    function pause($pkt){
        if( $this->active_player && !$this->state->paused){
            $this->json('VideoPlayer.PlayPause',null,$pkt);
        }
        else
            $this->nak($pkt['from']);
    }
    //stop active player
    function stop($pkt){

        if($this->active_player){
            $this->json($this->active_player.'Player.Stop',null,$pkt);
        }
        else
            $this->nak($pkt['from']);

    }

    function gettime($pkt){
        if($this->active_player && $this->active_player != 'Picture'){
            $this->json($this->active_player.'Player.GetTime',null,$pkt);
        }
        else
            $this->nak($pkt['from']);
    }


    function send($text){
        note(debug,$text);
        return socket_write($this->socket,$text."\n\r",strlen($text));
    }

    function intercom_event($event){

        // Decode the incomming message
        note(debug,"From XBMC: \n".substr($event,0,100)."\n <> \n".substr($event,-100));
        if ( !$event = json_decode($event) ) {
            note(error,"Syntaxerror from XBMC");
            return;
        }

        // Handle error messages
        if(isset($event->error)){
            if ( $this->lastcmd[$event->id]['var']['to'] )
                $this->nak( $this->lastcmd[$event->id]['var'] );

            return trigger_error($event->error->message,E_USER_WARNING);
        };

        // Handle sent commands
        if( isset($event->id) && isset($this->lastcmd[$event->id]) ) {
            $cmd = $this->lastcmd[$event->id]['data'];
            $var = $this->lastcmd[$event->id]['var'];
            unset($this->lastcmd[$event->id]);

            switch($cmd['method']) {
                case 'JSONRPC.Version':
                    $this->api_version = $event->result->version;

                    if ( $this->api_version != 2 ){
                        trigger_error("Unsupported JSON.RPC API version ({$this->api_version})",E_USER_ERROR);
                        die();
                    }
                    break;
                case 'Player.GetActivePlayers':
                    if($event->result->video)
                        $this->active_player = 'Video';
                    if($event->result->audio)
                        $this->active_player = 'Audio';
                    if($event->result->picture)
                        $this->active_player = 'Picture';

                    if($this->active_player){
                        $this->json($this->active_player.'Player.State');
                    } else {
						$this->state = new stdClass();
						$this->state->paused = false;
						$this->state->playing = false;
                        $this->broadcast_event('state',$this->state);
					}
                        
                    break;
                case 'VideoLibrary.GetMovies':

                    $host = $this->setting('hostname');
                    $port = $this->setting('port');

                    foreach( $event->result->movies as $key => $line ) {
                        $event->result->movies[$key]->thumbnail = 'http://'.$host.':'.$port.'/vfs/'.$line->thumbnail;
                    }
                    break;
                case 'VideoPlayer.State':
                case 'PicturePlayer.State':
                case 'AudioPlayer.State':
                    $this->state = $event->result;
                    if($this->state->paused)
                        $this->broadcast_event('state',$this->state);
                    else
                        $this->broadcast_event('state',$this->state);
                    break;
            }

            if ( isset($var['to']) )
                $this->ack($var,$event);
        } else {
        // Handle broadcasts
            if(isset($event->method)){
                switch($event->method){
                    case 'Announcement':
                        switch($event->params->message){
                            case 'PlaybackResumed':
                                $this->state->paused = false;
                                $this->broadcast_event('state',$this->state);
                                break;
                            case 'PlaybackPaused':
                                $this->state->paused = true;
                                $this->broadcast_event('state',$this->state);
                                break;
                            case 'PlaybackStopped':
                                $this->active_player = '';
                                $this->state->paused = false;
                                $this->state->playing = false;
                                $this->broadcast_event('state',$this->state);
                                break;
                            case 'PlaybackStarted':
                                $this->json('Player.GetActivePlayers');
                                break;
                        }
                        break;
                    case 'test':

                        break;
                }

            }
        }
    }

    function json($cmd,$params=null,$var = array()){


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
    }

    function readSocket(){
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
    }


}

$xbmc = new xbmc();
$xbmc->start('xbmc','readSocket');

?>

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
        'on' => 'Turn on player',
        'off' => 'Turn off player',
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
            $this->send("listen 1\n");
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
    function stop($pkt){
        return $this->send($this->nameToMac($pkt['id']).' stop');
    }
    function pause($pkt){
        return $this->send($this->nameToMac($pkt['id']).' pause');
    }
    function off($pkt){
        return $this->send($this->nameToMac($pkt['id']).' power 0');
    }
    function on($pkt){
        return $this->send($this->nameToMac($pkt['id']).' power 1');
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
                        $this->send(urlencode($current)." power ?\n");
                        // request volume
                        $this->send(urlencode($current)." mixer volume ?\n");
                    } elseif ($current) {
                        $players[$current][$line[0]] = $line[1];
                    }
                }
                foreach($players as $key=>$player){
                    $players[$player['name']] = $players[$key];
                    unset($players[$key]);
                }
                $this->setState($players);
            default:
                $main = $this->macToName($main);
                if ( isset($this->state[$main]) ) {
                    switch($data[0]) {
                        case 'power':
                            $this->setState($main.'.power',$data[1]);
                            break;
                        case 'mixer':
                            switch($data[1]) {
                                case 'volume':
                                    if ( ctype_digit(substr($data[2],0,1)) )
                                        $this->setState($main.'.mixer.volume',$data[2]);
                                    break;
                            }
                            break;
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

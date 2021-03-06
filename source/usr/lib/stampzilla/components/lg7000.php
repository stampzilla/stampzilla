#!/usr/bin/php
<?php

require_once '../lib/component.php';

class lg7000 extends component {
    protected $componentclasses = array('video.switch');
    protected $settings = array();
    protected $next = 0;
    protected $commands = array(
        'power' => 'Controls the power',
    );
    protected $que = array();

    protected $sources = array(
        'digital' => '00',
        'analog' => '10',
        'av1' => '20',
        'av2' => '21',
        'av3' => '22',
        'av4' => '23',
        'komponent1' => '40',
        'komponent2' => '41',
        'komponent3' => '42',
        'komponent4' => '43',
        'rgb1' => '50',
        'rgb2' => '51',
        'rgb3' => '52',
        'rgb4' => '53',
        'hdmi1' => '90',
        'hdmi2' => '91',
        'hdmi3' => '92',
        'hdmi4' => '93',
    );

    function startup() {
        $this->connect();

    }

    function connect(){

        if ( isset($this->socket) ) {
            $doRestart = true;
            socket_close($this->socket);
        }

        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if( !socket_connect($this->socket,'localhost',3002))
            return;

        if ( isset($doRestart) && isset($this->parent_pid) ) {
            note(notice,'Starting new lg7000 daemon');
            $this->intercom('connected');
            $this->que = array();
        }

    }

    function event() {
        $this->runQue();
    }

    function power($pkt) {
        if ( !isset($pkt['power']) )
            $pkt['power'] = (!$this->state['power'])+0;

        if ( $pkt['power'] ) {
            $this->send('ka 0 01',$pkt);
        } else {
            $this->send('ka 0 00',$pkt);
        }
    }

    function mute($pkt) {
        if ( !isset($pkt['mute']) )
            $pkt['mute'] = $this->state['mute']+0;

        if ( $pkt['mute'] ) {
            $this->send('ke 0 01',$pkt);
        } else {
            $this->send('ke 0 00',$pkt);
        }
    }

    function volume($pkt) {
        if ( !isset($pkt['volume']) )
            return false;

        $this->send('kf 0 '.$this->hex($pkt['volume']),$pkt);
    }

    function source($pkt){
        if ( !isset($pkt['source']) || !isset($this->sources[$pkt['source']]) )
            return false;

        $this->send('xb 0 '.$this->sources[$pkt['source']],$pkt);
    }

    function channel($pkt){
        if ( !isset($pkt['channel']) )
            return false;

        // Require correct format, ex A12 (Analog 12), D15 (Digital 15) or R2 (Radio 2)
        if ( is_numeric(substr($pkt['channel'],0,1)) || !is_numeric(substr($pkt['channel'],1)) )
            return false;

        switch( strtolower(substr($pkt['channel'],0,1)) ) {
            case 'd': // DTV
                $source = 90;
                break;
            case 'a': // ATV
                $source = 80;
                break;
            case 'r': // RADIO
                $source = 32;
                break;
        }

        $pkt['channel'] = substr($pkt['channel'],1);

        if ( $pkt['channel'] > 255 ) {
            $l = $pkt['channel'] % 256;
            $h = ($pkt['channel'] - $l) / 256;
        } else {
            $l = $pkt['channel'];
            $h = 0;
        }

        $this->send('ma 01 '.$this->hex($h).' '.$this->hex($l).' '.$source,$pkt);
    }

    function hex($int) {
        return ($int < 16 ) ?  "0".strtoupper(dechex($int)) : strtoupper(dechex($int));
    }

    function intercom_event($in) {
        if ( $in == 'not connected' ) {
            $this->setState('power',false);
            $this->setState('source',false);
            $this->setState('volume',false);
            $this->setState('mute',false);
            $this->setState('channel',false);
            return;
        }

        if ( $in == 'connected' ) {
            note(notice,'Reconnecting');
            $this->connect();
            note(notice,'Restarting child');
            return $this->restart_child();
        }
        if ( !$in ) {
            return $this->send('ka 0 ff'); // Check power
        }

        $data = explode(' ',$in);
        $data = explode('|',wordwrap($data[2],2,'|',true));

        $pkt = array_shift($this->que);
        $cmd = strtolower(substr($pkt['cmd'],0,2));

        if ( $data[0] == 'OK' ) {
            note(notice,'Got OK response: '.$in);

            switch($cmd) {
                case 'ka': // power
                    $power = !!hexdec($data[1]);

                    if ( !$this->state['power'] && $power ) {
                        $this->setState('power',$power);
                        note(debug,"Feeling sleepy...zZzz");
                        $done = time() + 8;
                        while($done>time()) {
                            sleep(1);
                        }
                        note(debug,"done sleeeping...zZzz");
                        $this->send('ke 0 ff'); // Check mute
                        $this->send('kf 0 ff'); // Check volume
                        $this->send('xb 0 ff'); // Check source
                        //$this->send('ma 0 ff ff ff'); // Check channel
                    } elseif ( $power ) {
                        $this->send('ke 0 ff'); // Check mute
                        $this->send('kf 0 ff'); // Check volume
                        $this->send('xb 0 ff'); // Check source
                    } elseif(isset($this->state['power']) && $power != $this->state['power']){
                        $this->setState(array('power'=>$power,'source'=>'','channel'=>''));
                    } elseif(!isset($this->state['power'])){
                        $this->setState(array('power'=>$power,'source'=>'','channel'=>''));
                    }

                    break;
                case 'ke': // mute
                    if($this->state['mute'] === hexdec($data[1]))
                        $this->setState('mute',!hexdec($data[1]));
                    break;
                case 'kf': // volume
                    if($this->state['volume'] !== hexdec($data[1]))
                        $this->setState('volume',hexdec($data[1]));
                    break;
                case 'xb': // source
                    $key = array_keys($this->sources, $data[1]);
                    if ( $key ) {
                        if($this->state['source'] !== $key[0])
                            $this->setState('source',$key[0]);

                        if ( $key[0] == 'digital' || $key[0] == 'analog' )
                            $this->send('ma 0 ff ff ff'); // Check channel
                        elseif ( $this->state['channel'] && $this->state['channel'] !=='' )
                            $this->setState('channel','');

                    } elseif( $this->state['source'] && $this->state['source'] !=='' ) {
                        $this->setState('source','');
                    }


                    break;
                case 'ma': // channel
                    $h = hexdec($data[1]);
                    $l = hexdec($data[2]);

                    $channel = $h * 256 + $l;

                    switch($data[3]) {
                        case '90':
                            $this->setState('channel','D'.$channel);
                            break;
                        case '80':
                            $this->setState('channel','A'.$channel);
                            break;
                        case '20':
                            $this->setState('channel','R'.$channel);
                            break;
                    }
                    break;
            }

            if ( $pkt['pkt'] )
                $this->ack($pkt['pkt']);
        } else {
            note(warning,'Got NoGod response: '.$in);

            if ( $pkt['pkt'] )
                $this->nak($pkt['pkt']);
        }

        $this->runQue();
    }

    function _child() {
        $contents = '';
        if( false == ($bytes = @socket_recv($this->socket,$buff, 2048,MSG_DONTWAIT) ) ){
            usleep(150000);
            if($bytes === 0 || socket_last_error() === 107 || socket_last_error() === 32){
                $this->intercom('not connected');
                sleep(10); // Sleep a litte, and wait for connection
                $this->connect();
            }
        }
        $this->buff .= $buff;

        while ( $pos = strpos($this->buff,chr(120))){
            $cmd = substr($this->buff,0,$pos);
            $this->buff = substr($this->buff,$pos+1);

            $this->intercom($cmd);
        }

        if ( $this->next < time() ) {
            $this->next = time() + 10;
            $this->intercom('');
        }
    }

    function send( $text,$ack = null ) {/*{{{*/
        note(debug,'Adding command to que '.$text);

        $this->que[] = array(
            'cmd' => $text,
            'pkt' => $ack,
            'sent' => false,
            'timestamp' => 0
        );

        $this->runQue();
    }/*}}}*/
    function runQue() {/*{{{*/
        $next = reset($this->que);

        if ( !$next )
            return;

        if ( $next['timestamp'] < time() && $next['sent']) {
            note(warning,"Unanswerd message (".$next['cmd']."), throwing it away");
            if( $next['pkt'] )
                $this->nak($next['pkt']);

            array_shift($this->que);
            $next = reset($this->que);
        }

        if ( !$next )
            return;

        if( ($next && !$next['sent']) ) {
            $text = $next['cmd'];

            note(notice,'Send: '.$text);
            $text .= "\r";

            socket_write($this->socket, $text,strlen($text));
            $this->que[key($this->que)]['sent'] = true;
            $this->que[key($this->que)]['timestamp'] = time() + 5;
        }
    }/*}}}*/
}

$b = new lg7000();
$b->start('lg7000','_child');

?>

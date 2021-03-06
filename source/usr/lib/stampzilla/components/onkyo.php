#!/usr/bin/php
<?php

//stty -F /dev/ttyUSB1 -brkint -icrnl -imaxbel -opost -isig -icanon -echo raw
//stty -F /dev/ttyUSB0 -brkint -icrnl -imaxbel -opost -isig -icanon -echo raw
//stty -F /dev/ttyUSB2 -brkint -icrnl -imaxbel -opost -isig -icanon -echo raw

require_once "../lib/component.php";

class onkyo extends component {
    protected $componentclasses = array('audio.switch, video.switch, audio.controller');
    protected $settings = array();
    protected $commands = array(
        'power' => 'Control power. 0 or 1',
    );
    protected $que = array();
    protected $test = null;

    function startup() {/*{{{*/
        $this->connect();

    }/*}}}*/

    function connect(){

        if ( isset($this->socket) ) {
            $doRestart = true;
            socket_close($this->socket);
        }

        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if( !socket_connect($this->socket,'localhost',3001))
            return;

        if ( isset($doRestart) && isset($this->parent_pid) ) {
            note(notice,'Starting new onkyo daemon');
            $this->intercom('connected');
        } else {
            $this->state['power'] = false;
            $this->send('!1PWRQSTN');
            $this->send('!1MVLQSTN');
            $this->send('!1SLIQSTN');
        }

    }

//FUNCTIONS
    function power($pkt){/*{{{*/
        if(!isset($pkt['power']))
            $pkt['power'] = (!$this->state['power'])+0;
        $this->send('!1PWR0'.$pkt['power'],$pkt);
    }/*}}}*/
    function source($pkt){/*{{{*/
        $s = array (
            'vcrdvr' => '00',
            'cblsat' => '01',
            'gametv' => '02',
            'aux1' => '03',
            'aux2' => '04',
            'dvd' => '10',
            'tape' => '20',
            'phono' => '22',
            'cd' => '23',
            'fm' => '24',
            'am' => '25',
            'tuner' => '26',
        );
        return $this->send('!1SLI'.$s[$pkt['source']],$pkt);
    }/*}}}*/
    function volume($pkt){/*{{{*/
        $vol = ($pkt['volume'] < 16 ) ?  "0".strtoupper(dechex($pkt['volume'])) : strtoupper(dechex($pkt['volume']));
        return $this->send('!1MVL'.$vol,$pkt);

        //to read vol
        //return hexdec($value);
    }/*}}}*/
    function radio($pkt){/*{{{*/
        return $this->send('!1TUN10580',$pkt);

    }/*}}}*/
    function sleep($pkt){/*{{{*/
        return $this->send('!1SLP00',$pkt);
    }/*}}}*/
    function mute($pkt){/*{{{*/
        return $this->send('!1AMT01',$pkt);
    }/*}}}*/
    function unmute($pkt){/*{{{*/
        return $this->send('!1AMT00',$pkt);
    }/*}}}*/

    function intercom_event($cmd){/*{{{*/
        if ( $cmd == 'not connected' ) {
            $this->setState('power',false);
            $this->setState('source',false);
            $this->setState('volume',false);
            return;
        }

        if ( $cmd == 'connected' ) {
            note(notice,'Reconnecting');
            $this->connect();
            note(notice,'Restarting child');
            return $this->restart_child();
        }

        note(notice,'Read: '.$cmd);
        $s = array (
            '00' => 'vcrdvr',
            '01' => 'cblsat',
            '02' => 'gametv',
            '03' => 'aux1',
            '04' => 'aux2',
            10 => 'dvd',
            20 => 'tape',
            22 => 'phono',
            23 => 'cd',
            24 => 'fm',
            25 => 'am',
            26 => 'tuner',
        );

        $pkt = reset($this->que);
        if ( ($pkt['pkt'] && trim($cmd) == trim($pkt['cmd'])) || strstr($pkt['cmd'],'QSTN') ){
            $pkt = array_shift($this->que);
            $this->ack($pkt['pkt']);
        }

        $val = substr($cmd,5);
        $cmd = substr($cmd,0,5);


        switch ($cmd){
            case '!1SLI':
                $this->setState('source',$s[$val]);
                break;
            case '!1PWR':

                if($val=='00')
                    $this->setState('source','');
                elseif (!$this->state['power']) {
                    $done = time() + 5;
                    while($done>time()) {
                        sleep(1);
                    }
                }

                $this->setState('power',$val+0);
                break;
            case '!1MVL':
                $this->setState('volume',hexdec($val));
                break;

        }

        $this->runQue();

    }/*}}}*/
    function _child() {/*{{{*/
        $contents = '';
        if( false == ($bytes = socket_recv($this->socket,$buff, 2048,0) ) ){
            $this->intercom('not connected');
            sleep(10); // Sleep a litte, and wait for connection
            $this->connect();
        }
        $this->buff .= $buff;

        while ( $pos = strpos($this->buff,chr(26))){
            $cmd = substr($this->buff,0,$pos);
            $this->buff = substr($this->buff,$pos+1);

            //send to event to check ack
            $this->intercom($cmd);
        }

    }/*}}}*/


    function send( $text,$ack = null ) {/*{{{*/
        note(debug,'Adding command to que '.$text);

        $this->que[] = array(
            'cmd' => $text."\r",
            'pkt' => $ack,
            'sent' => false,
            'timestamp' => 0,
            'retry' => 0
        );

        while(true){
            if(!$this->runQue())
                break;
        }
        note(warning,count($this->que));
    }/*}}}*/
    function runQue() {/*{{{*/
        note(debug,"Kolla kö");
        $next = reset($this->que);


        if ( !$next )
            return false;

        if ( $next['timestamp'] < time() && $next['sent']) {
            note(warning,"Unanswerd message (".$next['cmd']."), Trying again:".$next['retry']);
            if($next['retry'] < 2){
                note(warning,"Unanswerd message (".$next['cmd']."), throwing it away");
                if( $next['pkt'] )
                    $this->nak($next['pkt']);

                array_shift($this->que);
                $next = reset($this->que);
            }
            else{
                $next['sent'] = false;
                $this->que[key($this->que)]['retry'] = $this->que[key($this->que)]['retry']+1;
            }
        }

        if ( !$next )
            return false;

        if( ($next && !$next['sent']) ) {
            $text = $next['cmd'];

            note(notice,'Send: '.$text);
            $text .= "\r";

            socket_write($this->socket, $text,strlen($text));
            $this->que[key($this->que)]['sent'] = true;
            $this->que[key($this->que)]['timestamp'] = time() + 5;
        }

        return;
    }/*}}}*/

}


$t = new onkyo();
$t->start('onkyo','_child');

?>

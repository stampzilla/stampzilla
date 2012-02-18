#!/usr/bin/php
<?php

require_once '../lib/component.php';
require_once '../lib/php_serial.class.php';

class sharpLCD extends component {
    protected $componentclasses = array('video.switch');
    protected $settings = array();
    protected $commands = array(
        'power' => 'Controls the power',
    );
    protected $que = array();

    protected $sources = array(
        'digital' => 'IDTV',
        'analog' => 'ITVD',
        'av1' => 'IAVD1',
        'av2' => 'IAVD2',
        'av3' => 'IAVD3',
        'av4' => 'IAVD4',
        'pc' => 'IAVD9',
        'hdmi1' => 'IAVD5',
        'hdmi2' => 'IAVD6',
        'hdmi3' => 'IAVD7',
        'hdmi4' => 'IAVD8',
        'ext1(y/c)' => 'INP10',
        'ext1(cvbs)' => 'INP11',
        'ext1(rgb)' => 'INP12',
        'ext2(y/c)' => 'INP20',
        'ext2(cvbs)' => 'INP21',
    );

    function startup() {
        $this->s = new phpSerial();
        $this->s->deviceSet( "/dev/ttyUSB0" );
	$this->s->conf( "9600 -parenb cs8 -cstopb clocal -crtscts -ixon -ixoff -echo raw" );
        $this->s->deviceOpen();
	
	$this->send("A");
    }

    function event() {
        $this->runQue();
    }

    function power($pkt) {
        if ( !isset($pkt['power']) )
            $pkt['power'] = (!$this->state['power'])+0;

        if ( $pkt['power'] ) {
            $this->send('POWR1',$pkt);
        } else {
            $this->send('POWR0',$pkt);
        }
    }

    function volume($pkt) {
        if ( !isset($pkt['volume']) )
            return $this->nak($pkt,'volume parameter is not defined');

        $this->send('VOLM'.$pkt['volume'],$pkt);
    }

    function source($pkt){
        if ( !isset($pkt['source']) ) 
            return $this->nak($pkt,'source parameter is not defined');

	if ( !isset($this->sources[$pkt['source']]) )
            return $this->nak($pkt,'selected source do not exist, available: '.implode(array_keys($this->sources)));

        $this->send($this->sources[$pkt['source']],$pkt);
    }

    function channel($pkt){
        if ( !isset($pkt['channel']) )
            return $this->nak($pkt,'channel parameter is not defined');

        // Require correct format, ex A12 (Analog 12), D15 (Digital 15) or R2 (Radio 2)
        if ( is_numeric(substr($pkt['channel'],0,1)) || !is_numeric(substr($pkt['channel'],1)) )
            return false;

        switch( strtolower(substr($pkt['channel'],0,1)) ) {
            case 'd': // DTV
                $source = 'IDTV';
                break;
            case 'a': // ATV
                $source = 'ITVD';
                break;
        }

        $pkt['channel'] = substr($pkt['channel'],1);

        $this->send($source,$pkt);
        $this->send('DTVD'.$pkt['channel'],$pkt);
    }

    function intercom_event($in) {
        if ( $in == 'CHECK' ) {
            return $this->send('POWR?'); // Check power
        }

        $pkt = array_shift($this->que);
        $cmd = strtoupper(substr($pkt['cmd'],0,4));

        if ( substr($in,-3) == 'ERR' ) {
            note(warning,'Got NoGod response for ('.$pkt['cmd'].'): '.$in);

            if ( $pkt['pkt'] )
                $this->nak($pkt['pkt']);
	} else {
            note(notice,'Got OK response for ('.$pkt['cmd'].'): '.$in);

            switch($cmd) {
                case 'POWR': // power
		    $power = !!$in;
		

		    if ( $power ) {
                    	$this->setState('power',$power);
                        $this->send('IAVD?'); // Check input A
                        $this->send('VOLM?'); // Check volume
                        $this->send('INP??'); // Check input B
                        $this->send('MUTE?'); // Check mute
                        $this->send('DTVD????'); // Check channel
		    } else {
                    	$this->setState(array(
				'power' => false,
				'volume' => 0,
				'source' => '',
				'mute' => false,
				'channel' => '',
			));
		    }

                    break;
                case 'MUTE': // mute
                    $this->setState('mute',!!($in-1));
                    break;
                case 'VOLM': // volume
                    $this->setState('volume',$in);
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
                case 'DTVD': // channel
                    $this->setState('channel',$in);
                    break;
            }

            if ( $pkt['pkt'] )
                $this->ack($pkt['pkt']);
        }

        $this->runQue();
    }

    function _child() {
        if ( !isset($this->buff) )
            $this->buff = '';

        if ( !$this->s->_ckOpened() )
            die();

        $this->buff .= $this->s->readPort();

        while ( $pos = strpos($this->buff,"\r")){
            $cmd = substr($this->buff,0,$pos);
            $this->buff = substr($this->buff,$pos+1);

            $this->next = time() + 2;
            $this->intercom($cmd);
        }

        if ( !isset($this->next) || $this->next < time() ) {
            $this->next = time() + 2;
            $this->intercom('CHECK');
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
            $this->setState('power',false);
            note(warning,"Unanswerd message (".$next['cmd']."), throwing it away");
            if( $next['pkt'] )
                $this->nak($next['pkt']);

            array_shift($this->que);
            $next = reset($this->que);
        }

        if ( !$next )
            return;

        if( ($next && !$next['sent']) ) {
            $text = str_pad(trim($next['cmd']),8,' ',STR_PAD_RIGHT);

            note(notice,'Send: "'.$text.'"');

            $this->s->sendMessage($text."\r\n");

            $this->que[key($this->que)]['sent'] = true;
            $this->que[key($this->que)]['timestamp'] = time() + 2;
        }
    }/*}}}*/
}


$hostname = exec('hostname');
if ( !$hostname ) {
	note(critical,'Failed to get hostname (exec hostname)');
	die();
}

$b = new sharpLCD();
$b->start($hostname.'_sharpLCD','_child');

?>

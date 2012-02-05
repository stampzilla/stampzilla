#!/usr/bin/php
<?php

require_once '../lib/component.php';
require_once '../lib/php_serial.class.php';


class yamaha extends component {
    protected $componentclasses = array('audio.switch, video.switch, audio.controller');
    protected $settings = array();
    protected $commands = array(
    );

    function startup() {
        $this->s = new phpSerial();
        $this->s->deviceSet( "/dev/ttyUSB0" );
        $this->s->conf( "9600 -parenb cs8 -cstopb -clocal crtscts -ixon -ixoff -echo raw" );
        $this->s->deviceOpen();
    }

    public $parameters = array(
        'MainVolume' => 0,
        'MainPower' => 0,
        'MainSource' => '',
        'Zone2Volume' => 0,
        'Zone2Power' => 0,
        'Zone2Source' => '',
    );
    private $proj = false;

    function send( $cmd ) {
        $this->s->sendMessage("\x03\x03\x02$cmd\x03");
        $this->s->sendMessage("\x03\x03\x02$cmd\x03");
        logger::text($cmd);
        return true;
    }

    function startCommunication() {
        $this->s->sendMessage( "\x03\x03\x17000\x18" );
        logger::text('Sending start string');
    }

    /*function event( $pkt ) {
        if ( $this->peer == $pkt['to'] && !in_array($pkt['cmd'],array('nak','ack','ping')) ) {
            
            list($cmd,$val) = explode('|',$pkt['cmd'],2);
            switch($cmd) {
                case 'start':
                    return $this->startCommunication();
                case 'MainPower':
                    if ( $val ) {
                        return $this->send( '07E7E' );
                    } else
                        return $this->send( '07E7F' );

                    return false;
                case 'MainSource':
                    if ( !$this->parameters['MainPower'] )
                        $this->send( '07E7E' );

                    if ( strtoupper($this->parameters['MainSource']) == strtoupper($val) )
                        return true;

                    switch( strtoupper($val) ) {
                        case 'PHONO':
                            return $this->send( '07A14' );
                        case 'CD':
                            return $this->send( '07A15' );
                        case 'TUNER':
                            return $this->send( '07A16' );
                        case 'CD-R':
                            return $this->send( '07A19' );
                        case 'MD/TAPE':
                            return $this->send( '07A18' );
                        case 'DVD':
                            return $this->send( '07AC1' );
                        case 'DTV':
                            return $this->send( '07A54' );
                        case 'CBL/SAT':
                            return $this->send( '07AC0' );
                        case 'VCR1':
                            return $this->send( '07A0F' );
                        case 'DVR/VCR2':
                            return $this->send( '07A13' );
                        case 'V-AUX':
                            return $this->send( '07A55' );
                        case 'XM':
                            return $this->send( '07AB4' );
                    }
                    return false;

                case 'MainVolume':
                    $this->send('230'.dechex((1-($val/(-80-16.5)))*(0xE8-27)));
                    return true;
                case 'Zone2Power':
                    if ( $val ) {
                        return $this->send( '07EBA' );
                    } else
                        return $this->send( '07EBB' );

                    return false;
                case 'Zone2Source':
                    if ( !$this->state['Zone2']['Power'] )
                        $this->send( '07EBA' );

                    if ( strtoupper($this->state['Zone2']['Source']) == strtoupper($val) )
                        return true;

                    switch( strtoupper($val) ) {
                        case 'PHONO':
                            return $this->send( '07AD0' );
                        case 'CD':
                            return $this->send( '07AD1' );
                        case 'TUNER':
                            return $this->send( '07AD2' );
                        case 'CD-R':
                            return $this->send( '07AD4' );
                        case 'MD/TAPE':
                            return $this->send( '07AD3' );
                        case 'DVD':
                            return $this->send( '07ACD' );
                        case 'DTV':
                            return $this->send( '07AD9' );
                        case 'CBL/SAT':
                            return $this->send( '07ACC' );
                        case 'VCR1':
                            return $this->send( '07AD6' );
                        case 'DVR/VCR2':
                            return $this->send( '07AD7' );
                        case 'V-AUX':
                            return $this->send( '07AD8' );
                        case 'XM':
                            return $this->send( '07AB8' );
                    }
                    return false;
                default:
                    return false;
            }
        }

        if ( $pkt['from'] == 'xbmc' && $pkt['cmd'] == 'sleep' ) {
            if( $this->parameters['MainSource'] == 'Vcr1' )
                return $this->send( '07E7F' );
        }
        if ( $pkt['from'] == 'ps3' ) {
            if (  ($pkt['cmd'] == 'sleep'||$pkt['cmd'] == 'Power|0') ) {
                logger::text('Off signal recived from PS3 '.$this->parameters['MainSource']);
                if( $this->parameters['MainSource'] == 'Dvr/vcr2' )
                    return $this->send( '07E7F' );
            } elseif ($pkt['cmd'] == 'Power|1') {
                logger::text('On signal recived from PS3');
                if( !$this->parameters['MainPower'] )
                    return $this->send( '07E7E' );
                if( $this->parameters['MainSource'] != 'Dvr/vcr2' )
                    return $this->send( '07A13' );
            } else
                logger::text('Unkown command from PS3');
        }

		if ( $pkt['from'] == '00:04:20:17:df:3e' && trim($pkt['cmd']) == 'power 1' ) {
				$this->send( '07EBA' );
					return $this->send( '07AD4' );
		}
		if ( $pkt['from'] == '00:04:20:17:df:3e' && trim($pkt['cmd']) == 'power 0' ) {
				return $this->send( '07EBB' );
		}
    }*/

    function intercom_event( $cmd ) {
        $sources = array(
            0 => 'Phono',
            1 => 'Cd',
            2 => 'Tuner',
            3 => 'Cd-r',
            4 => 'Md/tape',
            5 => 'Dvd',
            6 => 'Dtv',
            7 => 'Cbl/sat',
            8 => 'Sat',
            9 => 'Vcr1',
            'A' => 'Dvr/vcr2',
            'B' => 'Vcr3/Dvr',
            'C' => 'V-aux',
            'E' => 'Xm',
            'F' => 'None'
        );

        $cmd = ltrim($cmd,chr(2));

        if ( substr($cmd,0,6) == 'R0193H' ) {
            $cmd = substr($cmd,8);

            if ( substr($cmd,8,1) == 0 )
                $this->setState('Main.source','None');
            else
                $this->setState('Main.source',$sources[substr($cmd,9,1)]);

            return;
        }

        $typ = substr($cmd,0,1);
        $guard = substr($cmd,1,1);
        $cmd2 = substr($cmd,-4,2);
        $val = substr($cmd,-2,2);
        
        switch( $cmd2 ) {
            case 20: // Power
                $this->setState('Main.power',in_array($val,array('01','02','04','05'))?1:0);
                $this->setState('Zone2.power',in_array($val,array('01','03','04','06'))?1:0);
                break;
            case 21: // Source
                $val = substr($val,1);
                $this->setState('Main.source',$sources[$val]);
                break;
            case 24: // Zone2 Source
                $val = substr($val,1);
                $this->setState('Zone2.source',$sources[$val]);
                break;
            case 26: // Volume
                $val = hexdec($val);
                if ( $val < 39 )
                    return $this->setState('Main.volume',-80);
                if ( $val > 232 )
                    return $this->setState('Main.volume',16.5);

                $this->setState('Main.volume',(-80-16.5)/(39-232)*$val-99.5 );
                break;
            case 27: // Zone2 Volume
                $val = hexdec($val);
                if ( $val < 39 )
                    return $this->setState('Zone2.volume',-80);
                if ( $val > 232 )
                    return $this->setState('Zone2.volume',16.5);

                $this->setState('Zone2.volume',(-80-16.5)/(39-232)*$val-99.5 );
                break;
        }
    }

    function check() {
        if ( !isset($this->buffert) )
            $this->buffert = '';

        if ( !$this->s->_ckOpened() )
            die();

        $this->buffert .= $this->s->readPort();
        
        if ( $end = strpos($this->buffert,chr(3)) ) {
            $cmd = substr($this->buffert,1,$end-1);
            $this->buffert = substr($this->buffert,$end+1);

            $this->intercom($cmd);
        }
        usleep(1000);
    }

    function pr( $str ) {
        $ret = '';
        $p=0;
        while( $p < strlen($str) ) {
            $c = substr($str,$p,1);
            if ( ord($c) < 32 || ord($c) > 126 )
                $ret .= '['.ord($c).']';
            else
                $ret .= $c;

            $p++;
        }
        return $ret;
    }

}

$b = new yamaha();
$b->start('yamaha','check');

?>

#!/usr/bin/php
<?php

require_once "../lib/component.php";

class rgb extends component {
    protected $componentclasses = array('controller');
    protected $settings = array();
    protected $commands = array(
        'set' => 'Turns on leds.',
        'reset' => 'Turns off leds.',
        'random' => 'Selects a random color',
    );


    function startup(){
        exec("stty -F /dev/arduino 9600 raw");
        if ( !$this->t = fopen('/dev/arduino','r+b') )
            die(" - Failed to open\n");

		$this->set("FFF");
    }

    function set($pkt) {
        if ( !is_array($pkt) ) 
            $pkt = array('color'=>$pkt);
        elseif ( !isset($pkt['color']) )
            $pkt['color'] = 'FFF';

        if ( strlen($pkt['color']) == 6 ) {
            $r = hexdec(substr($pkt['color'],0,2));
            $g = hexdec(substr($pkt['color'],2,2));
            $b = hexdec(substr($pkt['color'],4,2));
        } else if ( strlen($pkt['color']) == 3 ) {
            $r = min(hexdec(substr($pkt['color'],0,1))*16,255);
            $g = min(hexdec(substr($pkt['color'],1,1))*16,255);
            $b = min(hexdec(substr($pkt['color'],2,1))*16,255);
        }

        if ( isset($r) ) {
			$this->setState('1',pack('H*',chr($r).chr($g).chr($b)) );
            note(debug,"Sending color $r,$g,$b");
            fwrite($this->t,chr($r).chr($g).chr($b));
            return true;
        }
    }

    function reset($pkt) {
        return $this->set('000');
    }

    function random() {
        $full = rand(0,2);
        $sec = rand(0,1);
        $color = array();
        $first = true;
        for($i=0;$i<3;$i++) {
            if( $i==$full ) {
                $color[$i] = 255;
            } else {
                if ( $sec == $first ) {
                    $color[$i] = rand(0,255);
                } else {
                    $color[$i] = intval(rand(0,255)/2);
                }
                $first = false;
            }
        }
        foreach($color as $key => $line)
            $color[$key] = str_pad(dechex($line),2,'0',STR_PAD_LEFT);

        return $this->set(implode($color));
    }
}

$t = new rgb();
$t->start('rgb-arduino');


?>

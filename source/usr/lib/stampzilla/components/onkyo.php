
<?php

require_once "../lib/component.php";

class onkyo extends component {
    protected $componentclasses = array('audio.switch, video.switch, audio.controller');
    protected $settings = array();
    protected $commands = array(
        'on' => 'Turn on device.',
        'off' => 'Turns off device.',
    );
    private $last = null;
    function startup() {/*{{{*/
        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_connect($this->socket,'localhost',3001);


        $this->send('!1PWRQSTN');
        $this->send('!1MVLQSTN');
        $this->send('!1SLIQSTN');

    }/*}}}*/
    function send( $text ) {/*{{{*/
        note(notice,'Send: '.$text);
        $text = $text."\r";
        return socket_write($this->socket, $text,strlen($text));
    }/*}}}*/

//FUNCTIONS
    function on($pkt){/*{{{*/
        $this->last = $pkt;
        $this->send('!1PWR01');
    }/*}}}*/
    function off($pkt){/*{{{*/
        $this->last = $pkt;
        $this->send('!1PWR00');
    }/*}}}*/
    function source($pkt){/*{{{*/
        $this->last = $pkt;
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
        return $this->send('!1SLI'.$s[$pkt['source']]);
    }/*}}}*/
    function volume($pkt){/*{{{*/
        $this->last = $pkt;
        $vol = ($pkt['volume'] < 16 ) ?  "0".strtoupper(dechex($pkt['volume'])) : strtoupper(dechex($pkt['volume']));
        return $this->send('!1MVL'.$vol);

        //to read vol
        //return hexdec($value);
    }/*}}}*/
    function radio($pkt){/*{{{*/
        $this->last = $pkt;
        return $this->send('!1TUN10580');

    }/*}}}*/
    function sleep($pkt){/*{{{*/
        $this->last = $pkt;
        return $this->send('!1SLP00');
    }/*}}}*/
    function mute($pkt){/*{{{*/
        $this->last = $pkt;
        return $this->send('!1AMT01');
    }/*}}}*/
    function unmute($pkt){/*{{{*/
        $this->last = $pkt;
        return $this->send('!1AMT00');
    }/*}}}*/

    function intercom_event($cmd){/*{{{*/

        note(notice,'Read: '.$cmd);

        if($this->last){
            $this->last = null;
            $this->ack($this->last,$cmd);
        }

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


        $val = substr($cmd,5);
        $cmd = substr($cmd,0,5);
        switch ($cmd){
            case '!1SLI':
                $this->setState('source',$s[$val]);
                break;
            case '!1PWR':
                $this->setState('power',$val+0);
                break;
            case '!1MVL':
                $this->setState('volume',hexdec($val));
                break;

        }

    }/*}}}*/
    function _child() {/*{{{*/
        $contents = '';
        if( false == ($bytes = socket_recv($this->socket,$buff, 2048,0) ) ){
            die();
        }
        $this->buff .= $buff;

        while ( $pos = strpos($this->buff,chr(26))){
            $cmd = substr($this->buff,0,$pos);
            $this->buff = substr($this->buff,$pos+1);
            //exec("php /var/www/bulan.lan/htdocs/modules/mobileRemote/incoming.php '".trim(addslashes($cmd))."' > /dev/null 2>&1 &");
            //send to event to check ack
            $this->intercom($cmd);
        }

    }/*}}}*/


}


$t = new onkyo();
$t->start('onkyo','_child');

?>

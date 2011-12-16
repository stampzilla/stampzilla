<?php

require_once "../lib/component.php";

class telldus extends component {
    protected $componentclasses = array('controller');
    protected $settings = array();
    protected $commands = array(
        'state' => 'Returns the devices available and there state.',
        'set' => 'Turns on device.',
        'reset' => 'Turns off device.',
        'dim' => 'Dims a device.',
        'learn' => 'Sends a special learn command to devices supporting this. This is normaly devices of selflearning type.'
    );

    private $dev = array();

    function startup() {
        $this->state();
        $this->send_state();
    }

    function exec($cmd){
        note(debug,'cmd: '.$cmd);
        exec($cmd,$res);
        return $res;
    }

    function state() {
        $ret = $this->exec("tdtool --list");

        unset($ret[0]);

        foreach($ret as $key => $line) {
            $line = explode("\t",$line);

            $status = '';
            if ( $line[2] == 'ON' )
                $status = 255;

            if ( $line[2] == 'OFF' )
                $status = 0;

            if ( substr($line[2],0,7) == 'DIMMED:' )
                $status = substr($line[2],7);

            $this->dev[] = array(
                'id' => $line[0],
                'name' => $line[1],
                'status' => $status,
            );
        }

        return $this->dev;
    }

    function set($pkg) {
        $res = $this->exec("tdtool --on ".$pkg['id']);
        $this->dev[$pkg['id']] = 255;
        $this->send_state();

        print_r($res);
        if ( strpos($res[0],'Success') )
            return $res;
        return false;
    }
    function reset($pkg) {
        $res = $this->exec("tdtool --off ".$pkg['id']);
        $this->dev[$pkg['id']] = 0;
        $this->send_state();

        if ( strpos($res[0],'Success') )
            return $res;
        return false;
    }
    function dim($pkg) {
        $value = round($pkg['value']*(255/100));
        $res = $this->exec("tdtool -v ".$value." -d ".$pkg['id']);
        $this->dev[$pkg['id']] = $value;
        $this->send_state();

        if ( strpos($res[0],'Success') )
            return $res;
        return false;
    }
    function learn($pkg) {
        return $this->exec("tdtool --learn ".$pkg['id']);
    }

    function send_state() {
        $this->broadcast(array(
            'state' => $this->dev
        ));
    }
}


$t = new telldus();
$t->start('telldus');


?>

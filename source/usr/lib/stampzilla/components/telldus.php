#!/usr/bin/php
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
    private $cmdhistory = array();

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

            if(!isset($line[2]) || isset($line[3]))
                continue;

            $status = '';
            if ( $line[2] == 'ON' )
                $status = 255;

            if ( $line[2] == 'OFF' )
                $status = 0;

            if ( substr($line[2],0,7) == 'DIMMED:' )
                $status = substr($line[2],7);

            $this->dev[$line[0]] = array(
                'id' => $line[0],
                'name' => $line[1],
                'status' => $status,
            );
        }

        return $this->dev;
    }

    function toggle($pkt) {
        if ( $this->state[$pkt['id']]['status'] > 0 ) {
            return $this->reset($pkt);
        }
        return $this->set($pkt);
    }

    function set($pkg) {
        $res = $this->exec("tdtool --on ".$pkg['id']);
        $this->dev[$pkg['id']]['status'] = 255;
        $this->send_state();

        print_r($res);
        if ( strpos($res[0],'Success') )
            return $res;

        return false;
    }
    function reset($pkg) {
        $res = $this->exec("tdtool --off ".$pkg['id']);
        $this->dev[$pkg['id']]['status'] = 0;
        $this->send_state();

        print_r($res);
        if ( strpos($res[0],'Success') )
            return $res;
        return false;
    }
    function dim($pkg) {
        $value = round($pkg['value']*(255/100));
        $res = $this->exec("tdtool -v ".$value." -d ".$pkg['id']);
        $this->dev[$pkg['id']]['status'] = $value;
        $this->send_state();

        if ( strpos($res[0],'Success') )
            return $res;
        return false;
    }
    function learn($pkg) {
        return $this->exec("tdtool --learn ".$pkg['id']);
    }

    function send_state() {
        $this->setState($this->dev);
    }
    function intercom_event($cmd){
        note(notice,'R: '.$cmd);
        $temp = explode(';',$cmd);
        foreach($temp AS $key=>$val){
            $t = explode(':',$val);
            if(isset($t[1]))
                $temp[$t[0]] = $t[1];
            unset($temp[$key]);
        }
        if(isset($temp['model'])){
            switch($temp['model']) {
                case 'temperaturehumidity':
                    file_put_contents('/tmp/temperature', $temp['temp']);
                    $this->setState('temp',$temp['temp']);
                    break;
                case 'selflearning':
                    note(notice,'selflearning');
                    if(isset($temp['house']) && isset($temp['group']) &&isset($temp['unit'])&&isset($temp['method'])  ) {
                        if($temp['method'] == 'turnon')
                            $m = 'on';
                        else
                            $m = 'off';
                        $this->setState($temp['house'].'_'.$temp['group'].'_'.$temp['unit'].'_'.$m.'.button',true);
                        $this->setState($temp['house'].'_'.$temp['group'].'_'.$temp['unit'].'_'.$m.'.button',false);
                    }
                    
                    break;
            }
        }
        note(notice,print_r($temp,1));
    }
    function _child() {
        $this->socket = stream_socket_client('unix:///tmp/TelldusEvents');
        while(1){
            if( false == ($temporg = stream_socket_recvfrom($this->socket,1024))){
                $this->intercom(array('error'=>'Died in child','status'=>'died'));
                echo "died in child";
                die();
            }

            print_r($this->cmdhistory);

            print_r($temporg);
            if(strlen($temporg) > 125)
                continue;
            foreach($this->cmdhistory AS $key=>$line){
                $now = microtime(true);
                if( ($now-$line['time'] ) < 1 ){
                    if($line['cmd'] == $temporg ){
                        note(notice,'SKIPPING: '.$temporg);
                        continue 2;
                    }
                }
                else
                    array_shift($this->cmdhistory);
            }

            $this->cmdhistory[] = array('cmd'=>$temporg,'time'=>microtime(true));

            $this->intercom(trim($temporg));
        }

    }
}


$t = new telldus();
$t->start('telldus','_child');


?>

<?php
require_once "../lib/component.php";

class logic extends component {
    
    protected $componentclasses = array('logic');
    protected $settings = array();
    protected $commands = array(
        'state' => 'Returns the devices available and there state.',
        'set' => 'Turns on device.',
        'reset' => 'Turns off device.',
        'dim' => 'Dims a device.',
        'learn' => 'Sends a special learn command to devices supporting this. This is normaly devices of selflearning type.'
    );


    function startup() {
        if(!is_dir('/var/spool/stampzilla/')){
            mkdir('/var/spool/stampzilla/');
        }
    }


    function schedule(){

        while(true){
            sleep(1);
            note(debug,"Checking time");
        }
    }

}

$r = new logic();
$r->start('logic','schedule');

?>

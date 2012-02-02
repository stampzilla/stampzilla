<?php

require_once "../lib/component.php";
require_once '../lib/ads.php';


class beckhoff extends component {

    protected $componentclasses = array('controller');
    protected $settings = array();
    protected $commands = array(
        'set' => 'Turns on leds.',
        'reset' => 'Turns off leds.',
        'random' => 'Selects a random color',
    );

    function startup() {
        $this->child = new ads( "172.16.21.4","172.16.21.4.1.1",801,"172.16.21.2.1.1",803 );
        $this->child->tpy('/beckhoff/food.tpy');

        if ( !is_dir("/var/spool/stampzilla/beckhoff") )
            if ( !mkdir("/var/spool/stampzilla/beckhoff") ) {
                trigger_error("Failed to create dir..",E_USER_ERROR);die();
            }
    }

	function set($pkt) {
		$this->write('.Interface.'.$pkt['tag'],$pkt['value'],$pkt);
	}

    function write($tag,$value,$ack=null){
        file_put_contents("/var/spool/stampzilla/beckhoff/".uniqid(),serialize(array($tag,$value,$ack)));
    }

    function intercom_event($data) {
        $this->setState($data);
    }

    function _child() {
        $this->data = $this->child->read('.Interface');

        if ( !isset($this->prev) || $this->data != $this->prev ) {
            $this->intercom($this->data);
        }

        $this->prev = $this->data;

        $dir = scandir("/var/spool/stampzilla/beckhoff");
        foreach($dir as $key => $line) {
            if ( substr($line,0,1) == '.' ) 
                continue;

            $data = unserialize(file_get_contents("/var/spool/stampzilla/beckhoff/$line"));
            unlink("/var/spool/stampzilla/beckhoff/$line");
            note(notice,'Writing tag '.$data[0].' => '.$data[1]);
            $res = $this->child->write($data[0],$data[1]);
            if ( $data[2] ) {
                if ( $res ) {
                    $this->ack($data[2],$data[1]);
                } else {
                    $this->nak($data[2]);
                }
            }
        }
    }
}

$b = new beckhoff();
$b->start('beckhoff','_child');

?>

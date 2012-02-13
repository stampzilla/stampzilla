#!/usr/bin/php
<?php

require_once "../lib/component.php";
require_once '../lib/ads.php';


class beckhoff extends component {

    protected $componentclasses = array('controller');
    protected $settings = array(
        'tpy'=>array(
            'type'=>'text',
            'name' => 'TPY file',
            'required' => true
        ),
        'ip'=>array(
            'type'=>'text',
            'name' => 'PLC ip',
            'required' => true
        ),
        'source'=>array(
            'type'=>'text',
            'name' => 'Source net id',
            'required' => true
        )
	);
    protected $commands = array(
        'set' => 'Turns on leds.',
        'reset' => 'Turns off leds.',
        'random' => 'Selects a random color',
    );

    function startup() {
		$ip = $this->setting('ip');
		$host = $this->setting('source');
		$tpy = $this->setting('tpy');

		if ( $ip && $host && $tpy ) {
			$this->child = new ads( $ip,$ip.".1.1",801,$host,803 );
			$this->child->tpy($tpy);
		}

        if ( !is_dir("/var/spool/stampzilla/beckhoff") )
            if ( !mkdir("/var/spool/stampzilla/beckhoff") ) {
                trigger_error("Failed to create dir..",E_USER_ERROR);die();
            }
    }

	function toggle($pkt) {
		$this->write('.Interface.'.$pkt['tag'],!$this->readState($pkt['tag']),$pkt);
	}

	function set($pkt) {
		if ( !isset($pkt['value']) )
			$pkt['value'] = true;

		$this->write('.Interface.'.$pkt['tag'],$pkt['value'],$pkt);
	}

	function reset($pkt) {
		$this->write('.Interface.'.$pkt['tag'],0,$pkt);
	}

    function write($tag,$value,$ack=null){
        file_put_contents("/var/spool/stampzilla/beckhoff/".uniqid(),serialize(array($tag,$value,$ack)));
    }

    function intercom_event($data) {
        $this->setState($data);
    }

    function _child() {
		if ( !isset($this->child) ) {
			sleep(1000);
			return;
		}
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

		usleep(100000);
    }
}

$b = new beckhoff();
$b->start('beckhoff','_child');

?>

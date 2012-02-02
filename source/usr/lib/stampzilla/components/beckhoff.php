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
		//$this->plc->write('MAIN.nTargetMode',4);
		//$this->plc->write('MAIN.nDimLevel',10000);

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



/*    function event( $pkt ) {
        if ( $this->peer == $pkt['to'] ) {

            if ( !is_array($pkt['cmd']) ) {
                $cmd = array($pkt);
            } else {
                $cmd = $pkt['cmd'];
            }
            $ret = array();

            if ( isset($cmd['if']) )
                foreach($cmd['if'] as $key => $line) {
                    if ( (bool)($this->values[$key]) != $line )
                        return false;
                }

            unset($cmd['if']);
            foreach( $cmd as $pkt ) {
                switch ( $pkt['cmd'] ) {
                    case 'meter':
                        //$this->sendCmd(10); // Request meter 0
                        return $this->meter;
                    case 'list':
                        $ret['meter'] = $this->meter;
                        break;
                }

                $send = false;
                foreach( $this->values as $unit => $val )
                    switch ( $pkt['cmd'] ) {
                        case 'set':
                            if ( $unit = $pkt['unit'] ) {
				if ( isset($pkt['value']) ) {
				    if ( $unit == 'Yoda|Roof' )
					    $this->plc->write('MAIN.nYodaDimLevel',$pkt['value']);
				    else
					    $this->plc->write('MAIN.nDimLevel',$pkt['value']);
				}
				switch($unit) {
				    case 'Bumblebeeo|All':
					$this->plc->write('MAIN.nTargetMode',1);
					break;
				    case 'Bumblebeeo|Ring':
					$this->plc->write('MAIN.nTargetMode',2);
					break;
				    case 'Bumblebeeo|Center':
					$this->plc->write('MAIN.nTargetMode',3);
					break;
				    case 'Bumblebeeo|Screen':
					$this->plc->write('MAIN.nTargetMode',4);
					break;
				    case 'Bumblebeeo|Sofa':
					$this->plc->write('MAIN.nTargetMode',5);
					break;
				    case 'Bumblebeeo|Led':
					$this->plc->write('MAIN.nTargetMode',6);
					break;
				    case 'Projector':
					$this->plc->write('MAIN.bProjector',true);
					break;
				    case 'Yoda|Roof':
					$this->plc->write('MAIN.bYoda',true);
					break;
				    case 'Bedroom|Roof':
					$this->plc->write('MAIN.bBedroomRoof',true);
					break;
				    case 'Bedroom|Alarm':
					$this->plc->write('MAIN.bBedroomAlarm',1);
					break;
				}
			    }
                            break;
                        case 'reset':
                            if ( $unit = $pkt['unit'] ) {
				switch($unit) {
				    case 'Bumblebeeo|All':
				    case 'Bumblebeeo|Ring':
				    case 'Bumblebeeo|Center':
				    case 'Bumblebeeo|Screen':
				    case 'Bumblebeeo|Sofa':
					$this->plc->write('MAIN.nTargetMode',0);
					break;
				    case 'Yoda|Roof':
					$this->plc->write('MAIN.bYoda',false);
					break;
				    case 'Projector':
					$this->plc->write('MAIN.bProjector',false);
					break;
				    case 'Bedroom|Roof':
					$this->plc->write('MAIN.bBedroomRoof',false);
					break;
				    case 'Bedroom|Alarm':
					$this->plc->write('MAIN.bBedroomAlarm',0);
					break;
				}
			    }
                            break;
                        case 'toggle':
                            if ( $unit = $pkt['unit'] ) {
				switch($unit) {
				    case 'Bumblebeeo|All':
					$this->plc->write('MAIN.nTargetMode',1);
					break;
				    case 'Bumblebeeo|Ring':
					$this->plc->write('MAIN.nTargetMode',2);
					break;
				    case 'Bumblebeeo|Center':
					$this->plc->write('MAIN.nTargetMode',3);
					break;
				    case 'Bumblebeeo|Screen':
					$this->plc->write('MAIN.nTargetMode',4);
					break;
				    case 'Bumblebeeo|Sofa':
					$this->plc->write('MAIN.nTargetMode',5);
					break;
				}
				$ret[$unit] = true;
			    }
                            return $ret;
                        case 'status':
                            break;
                        case 'list':
                            if ( $this->values[$unit] == 0 ) 
                                $ret[$unit] = 'false';
                            else
                                $ret[$unit] = 'true';
                            break;
                        default:
                            return !trigger_error("Unknown command '".$pkt['cmd']."'!");
                    }
            }
            return $ret;
        }
    }*/
}

$b = new beckhoff();
$b->start('beckhoff','_child');

?>

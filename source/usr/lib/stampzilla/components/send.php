#!/usr/bin/php
<?php

$start = microtime(true);

require_once '../lib/component.php';
require_once "../lib/functions.php";

$args = arguments($_SERVER['argv']);
parse_str($args['arguments'][0],$_GET);

if ( in_array('o',$args['flags']) ) {
	ob_start();
}


class sender extends component{
	protected $componentclasses = array('commander');
	public $answerd = false;
	public $data = array(
		'success' => false,
		'timeout' => true
	);

    function event($pkt) {
        global $start,$data;

        if ( !isset($this->msg) )
            return false;

        //if( $pkt['to'] = $this->peer && $pkt['cmd'] == 'answer' ) { //see line 62
        //    print_r($pkt['data']);
        //}

        if( $pkt['to'] = $this->peer && $pkt['msg'] = $this->msg && isset($pkt['cmd']) ) {
            if ( $pkt['cmd'] == 'ack' ) {
				$this->answerd = true;
				$this->data['success'] = true;
				$this->data['timeout'] = false;
                note(debug,'Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
                note(debug,'Success!');

                if(isset($pkt['answer'])){
                    $data['answer'] = $pkt['answer'];
                }

				echo json_format(json_encode($pkt['ret']))."\n";
				posix_kill($this->child_pid, SIGTERM);
				die();
            } elseif( $pkt['to'] = $this->peer && $pkt['cmd'] == 'nak' && $pkt['msg'] = $this->msg ) {
				$this->data['success'] = false;
				$this->data['timeout'] = false;
                note(debug,'Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
                note(error,'Failed to execute command!');

				posix_kill($this->child_pid, SIGTERM);
				die();
            }
        }
    }

    function startup() {
		if ( !$_GET ) {
        	note(error,'No send argument defined!');
			die();
		}

    	$this->msg( $_GET );
    }

    function msg( $msg ) {
        if ( !isset($msg['from']) )
            $msg['from'] = $this->peer;

        // Fix for cli objects like {'Roof':32000,'RoofMode':4,'Projector':false}
        foreach($msg as $key => $line) {
            if ( ($obj = json_decode(str_replace("'",'"',$line))) !== null )
                $msg[$key] = $obj;
        }

        $this->msg = sha1(json_encode($msg));
        $this->broadcast($msg);
    }

    function checkTimeout() {
        global $start,$c;
        sleep(2);
		$c->broadcast(array(
			'cmd' => 'nak',
			'pkt' => $_GET
		));
        note(debug,'Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
        note(error,'Timeout reached ('.round((microtime(true)-$start)*1000,1).'ms)!');
        posix_kill($this->parent_pid, SIGTERM);
        die();
    }

	function __destruct() {
		if ( !$this->answerd ) {
			$this->broadcast(array(
				'cmd' => 'nak',
				'pkt' => $_GET
			));
		}
	
		global $args;

		if ( in_array('o',$args['flags']) ) {
			$this->data = array_merge($this->data,array(
    	        'log' => ob_get_contents()
        	));
	        ob_end_clean();
    	    echo json_encode($this->data);
		}
	}
}

$c = new sender();

$c->start('','checkTimeout');
?>

<?php
$fromweb=0;
if( (isset($_SERVER['argv'][2]) && $_SERVER['argv'][2]=='fromweb') || isset($_SERVER['QUERY_STRING']) ){
    $fromweb=1;
    set_time_limit(2);
}
if ( isset($_SERVER['argv'][1]) ) {
    parse_str($_SERVER['argv'][1],$temp1);
    if($temp1)
        $_GET=$temp1;
    else
        trigger_error('Syntax error on message: "'.$_SERVER['argv'][1].'"',E_USER_ERROR);
}
    
if ( $fromweb ) {
    ob_start();
    define('quiet',true);
    define('makelog',true);
    define('nolog',true);
}

$start = microtime(true);
require_once '../lib/udp.php';
require_once '../lib/actor.php';
require_once '../lib/errorhandler.php';

$data = array(
    'success' => false,
    'timeout' => false
);

class sender extends actor{
    function json( ) {
        global $log,$data;

        $data = array_merge($data,array(
            'log' => $log
        ));
        ob_end_clean();
        echo json_encode($data);
        die();
    }

    function event($pkt) {
        global $start,$data;

        if ( !isset($this->msg) )
            return false;

        //if( $pkt['to'] = $this->peer && $pkt['cmd'] == 'answer' ) { //see line 62
        //    print_r($pkt['data']);
        //}

        if( $pkt['to'] = $this->peer && $pkt['msg'] = $this->msg && isset($pkt['cmd']) ) {
            if ( $pkt['cmd'] == 'ack' ) {
                note(debug,'Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
                note(debug,'Success!');

                if(isset($pkt['answer'])){
                    $data['answer'] = $pkt['answer'];
                }

                if ( $this->fromweb ) {
                    global $data;
                    $data['success'] = true;
                    if ( isset($pkt['ret']) )
                        $data['ret'] = $pkt['ret'];
                    die();
                } else {
                    posix_kill($this->pidChild, SIGTERM);
                    die();
                }
            } elseif( $pkt['to'] = $this->peer && $pkt['cmd'] == 'nak' && $pkt['msg'] = $this->msg ) {
                note(debug,'Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
                note(error,'Failed to execute command!');

                if ( $this->fromweb ) {
                    die();
                } else {
                    posix_kill($this->pidChild, SIGTERM);
                    die();
                }
            } 
            elseif($pkt['cmd'] == 'timeout'){
                $data['success'] = false;
                $data['timeout'] = true;
                note('Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
                note(error,'Timeout recieved!');
                die();
            }
        }
    }

    function send() {
            $this->msg( $_GET );
    }

    function msg( $msg ) {
        if ( !isset($msg['from']) )
            $msg['from'] = $this->peer;

        $this->msg = sha1(json_encode($msg));
        $this->broadcast($msg);
    }

    function checkTimeout() {
        global $start;
        sleep(2);
        note(debug,'Total time: '.round((microtime(true)-$start)*1000,1).'ms' );
        note(error,'Timeout reached!');
        posix_kill($this->pidParent, SIGTERM);
        die();
    }
}

$c = new sender();
$c->peer = md5(mt_rand(0, 32) . time());
$c->fromweb = $fromweb;
$c->send();

if ( !$fromweb ) {
    $c->start('','checkTimeout');
} else {
    function shutdown() {
        global $c,$start,$data;
        $data['time'] = round((microtime(true)-$start)*1000,1);
        $c->json();
    }

    register_shutdown_function('shutdown');
    $c->start('');
}
?>

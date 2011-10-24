<?php

require_once "../lib/errorhandler.php";
require_once "../lib/constants.php";
require_once "../lib/udp.php";
require_once "../lib/actor.php";


class xbmc extends actor {
	function __construct() {
        parent::__construct();
        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_connect($this->socket,'xbmc.lan',9090);

        $tmp =  $this->json('JSONRPC.Version');

        echo $tmp."\n";
        socket_write($this->socket,$tmp,strlen($tmp));

        $this->readSocket();


	}
    function getId(){
        return 1;
    }


    function json($cmd,$params=null){

        
        $data = array(
            'jsonrpc'=>"2.0",
            'method'=>$cmd,
            'params'=>$params,
            'id'=>$this->getId()
        );
        if($params==null)
            unset($data['params']);

        return json_encode($data);

    }

    function readSocket(){
        if( false == ($bytes = socket_recv($this->socket,$buff, 2048,0) ) ){
            //$this->intercom(array('error'=>socket_strerror(socket_last_error($this->socket)),'status'=>'died'));
            echo "died in child";
            die();
        }
        $this->buff .= $buff;
        while ( $pos = strpos($this->buff,"}\n")){
            $cmd = substr($this->buff,0,$pos+1);
            $this->buff = substr($this->buff,$pos+2);
            echo $cmd;
        }

    }


}

$xbmc = new xbmc();
$xbmc->start('xbmc-test','readSocket');

?>

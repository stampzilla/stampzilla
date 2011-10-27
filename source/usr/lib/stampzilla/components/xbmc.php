<?php
require_once "../lib/component.php";

class xbmc extends component {
    private $id = 0;
    private $commands = array(
        'play'=>'play'
        );
	function __construct() {
        parent::__construct();
        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_connect($this->socket,'xbmc.lan',9090);

	}
    function getId(){
        $this->id++;
        if($this->id > 10000)
            $this->id=1;
        return $this->id;
    }

    function event($pkt){
        return false;
        $tmp =  $this->json($pkt['cmd']);
        $this->send($tmp);
        return true;
    }

    function raw($pkt) {
        if(is_array($pkt['raw']))
            $this->send(json_encode($pkt['raw']));
        elseif(is_string($pkt['raw']))
            $this->send($pkt['raw']);
        return true;
    }

    function send($text){
        note(debug,$text);
        socket_write($this->socket,$text."\n\r",strlen($text));
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
            $this->buff = substr($this->buff,$pos+1);
            echo $cmd;
        }

    }


}

$xbmc = new xbmc();
$xbmc->start('xbmc-test','readSocket');

?>

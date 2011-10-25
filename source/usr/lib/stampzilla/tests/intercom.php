<?php

$sockets = array();
if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
    die(socket_strerror(socket_last_error()));
}
list($reader, $writer) = $sockets;

$signal = SIGALRM;

declare(ticks = 1);

$pid = pcntl_fork();
if ($pid == -1) {
    die('cannot fork');
} elseif ($pid) {
	echo "Start parent\n";
    socket_close($writer);

	function got_usr1($signal) {
    	pcntl_signal($signal, 'got_usr1');  // but not for SIGCHLD!
		echo "Parent GOT SIGALRM\n";
	}
	pcntl_signal($signal, 'got_usr1');

    while(true) {
		echo "Parent socket_read\n";
		if ( ($line = @socket_read($reader, 1024, PHP_NORMAL_READ) ) === false ) 
			if ( ($errorcode = socket_last_error()) == 104 ) {
				echo "Parent socket_read ERROR: $errorcode\n";
				echo "Parent EXIT\n";
				exit();
			} else
				echo "Parent socket_read ERROR: $errorcode\n";

		echo "Parent socket_read done\n";		

		if ( $line )
		    printf("Parent Pid %d just read this: `%s'\n", getmypid(), rtrim($line));
	}
    socket_close($reader);
    pcntl_waitpid($pid, $status);
} else {
	echo "Start child\n";
    socket_close($reader);

	echo "\nChild sends SIGALRM (1)\n";
	posix_kill(posix_getppid(), $signal);

	sleep(1);
	echo "\nChild sends SIGALRM (2)\n";
	posix_kill(posix_getppid(), $signal);

	sleep(1);
    $line = sprintf("Child Pid %d is sending this\n", getmypid());
	echo $line;
    if (!socket_write($writer, $line, strlen($line))) {
        socket_close($writer);
        die(socket_strerror(socket_last_error()));
    }
    socket_close($writer);  // this will happen anyway
	echo "Child EXIT\n";
    exit(0);
}

?>

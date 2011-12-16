<?php
require_once "../lib/component.php";

class logic extends component {
    
    protected $componentclasses = array('logic');
    protected $settings = array();
    protected $commands = array(
        'rooms' => 'Returns the rooms available',
    );

    function startup() {
        if(!is_dir('/var/spool/stampzilla/'))
            if ( !mkdir('/var/spool/stampzilla/') )
                note(critical,'Failed to create dir /var/spool/stampzilla/',true);

        if ( !is_file('/var/spool/stampzilla/rooms.yml') ) {
            $this->rooms = array('Default'=>array());
            if ( !$this->save('rooms') ) 
                note(critical,'Failed to create file /var/spool/stampzilla/rooms',true);
        }

        $this->load('rooms');
    }

    function save($file) {
        $string = Spyc::YAMLDump($this->$file);

        if ( $file == 'schedule' ) 
            file_put_contents('/var/spool/stampzilla/reload_schedule',1);

        return file_put_contents('/var/spool/stampzilla/'.$file.'.yml',$string);
    }

    function load($file) {
        $this->$file = spyc_load_file('/var/spool/stampzilla/'.$file.'.yml');
        return isset($this->$file);
    }

    function schedule($pkt) {
        // Require time and command
        if ( !isset($pkt['time']) || !isset($pkt['command']) ) 
            return false;

        $event = array(
            'time' => $pkt['time'],
            'command' => $pkt['command']
        );

        if ( isset($pkt['interval']) )
            $event['interval'] = $pkt['interval'];

        $this->load('schedule');
        $this->schedule[] = $event;
        return $this->save('schedule');
    }

    function child(){

        if ( isset($this->event) && $this->event > -1 ) {
            if ( $this->schedule[$this->event]['timestamp'] < time()+1 ) {
                note(notice,'Trigger event #'.$this->event);

                if ( isset($this->schedule[$this->event]['interval']) && $this->schedule[$this->event]['interval'] > 0) {
                    while($this->schedule[$this->event]['timestamp'] < time()+1 ) {
                        $this->schedule[$this->event]['timestamp'] += $this->schedule[$this->event]['interval'];
                    }
                }
                $this->event = null;
            }
        }

        if ( !isset($this->schedule) || is_file('/var/spool/stampzilla/reload_schedule') ) {
            note(debug, 'Reloading schedule');

            if ( !$this->load('schedule') )
                $this->schedule = array();

            if ( is_file('/var/spool/stampzilla/reload_schedule') )
                unlink('/var/spool/stampzilla/reload_schedule');

            $this->event = null;
        }

        if ( !isset($this->event) || $this->event === null ) {
            $this->event = -1;
            foreach($this->schedule as $key => $line) {
                if ( !isset($this->schedule[$key]['timestamp']) )
                    $this->schedule[$key]['timestamp'] = strtotime($line['time']);

                if ( isset($line['interval']) && $line['interval'] > 0) {
                    // Update the timestamp to the next
                    while($this->schedule[$key]['timestamp'] < time() ) {
                        $this->schedule[$key]['timestamp'] += $line ['interval'];
                    }
                } elseif($this->schedule[$key]['timestamp'] < time() ) {
                    // No repetition, then remove the event
                    note(debug,'Removed event #'.$key);
                    unset($this->schedule[$key]);
                }

                if ( isset($this->schedule[$key]) && ($this->event == -1 || $this->schedule[$key]['timestamp'] < $this->schedule[$this->event]['timestamp']) ) {
                    $this->event = $key;
                }
            }
            $this->save('schedule');
            unlink('/var/spool/stampzilla/reload_schedule');

            if ( $this->event > -1 )
                note(debug,'Next scheduled event is #'.$this->event.' timestamp: '.date('Y-m-d H:i:s',$this->schedule[$this->event]['timestamp']));
            else
                note(debug,'Did not find any new events');
        }

        if ( $this->event == -1 || $this->event === null || $this->schedule[$this->event]['timestamp'] > time() ) {
            $sleep = intval((1.499600-(microtime(true)-time()))*1000000);
            if ( $sleep < 0 ) 
                $sleep += 1000000;

            usleep($sleep);
        }
    }

}

$r = new logic();
$r->start('logic','child');

?>

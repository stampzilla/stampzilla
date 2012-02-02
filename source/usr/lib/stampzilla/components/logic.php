<?php
require_once "../lib/component.php";

class logic extends component {
    
    protected $componentclasses = array('logic');
    protected $settings = array();
    protected $commands = array(
        'rooms' => 'Returns the rooms available',
        'room' => 'Adds/updates a room definition',
        'deroom' => 'Remove a room',
        'schedule' => 'List and add schedule tasks',
        'unschedule' => 'Remove a scheduled task'
    );
    protected $sends = array();

    function startup() {
        if(!is_dir('/var/spool/stampzilla/'))
            if ( !mkdir('/var/spool/stampzilla/') )
                note(critical,'Failed to create dir /var/spool/stampzilla/',true);

        if ( !is_file('/var/spool/stampzilla/rooms.yml') ) {
            $this->rooms = array(
                uniqid() => array(
                    'name' => 'Default'
                )
            );

            if ( !$this->_save('rooms') ) 
                note(critical,'Failed to create file /var/spool/stampzilla/rooms',true);
        }

        $this->_load('rooms');
        $this->_load('rules');
        $this->_load('schedule');

        $this->broadcast(array(
            'type' => 'hello'
        ));
    }

    function event($pkt) {

        if ( isset($pkt['cmd']) && $pkt['cmd'] == 'greetings') {
            if ( $pkt['class'][0] == 'commander' ) {
                $this->sends[$pkt['from']] = 1;
            }
        }

        if ( isset($pkt['type']) && $pkt['type'] == 'state' && !isset($this->sends[$pkt['from']]) ) {
            $this->state[$pkt['from']] = $pkt['data'];
            return $this->stateUpdate();
        }

        if ( isset($pkt['cmd']) && $pkt['cmd'] == 'bye' ) {
            unset($this->sends[$pkt['from']]);
            unset($this->state[$pkt['from']]);
            return $this->stateUpdate();
        }

        if ( !isset($pkt['to']) )
            return;

        /*foreach( $this->rules as $key => $line ) {
            if ( $pkt['to'] == $line['trigger']['component'] ) {
                note(debug,'Testing rule '.$key );
                if ( $this->testEvent($pkt,$line) ){
                    note(notice,'Triggerd rule '.$key );
                    $this->triggerEvent($pkt,$line);

                    if ( $line['trigger']['component'] == 'logic' ) {
                        return true;
                    }

                    break;
                }
            }
        }*/
    }

    function stateUpdate() {
        $this->broadcast( array(
            'type' => 'state',
            'data' => $this->state
        ));

        foreach($this->rules as $uuid => $rule) {
            $satisfied = true;

            foreach($rule['conditions'] as $key => $line) {
                $val = $this->readState($line['state']);

                $state = true;

                switch($line['type']) {
                    case 'eq':
                        if ( $val != $line['value'] ) {
                            $state = false;
                        }
                        break;
                }

                if ( !$state )
                    $satisfied = false;

                $rule['conditions'][$key]['active'] = $satisfied;
            }

            if ( !isset($rule['active']) || $rule['active'] != $satisfied ) {
                if ( $satisfied && $rule['enter'] ) {
                    note(notice,'Running enter commands for rule '.$uuid.' ('.$rule['name'].')' );

                    foreach($rule['enter'] as $line2)
                        $this->broadcast($line2);
                } elseif ($rule['exit']) {
                    note(notice,'Running exit commands for rule '.$uuid.' ('.$rule['name'].')' );

                    foreach($rule['exit'] as $line2)
                        $this->broadcast($line2);
                }
            }

            $this->rules[$uuid]['active'] = $satisfied;
        }
    }


    function state($pkt) {
        return array(
            'rooms' => $this->rooms,
            'rules' => $this->rules,
            'schedule' => $this->schedule,
        );
    }

    function rooms($pkt) {
        return $this->rooms;
    }

    function room($pkt) {
        if( !isset($pkt['name']) )
            return false;

        $room = array(
            'name' => $pkt['name']
        );
        $id = uniqid();

        $this->rooms[$id] = $room;
        $this->_save('rooms');

        $this->broadcast(array(
            'type' => 'event',
            'event' => 'addRoom',
            'uuid' => $id,
            'data' => $room
        ));

        note(notice,"New room ".$pkt['name']." with UUID ".$id." was created");

        return $id;
    }

    function deroom( $pkt ) {
        if ( !isset($pkt['uuid']) )
            return false;

        note(debug,"Deroom uuid ".$pkt['uuid']);

        if ( isset($this->rooms[$pkt['uuid']]) ) {
            unset($this->rooms[$pkt['uuid']]);
            note(notice,"UUID ".$pkt['uuid']." was removed.");

            $this->broadcast(array(
                'type' => 'event',
                'event' => 'removeRoom',
                'uuid' => $pkt['uuid'],
            ));

            return $this->_save('rooms');
        }

        note(debug,"UUID ".$pkt['uuid']." was not found!");

        return false;
    }

    function update($pkt) {
        $pkt['value'] = str_replace('px','',$pkt['value']);

        if ( !isset($this->rooms[$pkt['room']][$pkt['element']][$pkt['uuid']][$pkt['field']]) ) 
            return $this->nak($pkt,array('msg' => 'Field "'.$pkt['field'].'" do not exists!','value'=>''));

        $this->rooms[$pkt['room']][$pkt['element']][$pkt['uuid']][$pkt['field']] = $pkt['value'];

        $this->broadcast(array(
            'type' => 'event',
            'event' => 'roomUpdate',
            'uuid' => $pkt['room'],
            'data' => $this->rooms[$pkt['room']]
        ));

        note(notice,"Value updated for room {$pkt['room']} > {$pkt['element']}({$pkt['uuid']}) {$pkt['field']} = {$pkt['value']}");
        $this->_save('rooms');

        return $this->ack($pkt,array('value'=>$pkt['value']));
    }

    function create($pkt) {
        switch($pkt['element']) {
            case 'buttons':
                $this->rooms[$pkt['room']]['buttons'][uniqid()] = array(
                    'title' => 'New button',
                    'position' => ($pkt['x']-50).','.($pkt['y']-50).',100,100',
                    'component' => 'UNCONFIGURED',
                    'cmd' => 'UNCONFIGURED',
                    'state' => 'UNCONFIGURED'
                );
                break;
            default:
                return $this->nak($pkt,'Unknown element type "'.$pkt['element'].'"');
        }

        note(notice,"Created new {$pkt['element']} in {$pkt['room']}");
        $this->_save('rooms');

        $this->broadcast(array(
            'type' => 'event',
            'event' => 'roomUpdate',
            'uuid' => $pkt['room'],
            'data' => $this->rooms[$pkt['room']]
        ));

        return true;
    }


    function remove($pkt) {
        if ( !isset($this->rooms[$pkt['room']][$pkt['element']][$pkt['uuid']]) )
            return false;

        unset($this->rooms[$pkt['room']][$pkt['element']][$pkt['uuid']]);

        note(notice,"Removed element {$pkt['uuid']} in {$pkt['room']}");
        $this->_save('rooms');

        $this->broadcast(array(
            'type' => 'event',
            'event' => 'roomUpdate',
            'uuid' => $pkt['room'],
            'data' => $this->rooms[$pkt['room']]
        ));

        return true;
    }

    function _save($file) {
        $string = Spyc::YAMLDump($this->$file);

        if ( $file == 'schedule' ) 
            file_put_contents('/var/spool/stampzilla/reload_schedule',1);

        return file_put_contents('/var/spool/stampzilla/'.$file.'.yml',$string);
    }

    function _load($file) {
        if(is_file('/var/spool/stampzilla/'.$file.'.yml')){
            $this->$file = spyc_load_file('/var/spool/stampzilla/'.$file.'.yml');
            return isset($this->$file);
        }

        $this->$file = array();
        return array();
    }

    function schedule($pkt) {
        // Require time and command
        if ( !isset($pkt['time']) || !isset($pkt['name']) ) {
            $this->_load('schedule');
            return $this->schedule;
        }

        $event = array(
            'time' => $pkt['time'],
            'name' => $pkt['name'],
            'uuid' => uniqid(),
            'commands' => array()
        );

        if ( isset($pkt['interval']) )
            $event['interval'] = $pkt['interval'];

        $this->_load('schedule');
        $this->schedule[] = $event;
        return $this->_save('schedule');
    }

    function scheduleCommand($pkt) {
        if ( !isset($pkt['uuid']) || !isset($pkt['data']) ) {
            return false;
        }

        $pkt['data'] = explode(',',$pkt['data']);
        foreach($pkt['data'] as $key2 => $line2) {
            unset($pkt['data'][$key2]);
            $line2 = explode(':',$line2,2);
            $pkt['data'][$line2[0]] = $line2[1];
        }

        $this->_load('schedule');
        foreach($this->schedule as $key => $line)
            if ( $line['uuid'] == $pkt['uuid'] ) {
                if ( !isset($this->schedule[$key]['commands']) )
                    $this->schedule[$key]['commands'] = array();

                $this->schedule[$key]['commands'][uniqid()] = $pkt['data'];

                return $this->_save('schedule');
            }
    }

    function unschedule($pkt) {
        if ( !isset($pkt['uuid']) )
            return false;

        $this->_load('schedule');

        foreach($this->schedule as $key => $line) 
            if ( $line['uuid'] == $pkt['uuid'] ) {
                unset($this->schedule[$key]);
                return $this->_save('schedule');
            }

        return false;
    }

    function reschedule($pkt) {
        if ( !isset($pkt['uuid']) )
            return false;

        $this->_load('schedule');

        foreach($this->schedule as $key => $line) 
            if ( $line['uuid'] == $pkt['uuid'] ) {

                foreach($pkt as $key2 => $line2) {
                    if( isset($line[$key2]) )
                        $this->schedule[$key][$key2] = $line2;
                }

                file_put_contents('/var/spool/stampzilla/reload_schedule',1);
                return $this->_save('schedule');
            }

        return false;
    }
    function _child(){/*{{{*/

        if ( isset($this->event) && $this->event > -1 ) {
            if ( $this->schedule[$this->event]['timestamp'] < time()+1 ) {
                note(notice,'Trigger event #'.$this->event.' ('.$this->schedule[$this->event]['name'].')');

                if ( !isset($this->schedule[$this->event]['commands']) )
                    $this->schedule[$this->event]['commands'] = array();

                foreach($this->schedule[$this->event]['commands'] as $key => $line)
                    $this->broadcast($line);

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

            if ( !$this->_load('schedule') )
                $this->schedule = array();

            if ( is_file('/var/spool/stampzilla/reload_schedule') )
                unlink('/var/spool/stampzilla/reload_schedule');

            $this->event = null;
        }

        if ( !isset($this->event) || $this->event === null ) {
            $this->event = -1;
            foreach($this->schedule as $key => $line) {
                if ( !isset($this->schedule[$key]['timestamp']) ) {
                    $this->schedule[$key]['timestamp'] = strtotime($line['time']);

                    if ( $this->schedule[$key]['timestamp'] < time() ) {
                        $this->schedule[$key]['timestamp'] = strtotime('+1day',$this->schedule[$key]['timestamp']);
                    }
                }

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

            $this->_save('schedule');
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
    }/*}}}*/

}

$r = new logic();
$r->start('logic','_child');

?>

#!/usr/bin/php
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
    protected $states = array();

    function startup() {/*{{{*/
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

        $this->setState('schedule',$this->schedule);
        $this->setState('rules',$this->rules);

        $this->broadcast(array(
            'type' => 'hello'
        ));
    }/*}}}*/

// RULES - TRIGGERS
    function event($pkt) {/*{{{*/

        if ( isset($pkt['cmd']) && $pkt['cmd'] == 'greetings') {
            if ( $pkt['class'][0] == 'commander' ) {
                $this->sends[$pkt['from']] = 1;
            }
        }

        if ( isset($pkt['type']) && $pkt['type'] == 'state' && !isset($this->sends[$pkt['from']]) ) {
            $this->states[$pkt['from']] = $pkt['data'];
            return $this->stateUpdate();
        }

        if ( isset($pkt['cmd']) && $pkt['cmd'] == 'bye' ) {
            unset($this->sends[$pkt['from']]);
            unset($this->states[$pkt['from']]);
            return $this->stateUpdate();
        }

        if ( !isset($pkt['to']) )
            return;
    }/*}}}*/
    function readStates( $path ) {/*{{{*/
        $path = explode('.',$path);
        $path = array_filter($path, 'strlen'); // Remove empty

        $a = '$this->states';
        foreach($path as $key => $line) {
            if ( !eval("return isset($a);") ) {
                eval("$a = array();");
            }

            if ( eval("return is_object($a);") ) {
                $a .= '->'.$line;
            } else {
                $a .= '["'.$line.'"]';
            }
        }

        return eval("
            if ( isset($a) ) {
                return $a;
            }"
        );
    }/*}}}*/
    function stateUpdate() {/*{{{*/
        $pre = $this->rules;

        foreach($this->rules as $uuid => $rule) {
            $satisfied = true;

            foreach($rule['conditions'] as $key => $line) {
                $val = $this->readStates($line['state']);

                $state = true;

                switch($line['type']) {
                    case 'eq':
                        if ( $val != $line['value'] ) {
                            $state = false;
                        }
                        break;
                    case 'ne':
                        if ( $val == $line['value'] ) {
                            $state = false;
                        }
                        break;
                }

                if ( !$state )
                    $satisfied = false;

                $this->rules[$uuid]['conditions'][$key]['active'] = $state;
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

        if ( $pre != $this->rules ) {
            $this->setState('rules',$this->rules);
        }
    }
/*}}}*/
    function state($pkt) {/*{{{*/
        return array(
            'rooms' => $this->rooms,
            'schedule' => $this->schedule,
        );
    }/*}}}*/

// RULES - CONFIG
    function createRule($pkt) {
        if ( !isset($pkt['name']) )
            return false;

        $this->rules[uniqid()] = array(
            'name' => $pkt['name'],
            'conditions' => array(),
            'enter' => array(),
            'exit' => array()
        );

        $this->_save('rules');
        $this->setState('rules',$this->rules);
        return true;
    }

    function updateRule($pkt) {
        if ( !isset($pkt['uuid']) || !isset($this->rules[$pkt['uuid']]) || !isset($pkt['name']) )
            return false;

        note(debug,'Updating rule '.$pkt['uuid']);

        $this->rules[$pkt['uuid']]['name'] = $pkt['name'];

        $this->_save('rules');
        $this->setState('rules',$this->rules);
        return true;
    }

    function removeRule($pkt) {
        if ( !isset($pkt['uuid']) || !isset($this->rules[$pkt['uuid']]) )
            return false;

        unset($this->rules[$pkt['uuid']]);

        $this->_save('rules');
        $this->setState('rules',$this->rules);
        return true;
    }

    function addCondition($pkt) {
        if ( !isset($pkt['uuid']) || !isset($this->rules[$pkt['uuid']]) )
            return false;

        $this->rules[$pkt['uuid']]['conditions'][] = array(
            'state' => $pkt['state'],
            'type' => $pkt['type'],
            'value' => $pkt['value'],
        );

        $this->_save('rules');
        $this->setState('rules',$this->rules);
        return true;
    }

    function updateCondition($pkt) {
        if ( !isset($pkt['uuid']) || !isset($this->rules[$pkt['uuid']]) || !isset($pkt['key']) || !isset($this->rules[$pkt['uuid']]['conditions'][$pkt['key']])  )
            return false;

        $this->rules[$pkt['uuid']]['conditions'][$pkt['key']] = array(
            'state' => $pkt['state'],
            'type' => $pkt['type'],
            'value' => $pkt['value'],
        );

        $this->_save('rules');
        $this->setState('rules',$this->rules);
        return true;
    }

    function removeCondition($pkt) {
        if ( !isset($pkt['uuid']) || !isset($this->rules[$pkt['uuid']]) || !isset($pkt['key']) || !isset($this->rules[$pkt['uuid']]['conditions'][$pkt['key']]) )
            return false;

        unset($this->rules[$pkt['uuid']]['conditions'][$pkt['key']]);

        $this->_save('rules');
        $this->setState('rules',$this->rules);
        return true;
    }




// ROOMS
    function rooms($pkt) {/*{{{*/
        return $this->rooms;
    }/*}}}*/
    function room($pkt) {/*{{{*/
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
    }/*}}}*/
    function deroom( $pkt ) {/*{{{*/
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
    }/*}}}*/
    function update($pkt) {/*{{{*/
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
    }/*}}}*/
    function create($pkt) {/*{{{*/
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
    }/*}}}*/
    function remove($pkt) {/*{{{*/
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
    }/*}}}*/

// CONFIG FILES
    function _save($file) {/*{{{*/
        $string = Spyc::YAMLDump($this->$file);

        if ( $file == 'schedule' ) {
            file_put_contents('/var/spool/stampzilla/reload_schedule',1);
            $this->setState('schedule',$this->schedule);
        }

        return file_put_contents('/var/spool/stampzilla/'.$file.'.yml',$string);
    }/*}}}*/
    function _load($file) {/*{{{*/
        if(is_file('/var/spool/stampzilla/'.$file.'.yml')){
            $this->$file = spyc_load_file('/var/spool/stampzilla/'.$file.'.yml');
            return isset($this->$file);
        }

        $this->$file = array();
        return array();
    }/*}}}*/

// SCHEDULE
    function schedule($pkt) {/*{{{*/
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
        $this->schedule[$event['uuid']] = $event;
        return $this->_save('schedule');
    }/*}}}*/
    function unschedule($pkt) {/*{{{*/
        if ( !isset($pkt['uuid']) )
            return false;

        $this->_load('schedule');

        foreach($this->schedule as $key => $line) 
            if ( $line['uuid'] == $pkt['uuid'] ) {
                unset($this->schedule[$key]);
                return $this->_save('schedule');
            }

        return false;
    }/*}}}*/
    function reschedule($pkt) {/*{{{*/
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
    }/*}}}*/

    function scheduleCommand($pkt) {/*{{{*/
        if ( !isset($pkt['uuid']) || !isset($pkt['data']) || !trim($pkt['data'])) {
            return false;
        }

        $pkt['data'] = explode(',',trim($pkt['data'],'{}'));
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
    }/*}}}*/
    function unscheduleCommand($pkt) {/*{{{*/
        if ( !isset($pkt['uuid']) )
            return false;

        $this->_load('schedule');

        foreach($this->schedule as $key => $line) 
            foreach($line['commands'] as $key2 => $line2) 
                if ( $key2 == $pkt['uuid'] ) {
                    unset($this->schedule[$key]['commands'][$key2]);
                    return $this->_save('schedule');
                }

        return false;
    }/*}}}*/

// SCHEDULE - Child
    function intercom_event($cmd,$data) {/*{{{*/
        switch($cmd) {
            case 'state':
                $this->setState('runner',$data);
                break;
        }
    }/*}}}*/
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

            if ( $this->event > -1 ) {
                $this->intercom('state',array(
                    $this->event,
                    date('Y-m-d H:i:s',$this->schedule[$this->event]['timestamp'])
                ));
                note(debug,'Next scheduled event is #'.$this->event.' timestamp: '.date('Y-m-d H:i:s',$this->schedule[$this->event]['timestamp']));
            } else
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

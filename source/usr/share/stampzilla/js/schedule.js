schedule = {
    clear: function() {
        $('scheduleContent').innerHTML = '<h3>Start Logic daemon to use schedule</h3>';
    },
    add: function(data) {
        schedule.schedule = data;
        var val = null;
        $$('.scheduleItem').addClass('invalid');
        $('scheduleContent').innerHTML = '';
        for(var uuid in data) {
            val = data[uuid];
            
            if($('schedule_'+uuid) == undefined){
                el = new Element('div', {id: 'schedule_'+uuid,class:'scheduleItem'});
                $('scheduleContent').adopt(el);
                this.draw(el,val);
            }
            else{
                if($('schedule_'+uuid) != undefined){
                    $('schedule_'+uuid).removeClass('invalid');
                    this.draw($('schedule_'+uuid),val);
                }

            }
        }
        $$('.invalid').dispose();
    },
    draw: function(el,data){
        var tmp,button;
        for(var cmduuid in data.commands) {
            tmp = new Element('div', {class: 'cmds'});
            button = new Element('input', {'type' : 'button', 'value' : 'Remove'});
            button.addEvent('click', function(event){
                if(!confirm('Are u sure?')){
                    return false;
                }
                schedule.unscheduleCommand(cmduuid);
                return false;
            });
            tmp.adopt(button);
            for(var fields in data.commands[cmduuid]) {
                tmp.adopt( new Element('div', {class: 'cmd',html: ''+fields+' : '+data.commands[cmduuid][fields]+''}));
            }

            el.adopt(tmp);
        }
        
        var add = new Element('div', {styles: {float:'right'}});
        var input_add = new Element('input', {'type' : 'button', 'value' : 'Add command'});
        input_add.addEvent('click', function(event){
            schedule.showFormCmd(data.uuid);
            event.stopPropagation();
        });
        add.adopt(input_add);
        el.adopt(add);

        var name = new Element('div', {class: 'name'});
        var name_span = new Element('span' ,{html: data.name  });
        name_span.addEvent('click', function(event){
            schedule.showFormSchedule(data.uuid);
            event.stopPropagation();
        });
        name.adopt(name_span);
        el.adopt(name);


        var time = new Element('div', {html: 'Time: '+data.time});
        el.adopt(time);

        var interval = new Element('div', {html: 'Interval: '+data.interval});
        el.adopt(interval);


        if(data.timestamp != undefined){
            var date = new Date(data.timestamp*1000);
            var interval = new Element('div', {html: 'Next run time: '+("0" + date.getHours()).slice(-2)+':'+("0" + date.getMinutes()).slice(-2)+':'+("0" + date.getSeconds()).slice(-2)});
            el.adopt(interval);
        }

        var clear = new Element('div', {styles: {clear:'both'}});
        el.adopt(clear);

    },

    unscheduleCommand: function(uuid){
        var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=unscheduleCommand', onSuccess: function(data){}}).send();
    },
    unschedule: function(uuid){
        var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=unschedule', onSuccess: function(data){}}).send();
    },
    showFormCmd: function(uuid){
        var tmp;
        $('settings_pane').getElement('.copy').style.display = "none";
        var name = new Element('textarea', {'id':'value_cmd','value' : ''});
        tmp = new Element('div', {html: 'Name:'});
        name = tmp.adopt(name);
        var button = new Element('input', {'type' : 'button', 'value' : 'Add'});
        tmp = new Element('div', {html: ''});
        button = tmp.adopt(button);
        button.addEvent('click', function(event){
            var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=scheduleCommand&data='+$('value_cmd').value.replace(/\n/g,','), onSuccess: function(data){
                if(data.success != undefined && data.success){
                    $('settings_pane').fade('out');
                }
            }}).send();
            event.stopPropagation();
            event.preventDefault();
        });
        p = $('settings_pane').getElement('.parameters');
        p.innerHTML='';
        $('settings_pane').getElement('.remove').style.display = "none";
        el = new Element('h1', {html: 'Add command:'});
        el.inject(p);
        p.adopt(name,button);
        el.inject(p,'top');
        $('settings_pane').fade('in');
    },
    showFormSchedule: function(uuid){
        var tmp,name,interval,time,button;
        $('settings_pane').getElement('.copy').style.display = "none";
        if(uuid != null){
            name = new Element('input', {'id':'value_name','type':'text','name' : 'name','value' : schedule.schedule[uuid].name});
            interval = new Element('input', {'id':'value_interval','type':'text','name' : 'interval','value' : schedule.schedule[uuid].interval});
            time = new Element('input', {'id':'value_time','type':'text','name' : 'time','value' : schedule.schedule[uuid].time});
            button = new Element('input', {'type' : 'button', 'value' : 'Update'});
            $('settings_pane').getElement('.remove').style.display = "block";
            $('settings_pane').getElement('.remove').onclick = function() {if(!confirm('are u sure?')){ return false; }schedule.unschedule(uuid); $('settings_pane').fade('out'); return false;};
            el = new Element('h1', {html: 'Edit schedule:'});
        }
        else{
            name = new Element('input', {'id':'value_name','type':'text','name' : 'name','value' : ''});
            interval = new Element('input', {'id':'value_interval','type':'text','name' : 'interval','value' : ''});
            time = new Element('input', {'id':'value_time','type':'text','name' : 'time','value' : ''});
            button = new Element('input', {'type' : 'button', 'value' : 'Add'});
            $('settings_pane').getElement('.remove').style.display = "none";
            el = new Element('h1', {html: 'New Schedule:'});
        }
        tmp = new Element('div', {html: 'Name:'});
        name = tmp.adopt(name);
        tmp = new Element('div', {html: 'Interval:'});
        interval = tmp.adopt(interval);
        tmp = new Element('div', {html: 'Time:'});
        time = tmp.adopt(time);
        tmp = new Element('div', {html: ''});
        button = tmp.adopt(button);
        button.addEvent('click', function(event){
            var cmd = 'schedule';
            if(uuid != null){
                cmd = 'reschedule&uuid='+uuid;
            }
            var jsonRequest = new Request.JSON({url: 'send.php?to=logic&cmd='+cmd+'&name='+$('value_name').value+'&interval='+$('value_interval').value+'&time='+$('value_time').value, onSuccess: function(data){
                if(data.success != undefined && data.success){
                    $('settings_pane').fade('out');
                }
            }}).send();
            event.stopPropagation();
            event.preventDefault();
        });
        p = $('settings_pane').getElement('.parameters');
        p.innerHTML='';
        el.inject(p);
        p.adopt(name,interval,time,button);
        el.inject(p,'top');
        $('settings_pane').fade('in');
    }


};

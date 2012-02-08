schedule = {
    clear: function() {
        $('schedule').innerHTML = '';
    },
    add: function(data) {
        var val = null;
        $$('.scheduleItem').addClass('invalid');
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
        var html = '';
        for(var cmduuid in data.commands) {
            html += '<div class="cmds">';
            html += '<input type="button" value="Remove" onclick="if(!confirm(\'Are u sure?\')){return false;}schedule.unscheduleCommand(\''+cmduuid+'\');return false;" />';
            for(var fields in data.commands[cmduuid]) {
                html += '<div class="cmd">'+fields+' : '+data.commands[cmduuid][fields]+'</div>';
            }
            html += '</div>';
        }
        html += '<div style="float:right;"><input type="button" value="Add command" onclick="schedule.showFormCmd(\''+data.uuid+'\');return false;" /></div>';
        html += '<div class="name">'+data.name+'<input style="margin-left:10px;" type="button" value="Remove" onclick="if(!confirm(\'Are u sure?\')){return false;}schedule.unschedule(\''+data.uuid+'\');return false;" /></div>';
        html += '<div>Time: '+data.time+'</div>';
        html += '<div>Interval: '+data.interval+'</div>';
        if(data.timestamp != undefined){
        var date = new Date(data.timestamp*1000);
            html += '<div>Next run time: '+("0" + date.getHours()).slice(-2)+':'+("0" + date.getMinutes()).slice(-2)+':'+("0" + date.getSeconds()).slice(-2)+'</div>';
        }
        else{
            html += '<div>Next run time: none</div>';
        }

        html += '<div style="clear:both;"></div>';

        el.innerHTML = html;
    },

    unscheduleCommand: function(uuid){
        var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=unscheduleCommand', onSuccess: function(data){}}).send();
    },
    unschedule: function(uuid){
        var jsonRequest = new Request.JSON({url: 'send.php?to=logic&uuid='+uuid+'&cmd=unschedule', onSuccess: function(data){}}).send();
    },
    showFormCmd: function(uuid){
        var tmp;
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
    showFormSchedule: function(){
        var tmp;
        var name = new Element('input', {'id':'value_name','type':'text','name' : 'name','value' : ''});
        tmp = new Element('div', {html: 'Name:'});
        name = tmp.adopt(name);
        var interval = new Element('input', {'id':'value_interval','type':'text','name' : 'interval','value' : ''});
        tmp = new Element('div', {html: 'Interval:'});
        interval = tmp.adopt(interval);
        var time = new Element('input', {'id':'value_time','type':'text','name' : 'time','value' : ''});
        tmp = new Element('div', {html: 'Time:'});
        time = tmp.adopt(time);
        var button = new Element('input', {'type' : 'button', 'value' : 'Add'});
        tmp = new Element('div', {html: ''});
        button = tmp.adopt(button);
        button.addEvent('click', function(event){
            var jsonRequest = new Request.JSON({url: 'send.php?to=logic&cmd=schedule&name='+$('value_name').value+'&interval='+$('value_interval').value+'&time='+$('value_time').value, onSuccess: function(data){
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
        el = new Element('h1', {html: 'New Schedule:'});
        el.inject(p);
        p.adopt(name,interval,time,button);
        el.inject(p,'top');
        $('settings_pane').fade('in');
    }


}
